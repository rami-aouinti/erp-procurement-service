<?php

declare(strict_types=1);

namespace App\Supplier\Application\Service;

use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierClassificationReader;
use App\Supplier\Infrastructure\Audit\SupplierAuditLogger;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class ManageSupplierClassification
{
    private const TYPES = [
        'categories' => ['supplier_categories', 'category'],
        'commodities' => ['supplier_commodity_codes', 'commodity'],
        'tags' => ['supplier_tags', 'tag'],
        'business-units' => ['supplier_business_units', 'business unit'],
    ];

    public function __construct(private Connection $db, private SupplierListCache $cache, private SupplierAuditLogger $audit) {}

    /** @return list<array<string,mixed>> */
    public function list(string $type): array
    {
        [$table] = $this->type($type);
        $columns = match ($type) {
            'categories' => 'id, parent_id AS "parentId", code, name, description, is_active AS "active"',
            'tags' => 'id, code, name, color, is_active AS "active"',
            'commodities' => 'id, code, name, description, is_active AS "active"',
            default => 'id, code, name, is_active AS "active"',
        };
        return $this->db->fetchAllAssociative("SELECT {$columns} FROM {$table} ORDER BY name");
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function create(string $type, array $data): array
    {
        [$table, $label] = $this->type($type);
        $code = strtoupper(trim((string)($data['code'] ?? '')));
        $name = trim((string)($data['name'] ?? ''));
        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,49}$/', $code)) throw new UnprocessableEntityHttpException('Code must contain 2–50 uppercase letters, digits, dots, underscores or hyphens.');
        if ($name === '' || mb_strlen($name) > 150) throw new UnprocessableEntityHttpException('Name is required and must not exceed 150 characters.');
        if (isset($data['description']) && (!is_string($data['description']) || mb_strlen($data['description']) > 500)) throw new UnprocessableEntityHttpException('Description must not exceed 500 characters.');
        if ($type === 'tags' && isset($data['color']) && (!is_string($data['color']) || preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color']) !== 1)) throw new UnprocessableEntityHttpException('Tag color must be a six-digit hexadecimal color.');
        $parentId = $type === 'categories' && isset($data['parentId']) ? (int)$data['parentId'] : null;
        if ($parentId !== null && (int)$this->db->fetchOne('SELECT COUNT(*) FROM supplier_categories WHERE id=:id AND is_active=1', ['id'=>$parentId]) !== 1) throw new UnprocessableEntityHttpException('Parent category does not exist or is inactive.');
        try {
            if ($type === 'categories') {
                $this->db->executeStatement('INSERT INTO supplier_categories (parent_id,code,name,description) VALUES (:parent,:code,:name,:description)', ['parent'=>$parentId,'code'=>$code,'name'=>$name,'description'=>$data['description'] ?? null]);
            } elseif ($type === 'tags') {
                $this->db->executeStatement('INSERT INTO supplier_tags (code,name,color) VALUES (:code,:name,:color)', ['code'=>$code,'name'=>$name,'color'=>$data['color'] ?? null]);
            } elseif ($type === 'commodities') {
                $this->db->executeStatement('INSERT INTO supplier_commodity_codes (code,name,description) VALUES (:code,:name,:description)', ['code'=>$code,'name'=>$name,'description'=>$data['description'] ?? null]);
            } else {
                $this->db->executeStatement('INSERT INTO supplier_business_units (code,name) VALUES (:code,:name)', ['code'=>$code,'name'=>$name]);
            }
        } catch (\Throwable $e) {
            if (str_contains(strtoupper($e->getMessage()), 'ORA-00001')) throw new ConflictHttpException("A {$label} with this code already exists.");
            throw $e;
        }
        $this->cache->invalidate();
        $id = (int)$this->db->fetchOne("SELECT id FROM {$table} WHERE code=:code", ['code'=>$code]);
        return ['id'=>$id,'code'=>$code,'name'=>$name,'parentId'=>$parentId,'active'=>true];
    }

    /** @return array<string,mixed> */
    public function setActive(string $type, int $id, bool $active): array
    {
        [$table] = $this->type($type);
        $count = $this->db->executeStatement("UPDATE {$table} SET is_active=:active, updated_at=SYSTIMESTAMP WHERE id=:id", ['active'=>$active ? 1 : 0,'id'=>$id]);
        if ($count !== 1) throw new NotFoundHttpException('Classification entry not found.');
        $this->cache->invalidate();
        return ['id'=>$id,'active'=>$active];
    }

    /** @return array<string,mixed> */
    public function get(int $supplierId, SupplierClassificationReader $reader): array
    {
        if ((int)$this->db->fetchOne('SELECT COUNT(*) FROM suppliers WHERE id=:id', ['id'=>$supplierId]) !== 1) throw new NotFoundHttpException('Supplier not found.');
        return $reader->forSupplier($supplierId);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function assign(int $supplierId, array $data, string $actor): array
    {
        $categories = $this->ids($data['categoryIds'] ?? []);
        $commodities = $this->ids($data['commodityIds'] ?? []);
        $tags = $this->ids($data['tagIds'] ?? []);
        $units = $this->ids($data['businessUnitIds'] ?? []);
        $tier = isset($data['strategicTier']) && $data['strategicTier'] !== '' ? strtoupper((string)$data['strategicTier']) : null;
        if ($tier !== null && !in_array($tier, ['STANDARD','PREFERRED','STRATEGIC'], true)) throw new UnprocessableEntityHttpException('Strategic tier must be STANDARD, PREFERRED or STRATEGIC.');
        $this->db->beginTransaction();
        try {
            $lockedSupplier = $this->db->fetchOne('SELECT id FROM suppliers WHERE id=:id FOR UPDATE', ['id'=>$supplierId]);
            if ($lockedSupplier === false) throw new NotFoundHttpException('Supplier not found.');
            $this->replace('supplier_supplier_categories','category_id','supplier_categories',$supplierId,$categories,$actor);
            $this->replace('supplier_supplier_commodities','commodity_id','supplier_commodity_codes',$supplierId,$commodities,$actor);
            $this->replace('supplier_supplier_tags','tag_id','supplier_tags',$supplierId,$tags,$actor);
            $this->replace('supplier_supplier_business_units','business_unit_id','supplier_business_units',$supplierId,$units,$actor);
            $this->db->executeStatement('MERGE INTO supplier_classification_profiles p USING (SELECT :id supplier_id FROM dual) x ON (p.supplier_id=x.supplier_id) WHEN MATCHED THEN UPDATE SET p.strategic_tier=:tier,p.updated_by=:actor,p.updated_at=SYSTIMESTAMP,p.version=p.version+1 WHEN NOT MATCHED THEN INSERT (supplier_id,strategic_tier,updated_by) VALUES (:id,:tier,:actor)', ['id'=>$supplierId,'tier'=>$tier,'actor'=>$actor]);
            $this->audit->record($supplierId, 'SUPPLIER_CLASSIFICATION', $supplierId, 'UPDATED', ['categories', 'commodities', 'tags', 'businessUnits', 'strategicTier']);
            $this->db->executeStatement("INSERT INTO supplier_outbox_events (event_id,event_type,aggregate_id,payload,status,attempts,occurred_at,next_attempt_at) VALUES (RAWTOHEX(SYS_GUID()),'SupplierClassificationUpdated',:id,:payload,'PENDING',0,SYSTIMESTAMP,SYSTIMESTAMP)", ['id'=>$supplierId,'payload'=>json_encode(['supplierId'=>$supplierId], JSON_THROW_ON_ERROR)]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->isTransactionActive()) $this->db->rollBack();
            throw $e;
        }
        $this->cache->invalidate();
        return ['supplierId'=>$supplierId,'categoryIds'=>$categories,'commodityIds'=>$commodities,'tagIds'=>$tags,'businessUnitIds'=>$units,'strategicTier'=>$tier];
    }

    /** @return list<int> */
    private function ids(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) throw new UnprocessableEntityHttpException('Classification IDs must be arrays.');
        foreach ($value as $id) if (!is_int($id) || $id < 1) throw new UnprocessableEntityHttpException('Classification IDs must be positive integers.');
        return array_values(array_unique($value));
    }

    /** @param list<int> $ids */
    private function replace(string $join, string $fk, string $taxonomy, int $supplierId, array $ids, string $actor): void
    {
        $existing = array_map('intval', $this->db->fetchFirstColumn("SELECT {$fk} FROM {$join} WHERE supplier_id=:id", ['id'=>$supplierId]));
        foreach ($ids as $id) {
            $active = (int)$this->db->fetchOne("SELECT COUNT(*) FROM {$taxonomy} WHERE id=:id AND is_active=1", ['id'=>$id]) === 1;
            if (!$active && !in_array($id, $existing, true)) throw new UnprocessableEntityHttpException("Classification {$id} does not exist or is inactive.");
        }
        $this->db->executeStatement("DELETE FROM {$join} WHERE supplier_id=:id", ['id'=>$supplierId]);
        foreach ($ids as $id) $this->db->executeStatement("INSERT INTO {$join} (supplier_id,{$fk},assigned_by) VALUES (:supplier,:classification,:actor)", ['supplier'=>$supplierId,'classification'=>$id,'actor'=>$actor]);
    }

    /** @return array{string,string} */
    private function type(string $type): array
    {
        return self::TYPES[$type] ?? throw new NotFoundHttpException('Unknown classification type.');
    }
}

<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Persistence;

use App\Supplier\Application\Port\SupplierClassificationReader;
use Doctrine\DBAL\Connection;

final readonly class OracleSupplierClassificationReader implements SupplierClassificationReader
{
    public function __construct(private Connection $connection) {}

    public function forSupplier(int $supplierId): array
    {
        return [
            'categories' => $this->rows('SELECT c.id, c.code, c.name, c.parent_id AS "parentId" FROM supplier_supplier_categories x JOIN supplier_categories c ON c.id=x.category_id WHERE x.supplier_id=:id ORDER BY c.name', $supplierId),
            'commodities' => $this->rows('SELECT c.id, c.code, c.name FROM supplier_supplier_commodities x JOIN supplier_commodity_codes c ON c.id=x.commodity_id WHERE x.supplier_id=:id ORDER BY c.code', $supplierId),
            'tags' => $this->rows('SELECT t.id, t.code, t.name, t.color FROM supplier_supplier_tags x JOIN supplier_tags t ON t.id=x.tag_id WHERE x.supplier_id=:id ORDER BY t.name', $supplierId),
            'businessUnits' => $this->rows('SELECT b.id, b.code, b.name FROM supplier_supplier_business_units x JOIN supplier_business_units b ON b.id=x.business_unit_id WHERE x.supplier_id=:id ORDER BY b.name', $supplierId),
            'strategicTier' => $this->connection->fetchOne('SELECT strategic_tier FROM supplier_classification_profiles WHERE supplier_id=:id', ['id'=>$supplierId]) ?: null,
        ];
    }

    public function matchingSupplierIds(array $filters): array
    {
        $parts = [];
        $params = [];
        foreach ([
            'categoryId' => ['supplier_supplier_categories', 'category_id'],
            'commodityId' => ['supplier_supplier_commodities', 'commodity_id'],
            'tagId' => ['supplier_supplier_tags', 'tag_id'],
            'businessUnitId' => ['supplier_supplier_business_units', 'business_unit_id'],
        ] as $key => [$table, $column]) {
            if (isset($filters[$key]) && (int) $filters[$key] > 0) {
                $parts[] = "EXISTS (SELECT 1 FROM {$table} x WHERE x.supplier_id=s.id AND x.{$column}=:{$key})";
                $params[$key] = (int) $filters[$key];
            }
        }
        if (isset($filters['strategicTier']) && $filters['strategicTier'] !== '') {
            $parts[] = 'EXISTS (SELECT 1 FROM supplier_classification_profiles p WHERE p.supplier_id=s.id AND p.strategic_tier=:strategicTier)';
            $params['strategicTier'] = strtoupper((string) $filters['strategicTier']);
        }
        if ($parts === []) return [];
        return array_map('intval', $this->connection->fetchFirstColumn('SELECT s.id FROM suppliers s WHERE '.implode(' AND ', $parts), $params));
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $sql, int $id): array
    {
        return $this->connection->fetchAllAssociative($sql, ['id'=>$id]);
    }
}

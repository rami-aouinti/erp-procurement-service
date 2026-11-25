<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Integration;

use App\Supplier\Application\Port\SupplierClassificationReader;
use App\Supplier\Application\Service\ManageSupplierClassification;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class SupplierClassificationOracleTest extends KernelTestCase
{
    private Connection $db;
    private ManageSupplierClassification $manager;
    private SupplierClassificationReader $reader;
    private int $supplierId;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->db = $container->get(Connection::class);
        $this->manager = $container->get(ManageSupplierClassification::class);
        $this->reader = $container->get(SupplierClassificationReader::class);
        $this->db->beginTransaction();
        self::assertNotEmpty($container->get(ManageSupplierClassification::class)->list('business-units'));
        $this->supplierId = (int)$this->db->fetchOne('SELECT id FROM suppliers ORDER BY id FETCH FIRST 1 ROWS ONLY');
        self::assertGreaterThan(0, $this->supplierId, 'Supplier fixtures must exist before classification integration tests.');
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->isTransactionActive()) $this->db->rollBack();
        parent::tearDown();
    }

    public function testTaxonomyCodesAreUniqueAndInactiveEntriesCannotBeAssigned(): void
    {
        $category = $this->manager->create('categories', ['code'=>'F02-IT-'.bin2hex(random_bytes(3)), 'name'=>'IT Services']);
        self::assertGreaterThan(0, $category['id']);

        try {
            $this->manager->create('categories', ['code'=>$category['code'], 'name'=>'Duplicate']);
            self::fail('Duplicate taxonomy codes must be rejected.');
        } catch (ConflictHttpException) {
            self::assertTrue(true);
        }

        $this->manager->assign($this->supplierId, ['categoryIds'=>[(int)$category['id']]], 'integration-test');
        $this->manager->setActive('categories', (int)$category['id'], false);
        // Existing relationships survive deactivation and can be retained.
        $this->manager->assign($this->supplierId, ['categoryIds'=>[(int)$category['id']]], 'integration-test');
        // Once removed, an inactive entry cannot be assigned again.
        $this->manager->assign($this->supplierId, ['categoryIds'=>[]], 'integration-test');
        try {
            $this->manager->assign($this->supplierId, ['categoryIds'=>[(int)$category['id']]], 'integration-test');
            self::fail('Inactive categories must not be newly assigned.');
        } catch (UnprocessableEntityHttpException) {
            self::assertSame([], $this->reader->forSupplier($this->supplierId)['categories']);
        }
    }

    public function testSupplierClassificationReplacementIsAtomicAndSearchableFromOracle(): void
    {
        $suffix = bin2hex(random_bytes(3));
        $category = $this->manager->create('categories', ['code'=>'F02-CAT-'.$suffix, 'name'=>'Hardware']);
        $commodity = $this->manager->create('commodities', ['code'=>'F02-COM-'.$suffix, 'name'=>'Computer equipment']);
        $tag = $this->manager->create('tags', ['code'=>'F02-TAG-'.$suffix, 'name'=>'Critical']);
        $unit = $this->manager->create('business-units', ['code'=>'F02-BU-'.$suffix, 'name'=>'IT Procurement']);

        $saved = $this->manager->assign($this->supplierId, [
            'categoryIds'=>[(int)$category['id']],
            'commodityIds'=>[(int)$commodity['id']],
            'tagIds'=>[(int)$tag['id'], (int)$tag['id']],
            'businessUnitIds'=>[(int)$unit['id']],
            'strategicTier'=>'STRATEGIC',
        ], 'integration-test');

        self::assertSame([(int)$category['id']], $saved['categoryIds']);
        self::assertSame([(int)$tag['id']], $saved['tagIds'], 'Duplicate IDs are normalized.');
        $read = $this->reader->forSupplier($this->supplierId);
        self::assertSame('STRATEGIC', $read['strategicTier']);
        self::assertCount(1, $read['categories']);
        self::assertCount(1, $read['commodities']);
        self::assertCount(1, $read['tags']);
        self::assertCount(1, $read['businessUnits']);

        $matches = $this->reader->matchingSupplierIds(['categoryId'=>(int)$category['id'], 'strategicTier'=>'STRATEGIC']);
        self::assertContains($this->supplierId, $matches);

        try {
            $this->manager->assign($this->supplierId, ['categoryIds'=>[], 'commodityIds'=>[999999999], 'tagIds'=>[], 'businessUnitIds'=>[], 'strategicTier'=>'STANDARD'], 'integration-test');
            self::fail('An invalid commodity must abort the complete classification replacement.');
        } catch (UnprocessableEntityHttpException) {
            self::assertSame([(int)$category['id']], array_map(static fn(array $item): int => (int)$item['id'], $this->reader->forSupplier($this->supplierId)['categories']));
            self::assertSame('STRATEGIC', $this->reader->forSupplier($this->supplierId)['strategicTier']);
        }

        $this->manager->assign($this->supplierId, ['categoryIds'=>[], 'commodityIds'=>[], 'tagIds'=>[], 'businessUnitIds'=>[], 'strategicTier'=>null], 'integration-test');
        $cleared = $this->reader->forSupplier($this->supplierId);
        self::assertSame([], $cleared['categories']);
        self::assertNull($cleared['strategicTier']);
    }
}

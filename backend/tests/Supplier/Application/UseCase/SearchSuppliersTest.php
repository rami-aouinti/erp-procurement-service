<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Application\UseCase;

use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Application\UseCase\SearchSuppliers;
use App\Supplier\Domain\Model\Supplier;
use App\Supplier\Domain\Repository\SupplierRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SearchSuppliersTest extends TestCase
{
    public function testReturnsCachedSearchResultsWithoutCallingElasticsearch(): void
    {
        $expected = [['id' => 12, 'name' => 'Cached Supplier']];
        $cache = $this->createMock(SupplierListCache::class);
        $cache->expects(self::once())->method('get')->willReturn($expected);
        $cache->expects(self::never())->method('set');

        $search = $this->createMock(SupplierSearchIndex::class);
        $search->expects(self::never())->method('search');

        $repository = $this->createMock(SupplierRepository::class);
        $repository->expects(self::never())->method('findAll');

        self::assertSame($expected, (new SearchSuppliers($search, $cache, $repository))->execute());
    }

    public function testUsesElasticsearchAndStoresResultsInRedisCache(): void
    {
        $expected = [['id' => 12, 'name' => 'Acme GmbH']];
        $cache = $this->createMock(SupplierListCache::class);
        $cache->method('get')->willReturn(null);
        $cache->expects(self::once())->method('set')->with(
            self::callback(static fn (string $key): bool => str_starts_with($key, 'search:')),
            $expected,
            60,
        );

        $search = $this->createMock(SupplierSearchIndex::class);
        $search->expects(self::once())->method('search')->with('Acme', 'ACTIVE')->willReturn($expected);

        $repository = $this->createMock(SupplierRepository::class);
        $repository->expects(self::never())->method('findAll');

        self::assertSame($expected, (new SearchSuppliers($search, $cache, $repository))->execute('Acme', 'ACTIVE'));
    }

    public function testOracleFallbackSearchIncludesCountry(): void
    {
        $cache = $this->createMock(SupplierListCache::class);
        $cache->method('get')->willReturn(null);

        $search = $this->createMock(SupplierSearchIndex::class);
        $search->method('search')->willThrowException(new RuntimeException('Elasticsearch unavailable'));

        $supplier = Supplier::reconstitute(
            9,
            'European Supplier',
            null,
            null,
            null,
            new \DateTimeImmutable(),
            'ACTIVE',
            null,
            null,
            'Berlin',
            null,
            'DE',
        );
        $repository = $this->createMock(SupplierRepository::class);
        $repository->method('findAll')->willReturn([$supplier]);

        $results = (new SearchSuppliers($search, $cache, $repository))->execute('DE');

        self::assertCount(1, $results);
        self::assertSame('DE', $results[0]['country']);
    }

    public function testFallsBackToOracleWhenElasticsearchIsUnavailable(): void
    {
        $cache = $this->createMock(SupplierListCache::class);
        $cache->method('get')->willReturn(null);
        $cache->expects(self::once())->method('set');

        $search = $this->createMock(SupplierSearchIndex::class);
        $search->method('search')->willThrowException(new RuntimeException('Elasticsearch unavailable'));

        $supplier = Supplier::reconstitute(8, 'Acme GmbH', 'contact@acme.de', '+49 30 1234567', null, new \DateTimeImmutable(), 'ACTIVE', null, null, 'Berlin');
        $repository = $this->createMock(SupplierRepository::class);
        $repository->expects(self::once())->method('findAll')->willReturn([$supplier]);

        $results = (new SearchSuppliers($search, $cache, $repository))->execute('Berlin', 'ACTIVE');

        self::assertCount(1, $results);
        self::assertSame(8, $results[0]['id']);
        self::assertSame('Acme GmbH', $results[0]['name']);
    }
}

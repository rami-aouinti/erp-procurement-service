<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Transport\Console;

use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Domain\Repository\SupplierRepository;
use App\Supplier\Transport\Console\Command\ReindexSuppliersCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ReindexSuppliersCommandTest extends TestCase
{
    public function testRebuildsTheReadModelFromOracleAndInvalidatesCachedLists(): void
    {
        $suppliers = [];
        $repository = $this->createMock(SupplierRepository::class);
        $repository->expects(self::once())
            ->method('findAll')
            ->willReturn($suppliers);

        $searchIndex = $this->createMock(SupplierSearchIndex::class);
        $searchIndex->expects(self::once())
            ->method('reindex')
            ->with($suppliers)
            ->willReturn(0);

        $cache = $this->createMock(SupplierListCache::class);
        $cache->expects(self::once())->method('invalidate');

        $tester = new CommandTester(new ReindexSuppliersCommand($repository, $searchIndex, $cache));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('0 suppliers indexed successfully.', $tester->getDisplay());
    }
}

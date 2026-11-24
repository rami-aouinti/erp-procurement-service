<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Application\UseCase;

use App\Supplier\Application\DTO\SupplierData;
use App\Supplier\Application\UseCase\CreateSupplier;
use App\Supplier\Application\UseCase\DeleteSupplier;
use App\Supplier\Application\UseCase\GetSupplier;
use App\Supplier\Application\UseCase\ListSuppliers;
use App\Supplier\Application\UseCase\UpdateSupplier;
use App\Supplier\Application\Validator\SupplierDataValidator;
use App\Supplier\Domain\Exception\SupplierEmailAlreadyUsed;
use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\Supplier;
use App\Supplier\Domain\Repository\SupplierRepository;
use PHPUnit\Framework\TestCase;

final class SupplierUseCasesTest extends TestCase
{
    private function readModel(): SupplierReadModelSynchronizer
    {
        return new SupplierReadModelSynchronizer(
            $this->createMock(SupplierSearchIndex::class),
            $this->createMock(SupplierListCache::class),
        );
    }

    public function testCreateSupplierPersistsDomainModel(): void
    {
        $repository = $this->createMock(SupplierRepository::class);
        $repository->expects(self::once())->method('emailExists')->with('new@example.com')->willReturn(false);
        $repository->expects(self::once())->method('save')->willReturnCallback(static function (Supplier $supplier): Supplier {
            return Supplier::reconstitute(42, $supplier->name(), $supplier->email(), $supplier->phone(), $supplier->taxNumber(), $supplier->createdAt());
        });

        $created = (new CreateSupplier($repository, new SupplierDataValidator(), $this->readModel()))->execute(new SupplierData('New Supplier', 'new@example.com'));

        self::assertSame(42, $created->id());
        self::assertSame('New Supplier', $created->name());
    }

    public function testCreateRejectsDuplicateEmail(): void
    {
        $repository = $this->createMock(SupplierRepository::class);
        $repository->method('emailExists')->willReturn(true);
        $repository->expects(self::never())->method('save');

        $this->expectException(SupplierEmailAlreadyUsed::class);
        (new CreateSupplier($repository, new SupplierDataValidator()))->execute(new SupplierData('Duplicate', 'taken@example.com'));
    }

    public function testCreateAllowsMissingEmailWithoutUniquenessQuery(): void
    {
        $repository = $this->createMock(SupplierRepository::class);
        $repository->expects(self::never())->method('emailExists');
        $repository->expects(self::once())->method('save')->willReturnArgument(0);

        self::assertNull((new CreateSupplier($repository, new SupplierDataValidator()))->execute(new SupplierData('No Email'))->id());
    }

    public function testGetReturnsSupplierAndThrowsWhenMissing(): void
    {
        $supplier = Supplier::reconstitute(7, 'Acme', null, null, null, new \DateTimeImmutable());
        $repository = $this->createMock(SupplierRepository::class);
        $repository->method('findById')->willReturnMap([[7, $supplier], [99, null]]);
        self::assertSame($supplier, (new GetSupplier($repository))->execute(7));


        try {
            (new GetSupplier($repository))->execute(99);
            self::fail('Expected SupplierNotFound.');
        } catch (SupplierNotFound) {
            self::assertTrue(true);
        }
    }

    public function testListReturnsRepositoryCollection(): void
    {
        $suppliers = [Supplier::register('A', null, null, null), Supplier::register('B', null, null, null)];
        $repository = $this->createMock(SupplierRepository::class);
        $repository->expects(self::once())->method('findAll')->willReturn($suppliers);
        self::assertSame($suppliers, (new ListSuppliers($repository))->execute());
    }

    public function testUpdateChangesSupplierAndChecksDuplicateEmail(): void
    {
        $supplier = Supplier::reconstitute(5, 'Old name', null, null, null, new \DateTimeImmutable());
        $repository = $this->createMock(SupplierRepository::class);
        $repository->method('findById')->with(5)->willReturn($supplier);
        $repository->expects(self::once())->method('emailExists')->with('new@example.com', 5)->willReturn(false);
        $repository->expects(self::once())->method('save')->willReturnArgument(0);

        $updated = (new UpdateSupplier($repository, new SupplierDataValidator(), $this->readModel()))->execute(5, new SupplierData('New name', 'new@example.com'));
        self::assertSame('New name', $updated->name());
        self::assertSame('new@example.com', $updated->email());
    }

    public function testUpdateRejectsDuplicateEmail(): void
    {
        $supplier = Supplier::reconstitute(5, 'Old name', null, null, null, new \DateTimeImmutable());
        $repository = $this->createMock(SupplierRepository::class);
        $repository->method('findById')->willReturn($supplier);
        $repository->method('emailExists')->willReturn(true);
        $repository->expects(self::never())->method('save');

        $this->expectException(SupplierEmailAlreadyUsed::class);
        (new UpdateSupplier($repository, new SupplierDataValidator()))->execute(5, new SupplierData('Name', 'taken@example.com'));
    }

    public function testDeleteRemovesExistingSupplier(): void
    {
        $supplier = Supplier::reconstitute(5, 'Acme', null, null, null, new \DateTimeImmutable());
        $repository = $this->createMock(SupplierRepository::class);
        $repository->method('findById')->willReturn($supplier);
        $repository->expects(self::once())->method('delete')->with($supplier);

        (new DeleteSupplier($repository, $this->readModel()))->execute(5);
    }

    public function testDeleteThrowsWhenSupplierDoesNotExist(): void
    {
        $repository = $this->createMock(SupplierRepository::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('delete');

        $this->expectException(SupplierNotFound::class);
        (new DeleteSupplier($repository))->execute(404);
    }
}

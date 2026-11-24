<?php
declare(strict_types=1);
namespace App\Tests\Supplier\Domain\Model;
use App\Supplier\Domain\Exception\InvalidSupplierData;
use App\Supplier\Domain\Model\Supplier;
use PHPUnit\Framework\TestCase;
final class SupplierTest extends TestCase
{
    public function testItRegistersSupplierAndNormalizesOptionalFields(): void
    {
        $supplier = Supplier::register('  Acme GmbH  ', '  contact@acme.de ', '  ', null);
        self::assertSame('Acme GmbH', $supplier->name());
        self::assertSame('contact@acme.de', $supplier->email());
        self::assertNull($supplier->phone());
        self::assertNull($supplier->id());
    }
    public function testItRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidSupplierData::class);
        Supplier::register('   ', null, null, null);
    }
    public function testItRejectsAnInvalidEmail(): void
    {
        $this->expectException(InvalidSupplierData::class);
        Supplier::register('Acme GmbH', 'not-an-email', null, null);
    }
    public function testItUpdatesSupplierDetails(): void
    {
        $supplier = Supplier::register('Acme GmbH', null, null, null);
        $supplier->update('Acme Europe GmbH', 'info@acme.eu', '+49 421 123', 'DE123');
        self::assertSame('Acme Europe GmbH', $supplier->name());
        self::assertSame('info@acme.eu', $supplier->email());
    }
}

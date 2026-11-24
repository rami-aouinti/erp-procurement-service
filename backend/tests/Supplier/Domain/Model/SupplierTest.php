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

    public function testItRejectsNameLongerThanDatabaseColumn(): void
    {
        $this->expectException(InvalidSupplierData::class);
        Supplier::register(str_repeat('x', 151), null, null, null);
    }

    public function testItRejectsPhoneLongerThanDatabaseColumn(): void
    {
        $this->expectException(InvalidSupplierData::class);
        Supplier::register('Acme', null, str_repeat('1', 31), null);
    }

    public function testItRejectsTaxNumberLongerThanDatabaseColumn(): void
    {
        $this->expectException(InvalidSupplierData::class);
        Supplier::register('Acme', null, null, str_repeat('1', 51));
    }

    public function testItUpdatesSupplierDetails(): void
    {
        $supplier = Supplier::register('Acme GmbH', null, null, null);
        $supplier->update('Acme Europe GmbH', 'info@acme.eu', '+49 421 123', 'DE123');
        self::assertSame('Acme Europe GmbH', $supplier->name());
        self::assertSame('info@acme.eu', $supplier->email());
        self::assertSame('+49 421 123', $supplier->phone());
        self::assertSame('DE123', $supplier->taxNumber());
    }

    public function testItReconstitutesPersistedIdentityAndTimestamp(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01T12:00:00+00:00');
        $supplier = Supplier::reconstitute(10, 'Acme', null, null, null, $createdAt);
        self::assertSame(10, $supplier->id());
        self::assertSame($createdAt, $supplier->createdAt());
    }
}

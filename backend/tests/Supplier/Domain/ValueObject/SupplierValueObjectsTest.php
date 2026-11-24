<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Domain\ValueObject;

use App\Supplier\Domain\Enum\SupplierStatus;
use App\Supplier\Domain\Exception\InvalidSupplierData;
use App\Supplier\Domain\ValueObject\SupplierAddress;
use App\Supplier\Domain\ValueObject\SupplierEmail;
use App\Supplier\Domain\ValueObject\SupplierName;
use PHPUnit\Framework\TestCase;

final class SupplierValueObjectsTest extends TestCase
{
    public function testNameIsTrimmed(): void
    {
        self::assertSame('Acme GmbH', SupplierName::from(' Acme GmbH ')->value);
    }

    public function testEmailIsNormalized(): void
    {
        self::assertSame('info@acme.de', SupplierEmail::fromNullable(' INFO@ACME.DE ')?->value);
        self::assertNull(SupplierEmail::fromNullable(' '));
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->expectException(InvalidSupplierData::class);
        SupplierEmail::fromNullable('invalid');
    }

    public function testStatusIsCaseInsensitive(): void
    {
        self::assertSame(SupplierStatus::ACTIVE, SupplierStatus::fromInput(' active '));
    }

    public function testAddressNormalizesCountryCode(): void
    {
        self::assertSame('DE', SupplierAddress::fromNullable(' Main St ', 'Berlin', '10115', 'de')->country);
    }
}

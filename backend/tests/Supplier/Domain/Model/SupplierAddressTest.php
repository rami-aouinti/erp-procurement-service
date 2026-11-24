<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Domain\Model;

use App\Supplier\Domain\Model\SupplierAddress;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SupplierAddressTest extends TestCase
{
    public function testItCreatesAValidShippingAddress(): void
    {
        $address = new SupplierAddress(
            null, 12, 'SHIPPING', 'Warehouse', 'Example GmbH',
            'Hauptstrasse 1', null, '10115', 'Berlin', null, 'DE', true,
        );

        self::assertSame('SHIPPING', $address->addressType);
        self::assertSame('DE', $address->country);
        self::assertTrue($address->default);
    }

    public function testItRejectsAnUnknownAddressType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SupplierAddress(null, 12, 'HOME', null, null, 'Street 1', null, '10115', 'Berlin', null, 'DE');
    }

    public function testItRejectsAnInvalidCountryCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SupplierAddress(null, 12, 'BILLING', null, null, 'Street 1', null, '10115', 'Berlin', null, 'Germany');
    }
}

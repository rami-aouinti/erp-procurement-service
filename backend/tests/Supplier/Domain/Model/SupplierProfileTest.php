<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Domain\Model;

use App\Supplier\Domain\Model\SupplierProfile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SupplierProfileTest extends TestCase
{
    public function testItCreatesAProfileWithDefaultPreferences(): void
    {
        $profile = new SupplierProfile(42, 'Acme GmbH', 'HRB123', 'DE123456789', 'https://acme.example');

        self::assertSame(42, $profile->supplierId);
        self::assertSame('de', $profile->preferredLanguage);
        self::assertSame('EUR', $profile->preferredCurrency);
        self::assertSame(30, $profile->paymentTermsDays);
    }

    public function testItRejectsPaymentTermsOutsideSupportedRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SupplierProfile(42, null, null, null, null, 'de', 'EUR', 366);
    }

    public function testItRejectsInvalidCurrency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SupplierProfile(42, null, null, null, null, 'de', 'EURO');
    }

    public function testItRejectsInvalidLanguageTag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SupplierProfile(42, null, null, null, null, 'german language', 'EUR');
    }

    public function testItRejectsInvalidWebsite(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SupplierProfile(42, null, null, null, 'not-a-url');
    }

    public function testItRejectsLegalNameLongerThanDatabaseColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SupplierProfile(42, str_repeat('x', 201), null, null, null);
    }
}

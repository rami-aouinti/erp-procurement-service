<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Domain\Model;

use App\Supplier\Domain\Model\SupplierContact;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SupplierContactTest extends TestCase
{
    public function testItCreatesAValidContact(): void
    {
        $contact = new SupplierContact(null, 12, 'PURCHASING', 'Ada', 'Lovelace', 'Buyer', 'ada@example.com', '+4912345', true);

        self::assertSame(12, $contact->supplierId);
        self::assertSame('ada@example.com', $contact->email);
        self::assertTrue($contact->primary);
    }

    public function testItRejectsAnInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SupplierContact(null, 12, 'GENERAL', 'Ada', 'Lovelace', null, 'not-an-email', null);
    }

    public function testItRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SupplierContact(null, 12, 'GENERAL', ' ', 'Lovelace', null, null, null);
    }
}

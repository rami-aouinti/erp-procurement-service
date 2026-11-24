<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Domain\ValueObject;

use App\Supplier\Domain\ValueObject\Iban;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class IbanTest extends TestCase
{
    public function testItNormalizesAValidIban(): void
    {
        self::assertSame('DE89370400440532013000', (new Iban('de89 3704 0044 0532 0130 00'))->value);
    }

    public function testItRejectsAnInvalidChecksum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Iban('DE00370400440532013000');
    }

    public function testItRejectsAnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Iban('not-an-iban');
    }
}

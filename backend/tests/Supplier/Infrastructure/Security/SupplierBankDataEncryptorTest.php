<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Infrastructure\Security;

use App\Supplier\Infrastructure\Security\SupplierBankDataEncryptor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SupplierBankDataEncryptorTest extends TestCase
{
    private const KEY = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';

    public function testItEncryptsAndDecryptsBankData(): void
    {
        $encryptor = new SupplierBankDataEncryptor(self::KEY, 'v1');
        $encrypted = $encryptor->encrypt('DE89370400440532013000');

        self::assertNotSame('DE89370400440532013000', $encrypted);
        self::assertSame('DE89370400440532013000', $encryptor->decrypt($encrypted));
        self::assertSame('v1', $encryptor->keyVersion());
    }

    public function testItUsesDifferentCiphertextForTheSamePlaintext(): void
    {
        $encryptor = new SupplierBankDataEncryptor(self::KEY, 'v1');

        self::assertNotSame(
            $encryptor->encrypt('DE89370400440532013000'),
            $encryptor->encrypt('DE89370400440532013000'),
        );
    }

    public function testItRejectsAnInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SupplierBankDataEncryptor('not-a-key', 'v1');
    }

    public function testItRejectsTamperedCiphertext(): void
    {
        $encryptor = new SupplierBankDataEncryptor(self::KEY, 'v1');
        $encrypted = $encryptor->encrypt('DE89370400440532013000');
        $parts = explode('.', $encrypted);
        $parts[2] = rtrim(strtr(base64_encode('tampered'), '+/', '-_'), '=');

        $this->expectException(RuntimeException::class);
        $encryptor->decrypt(implode('.', $parts));
    }

    public function testItMasksIban(): void
    {
        self::assertSame('DE••••••••••••••••1300', (new SupplierBankDataEncryptor(self::KEY, 'v1'))->maskIban('DE89 3704 0044 0532 0130 00'));
    }
}

<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Security;

use InvalidArgumentException;
use RuntimeException;

final readonly class SupplierBankDataEncryptor
{
    private string $key;

    public function __construct(string $base64Key, private string $keyVersion)
    {
        $key = base64_decode($base64Key, true);
        if ($key === false || strlen($key) !== 32) {
            throw new InvalidArgumentException('Supplier bank encryption key must be a base64-encoded 32-byte key.');
        }
        if (trim($keyVersion) === '' || strlen($keyVersion) > 30) {
            throw new InvalidArgumentException('Supplier bank encryption key version must contain 1 to 30 characters.');
        }

        $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new InvalidArgumentException('Bank data cannot be empty.');
        }

        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Unable to encrypt supplier bank data.');
        }

        return implode('.', [
            rtrim(strtr(base64_encode($nonce), '+/', '-_'), '='),
            rtrim(strtr(base64_encode($tag), '+/', '-_'), '='),
            rtrim(strtr(base64_encode($ciphertext), '+/', '-_'), '='),
        ]);
    }

    public function decrypt(string $payload): string
    {
        $parts = explode('.', $payload);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid encrypted supplier bank data.');
        }

        [$encodedNonce, $encodedTag, $encodedCiphertext] = $parts;
        $nonce = self::decode($encodedNonce);
        $tag = self::decode($encodedTag);
        $ciphertext = self::decode($encodedCiphertext);

        if (strlen($nonce) !== 12 || strlen($tag) !== 16) {
            throw new InvalidArgumentException('Invalid encrypted supplier bank data.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Supplier bank data authentication failed.');
        }

        return $plaintext;
    }

    public function keyVersion(): string
    {
        return $this->keyVersion;
    }

    public static function maskIban(string $iban): string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', '', $iban));
        $length = strlen($normalized);
        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return substr($normalized, 0, 2)
            . str_repeat('•', max(4, $length - 6))
            . substr($normalized, -4);
    }

    private static function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid encrypted supplier bank data.');
        }

        return $decoded;
    }
}

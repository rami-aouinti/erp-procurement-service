<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Security;

use App\Supplier\Application\Port\SupplierBankDataCipher;
use InvalidArgumentException;
use RuntimeException;

final readonly class SupplierBankDataEncryptor implements SupplierBankDataCipher
{
    /** @var array<string, string> */
    private array $keys;

    private string $key;

    public function __construct(
        string $base64Key,
        private string $keyVersion,
        string $previousKeysJson = '{}',
    ) {
        $key = self::decodeKey($base64Key);
        if (trim($keyVersion) === '' || strlen($keyVersion) > 30) {
            throw new InvalidArgumentException('Supplier bank encryption key version must contain 1 to 30 characters.');
        }

        try {
            $previousKeys = json_decode($previousKeysJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('Previous supplier bank encryption keys must be valid JSON.', 0, $exception);
        }
        if (!is_array($previousKeys) || array_is_list($previousKeys) && $previousKeys !== []) {
            throw new InvalidArgumentException('Previous supplier bank encryption keys must be a JSON object keyed by version.');
        }

        $keys = [$keyVersion => $key];
        foreach ($previousKeys as $version => $encodedKey) {
            if (!is_string($version) || trim($version) === '' || strlen($version) > 30 || !is_string($encodedKey)) {
                throw new InvalidArgumentException('Each previous encryption key must have a valid version and base64 key.');
            }
            if (isset($keys[$version])) {
                throw new InvalidArgumentException(sprintf('Duplicate supplier bank encryption key version "%s".', $version));
            }
            $keys[$version] = self::decodeKey($encodedKey);
        }

        $this->key = $key;
        $this->keys = $keys;
    }

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new InvalidArgumentException('Bank data cannot be empty.');
        }

        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Unable to encrypt supplier bank data.');
        }

        return implode('.', [
            self::encode($nonce),
            self::encode($tag),
            self::encode($ciphertext),
        ]);
    }

    public function decrypt(string $payload, ?string $keyVersion = null): string
    {
        $version = $keyVersion ?? $this->keyVersion;
        $key = $this->keys[$version] ?? null;
        if ($key === null) {
            throw new RuntimeException(sprintf('Supplier bank encryption key version "%s" is not configured.', $version));
        }

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

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plaintext === false) {
            throw new RuntimeException('Supplier bank data authentication failed.');
        }

        return $plaintext;
    }

    public function keyVersion(): string
    {
        return $this->keyVersion;
    }

    public function maskIban(string $iban): string
    {
        $normalized = strtoupper((string) preg_replace('/\\s+/', '', $iban));
        $length = strlen($normalized);
        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return substr($normalized, 0, 2)
            . str_repeat('•', max(4, $length - 6))
            . substr($normalized, -4);
    }

    private static function decodeKey(string $base64Key): string
    {
        $key = base64_decode($base64Key, true);
        if ($key === false || strlen($key) !== 32) {
            throw new InvalidArgumentException('Supplier bank encryption key must be a base64-encoded 32-byte key.');
        }

        return $key;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
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

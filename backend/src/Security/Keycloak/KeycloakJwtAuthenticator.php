<?php

declare(strict_types=1);

namespace App\Security\Keycloak;

final class KeycloakJwtAuthenticator
{
    /** @var array<string, array{key: string, expiresAt: int}> */
    private static array $keyCache = [];

    public function __construct(
        private readonly string $issuer,
        private readonly string $audience,
        private readonly string $jwksUrl,
    ) {
    }

    /** @return array<string, mixed> */
    public function authenticate(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Malformed bearer token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJson($encodedHeader);
        $claims = $this->decodeJson($encodedPayload);
        if (($header['alg'] ?? null) !== 'RS256' || !is_string($header['kid'] ?? null)) {
            throw new \RuntimeException('Unsupported token signing algorithm.');
        }

        $publicKey = $this->publicKey($header['kid']);
        $signature = $this->base64UrlDecode($encodedSignature);
        if (openssl_verify($encodedHeader.'.'.$encodedPayload, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new \RuntimeException('Invalid token signature.');
        }

        $now = time();
        if (($claims['iss'] ?? null) !== $this->issuer
            || !is_numeric($claims['exp'] ?? null)
            || (int) $claims['exp'] <= $now - 30
            || (isset($claims['nbf']) && (!is_numeric($claims['nbf']) || (int) $claims['nbf'] > $now + 30))
            || !$this->hasAudience($claims['aud'] ?? null)
            || !is_string($claims['sub'] ?? null)
            || $claims['sub'] === '') {
            throw new \RuntimeException('Token claims are invalid or expired.');
        }

        return $claims;
    }

    private function hasAudience(mixed $audience): bool
    {
        return is_string($audience)
            ? hash_equals($this->audience, $audience)
            : (is_array($audience) && in_array($this->audience, $audience, true));
    }

    private function publicKey(string $kid): string
    {
        if (isset(self::$keyCache[$kid]) && self::$keyCache[$kid]['expiresAt'] > time()) {
            return self::$keyCache[$kid]['key'];
        }

        $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => false]]);
        $body = @file_get_contents($this->jwksUrl, false, $context);
        if (!is_string($body)) {
            throw new \RuntimeException('Keycloak signing keys are unavailable.');
        }
        $jwks = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        foreach (($jwks['keys'] ?? []) as $jwk) {
            if (($jwk['kid'] ?? null) === $kid && ($jwk['kty'] ?? null) === 'RSA' && ($jwk['use'] ?? 'sig') === 'sig') {
                $pem = $this->rsaPublicKeyToPem(
                    $this->base64UrlDecode((string) ($jwk['n'] ?? '')),
                    $this->base64UrlDecode((string) ($jwk['e'] ?? '')),
                );
                self::$keyCache[$kid] = ['key' => $pem, 'expiresAt' => time() + 300];
                return $pem;
            }
        }

        throw new \RuntimeException('No matching Keycloak signing key was found.');
    }

    private function decodeJson(string $value): array
    {
        $decoded = json_decode($this->base64UrlDecode($value), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid JWT JSON section.');
        }
        return $decoded;
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64url value.');
        }
        return $decoded;
    }

    private function rsaPublicKeyToPem(string $modulus, string $exponent): string
    {
        $integer = function (string $value): string {
            if ($value === '') {
                throw new \RuntimeException('Invalid RSA key material.');
            }
            if ((ord($value[0]) & 0x80) !== 0) {
                $value = "\0".$value;
            }
            return "\x02".$this->derLength(strlen($value)).$value;
        };
        $rsa = $integer($modulus).$integer($exponent);
        $rsa = "\x30".$this->derLength(strlen($rsa)).$rsa;
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $bitString = "\x03".$this->derLength(strlen($rsa) + 1)."\0".$rsa;
        $sequence = $algorithm.$bitString;
        $der = "\x30".$this->derLength(strlen($sequence)).$sequence;
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $encoded = ltrim(pack('N', $length), "\0");
        return chr(0x80 | strlen($encoded)).$encoded;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Security\Keycloak;

use App\Security\Keycloak\KeycloakJwtAuthenticator;
use PHPUnit\Framework\TestCase;

final class KeycloakJwtAuthenticatorTest extends TestCase
{
    public function testValidRs256AccessTokenIsAccepted(): void
    {
        [$authenticator, $token] = $this->signedToken(time() + 300);

        $claims = $authenticator->authenticate($token);

        self::assertSame('user-123', $claims['sub']);
        self::assertSame('rami', $claims['preferred_username']);
    }

    public function testExpiredAccessTokenIsRejected(): void
    {
        [$authenticator, $token] = $this->signedToken(time() - 120);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token claims are invalid or expired.');
        $authenticator->authenticate($token);
    }

    public function testTamperedAccessTokenIsRejected(): void
    {
        [$authenticator, $token] = $this->signedToken(time() + 300);
        $parts = explode('.', $token);
        $parts[1] = $this->base64UrlEncode(json_encode(['sub' => 'attacker'], JSON_THROW_ON_ERROR));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid token signature.');
        $authenticator->authenticate(implode('.', $parts));
    }

    /** @return array{KeycloakJwtAuthenticator, string} */
    private function signedToken(int $expiresAt): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $privateKey));
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);

        $kid = bin2hex(random_bytes(8));
        $jwks = ['keys' => [[
            'kid' => $kid,
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ]]];
        $jwksUrl = 'data://text/plain;base64,'.base64_encode(json_encode($jwks, JSON_THROW_ON_ERROR));

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => 'https://identity.example/realms/erp',
            'aud' => ['erp-procurement-api', 'account'],
            'sub' => 'user-123',
            'preferred_username' => 'rami',
            'exp' => $expiresAt,
            'iat' => time(),
        ], JSON_THROW_ON_ERROR));
        $input = $header.'.'.$payload;
        self::assertTrue(openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256));
        $token = $input.'.'.$this->base64UrlEncode($signature);

        return [
            new KeycloakJwtAuthenticator(
                'https://identity.example/realms/erp',
                'erp-procurement-api',
                $jwksUrl,
            ),
            $token,
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

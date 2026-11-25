<?php

declare(strict_types=1);

namespace App\Configuration\Application\Port;

interface ConfigurationCache
{
    /** @return array<string, mixed>|null */
    public function get(string $key): ?array;

    /** @param array<string, mixed> $value */
    public function set(string $key, array $value, int $ttl = 300): void;

    public function invalidate(string $key): void;
}

<?php

declare(strict_types=1);

namespace App\Configuration\Domain\Repository;

interface ConfigurationRepository
{
    /** @return array<string, mixed>|null */
    public function find(string $key): ?array;

    /** @param array<string, mixed> $value */
    public function save(string $key, array $value, string $actorId): void;
}

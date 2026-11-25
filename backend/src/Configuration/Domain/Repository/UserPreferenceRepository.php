<?php

declare(strict_types=1);

namespace App\Configuration\Domain\Repository;

interface UserPreferenceRepository
{
    /** @return array<string, mixed> */
    public function findForUser(string $userId): array;

    /** @param array<string, mixed> $preferences */
    public function saveForUser(string $userId, array $preferences): void;
}

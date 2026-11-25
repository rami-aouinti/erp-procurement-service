<?php

declare(strict_types=1);

namespace App\Configuration\Infrastructure\Persistence\Doctrine;

use App\Configuration\Domain\Repository\UserPreferenceRepository;
use Doctrine\DBAL\Connection;
use JsonException;

final readonly class OracleUserPreferenceRepository implements UserPreferenceRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function findForUser(string $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT preference_key, preference_value FROM app_user_preferences WHERE user_id = :userId',
            ['userId' => $userId],
        );
        $preferences = [];
        foreach ($rows as $row) {
            $value = $row['PREFERENCE_VALUE'] ?? $row['preference_value'] ?? null;
            if (is_resource($value)) {
                $value = stream_get_contents($value);
            }
            if (!is_string($value)) {
                continue;
            }
            try {
                $preferences[$row['PREFERENCE_KEY'] ?? $row['preference_key']] = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }
        }

        return $preferences;
    }

    public function saveForUser(string $userId, array $preferences): void
    {
        foreach ($preferences as $key => $value) {
            $this->connection->executeStatement(
                <<<'SQL'
                    MERGE INTO app_user_preferences target
                    USING (
                        SELECT :userId AS user_id, :preferenceKey AS preference_key FROM dual
                    ) source
                    ON (target.user_id = source.user_id AND target.preference_key = source.preference_key)
                    WHEN MATCHED THEN UPDATE SET
                        target.preference_value = TO_CLOB(:preferenceValue),
                        target.updated_at = SYSTIMESTAMP
                    WHEN NOT MATCHED THEN INSERT
                        (user_id, preference_key, preference_value, updated_at)
                        VALUES (:userId, :preferenceKey, TO_CLOB(:preferenceValue), SYSTIMESTAMP)
                    SQL,
                [
                    'userId' => $userId,
                    'preferenceKey' => (string) $key,
                    'preferenceValue' => json_encode($value, JSON_THROW_ON_ERROR),
                ],
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Configuration\Infrastructure\Persistence\Doctrine;

use App\Configuration\Domain\Repository\ConfigurationRepository;
use Doctrine\DBAL\Connection;
use JsonException;

final readonly class OracleConfigurationRepository implements ConfigurationRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $key): ?array
    {
        $value = $this->connection->fetchOne(
            'SELECT setting_value FROM app_configuration_settings WHERE setting_key = :settingKey',
            ['settingKey' => $key],
        );
        if ($value === false || $value === null) {
            return null;
        }
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        if (!is_string($value)) {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function save(string $key, array $value, string $actorId): void
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR);
        $this->connection->executeStatement(
            <<<'SQL'
                MERGE INTO app_configuration_settings target
                USING (SELECT :settingKey AS setting_key FROM dual) source
                ON (target.setting_key = source.setting_key)
                WHEN MATCHED THEN UPDATE SET
                    target.setting_value = TO_CLOB(:settingValue),
                    target.updated_by = :updatedBy,
                    target.updated_at = SYSTIMESTAMP,
                    target.version = target.version + 1
                WHEN NOT MATCHED THEN INSERT
                    (setting_key, setting_value, updated_by, updated_at, version)
                    VALUES (:settingKey, TO_CLOB(:settingValue), :updatedBy, SYSTIMESTAMP, 1)
                SQL,
            ['settingKey' => $key, 'settingValue' => $json, 'updatedBy' => $actorId],
        );
    }
}

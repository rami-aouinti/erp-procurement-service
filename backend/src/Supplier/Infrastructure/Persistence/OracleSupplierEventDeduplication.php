<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Persistence;

use App\Supplier\Application\Port\SupplierEventDeduplication;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use RuntimeException;

final readonly class OracleSupplierEventDeduplication implements SupplierEventDeduplication
{
    private const LEASE_SECONDS = 300;

    public function __construct(private Connection $connection)
    {
    }

    public function claim(string $eventId, string $eventType, int $aggregateId): ?string
    {
        $claimToken = bin2hex(random_bytes(16));

        try {
            $this->connection->executeStatement(
                "INSERT INTO supplier_consumed_events
                    (event_id, event_type, aggregate_id, status, attempts, received_at, locked_until, claim_token)
                 VALUES
                    (:eventId, :eventType, :aggregateId, 'PROCESSING', 1, SYSTIMESTAMP,
                     SYSTIMESTAMP + NUMTODSINTERVAL(" . self::LEASE_SECONDS . ", 'SECOND'), :claimToken)",
                [
                    'eventId' => $eventId,
                    'eventType' => $eventType,
                    'aggregateId' => $aggregateId,
                    'claimToken' => $claimToken,
                ],
            );

            return $claimToken;
        } catch (UniqueConstraintViolationException) {
            // The event was seen before. Reclaim it only if it was released
            // after a failure or its previous worker lease has expired.
        }

        $claimed = $this->connection->executeStatement(
            "UPDATE supplier_consumed_events
             SET status = 'PROCESSING',
                 attempts = attempts + 1,
                 locked_until = SYSTIMESTAMP + NUMTODSINTERVAL(" . self::LEASE_SECONDS . ", 'SECOND'),
                 claim_token = :claimToken,
                 last_error = NULL
             WHERE event_id = :eventId
               AND (
                   status = 'PENDING'
                   OR (status = 'PROCESSING' AND locked_until < SYSTIMESTAMP)
               )",
            [
                'eventId' => $eventId,
                'claimToken' => $claimToken,
            ],
        );

        if ($claimed > 0) {
            return $claimToken;
        }

        $existing = $this->connection->fetchAssociative(
            'SELECT status AS "status", event_type AS "event_type", aggregate_id AS "aggregate_id"
             FROM supplier_consumed_events
             WHERE event_id = :eventId',
            ['eventId' => $eventId],
        );

        if ($existing === false) {
            throw new RuntimeException('Supplier event disappeared while claiming it for processing.');
        }

        if (
            (string) $existing['event_type'] !== $eventType
            || (int) $existing['aggregate_id'] !== $aggregateId
        ) {
            throw new RuntimeException('Supplier event ID was reused with different event metadata.');
        }

        $status = (string) $existing['status'];

        if (in_array($status, ['PROCESSED', 'PROCESSING'], true)) {
            // A second delivery must not exhaust the short RabbitMQ retry
            // cycle while the original, still-unacked delivery is processing.
            // If that worker crashes, RabbitMQ requeues its original delivery.
            return null;
        }

        throw new RuntimeException('Supplier event could not be claimed for processing.');
    }

    public function markProcessed(string $eventId, string $claimToken): void
    {
        $updated = $this->connection->executeStatement(
            "UPDATE supplier_consumed_events
             SET status = 'PROCESSED',
                 processed_at = SYSTIMESTAMP,
                 locked_until = NULL,
                 claim_token = NULL,
                 last_error = NULL
             WHERE event_id = :eventId
               AND status = 'PROCESSING'
               AND claim_token = :claimToken",
            [
                'eventId' => $eventId,
                'claimToken' => $claimToken,
            ],
        );

        if ($updated !== 1) {
            throw new RuntimeException('Supplier event processing claim was lost before completion.');
        }
    }

    public function release(string $eventId, string $claimToken, string $errorClass): void
    {
        $updated = $this->connection->executeStatement(
            "UPDATE supplier_consumed_events
             SET status = 'PENDING',
                 locked_until = NULL,
                 claim_token = NULL,
                 last_error = :lastError
             WHERE event_id = :eventId
               AND status = 'PROCESSING'
               AND claim_token = :claimToken",
            [
                'eventId' => $eventId,
                'claimToken' => $claimToken,
                'lastError' => substr($errorClass, 0, 500),
            ],
        );

        if ($updated !== 1) {
            throw new RuntimeException('Supplier event processing claim could not be released.');
        }
    }
}

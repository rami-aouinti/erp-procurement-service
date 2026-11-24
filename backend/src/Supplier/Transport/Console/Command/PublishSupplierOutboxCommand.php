<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Console\Command;

use App\Observability\MetricsRegistry;
use App\Supplier\Infrastructure\Messaging\RabbitMqSupplierEventBus;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'supplier:outbox:publish', description: 'Publish pending supplier outbox events to RabbitMQ.')]
final class PublishSupplierOutboxCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RabbitMqSupplierEventBus $eventBus,
        private readonly MetricsRegistry $metrics,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum events to publish per run.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, min(1000, (int) $input->getOption('limit')));
        $published = 0;

        // Recover claims left behind by a worker that crashed after claiming an event.
        $this->connection->executeStatement(
            "UPDATE supplier_outbox_events SET status = 'PENDING', locked_until = NULL WHERE status = 'PROCESSING' AND locked_until < SYSTIMESTAMP"
        );
        $this->refreshPendingMetric();

        for ($i = 0; $i < $limit; ++$i) {
            $this->connection->beginTransaction();
            try {
                $event = $this->connection->executeQuery(
                    "SELECT event_id, event_type, aggregate_id, payload
                     FROM supplier_outbox_events
                     WHERE event_id = (
                         SELECT event_id
                         FROM (
                             SELECT event_id
                             FROM supplier_outbox_events
                             WHERE status = 'PENDING' AND next_attempt_at <= SYSTIMESTAMP
                             ORDER BY occurred_at
                         )
                         WHERE ROWNUM = 1
                     )
                     FOR UPDATE SKIP LOCKED"
                )->fetchAssociative();

                if ($event === false) {
                    $this->connection->commit();
                    break;
                }

                $this->connection->executeStatement(
                    "UPDATE supplier_outbox_events
                     SET status = 'PROCESSING', locked_until = SYSTIMESTAMP + INTERVAL '60' SECOND
                     WHERE event_id = :eventId",
                    ['eventId' => $event['EVENT_ID'] ?? $event['event_id']]
                );
                $this->connection->commit();
            } catch (Throwable $exception) {
                if ($this->connection->isTransactionActive()) {
                    $this->connection->rollBack();
                }
                throw $exception;
            }

            $eventId = (string) ($event['EVENT_ID'] ?? $event['event_id']);
            try {
                $payload = $event['PAYLOAD'] ?? $event['payload'];
                if (is_resource($payload)) {
                    $payload = stream_get_contents($payload);
                }
                $this->eventBus->publish([
                    'eventId' => $eventId,
                    'eventType' => (string) ($event['EVENT_TYPE'] ?? $event['event_type']),
                    'aggregateId' => (int) ($event['AGGREGATE_ID'] ?? $event['aggregate_id']),
                    'payload' => json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR),
                ]);
                if ($this->connection->executeStatement(
                    "UPDATE supplier_outbox_events
                     SET status = 'PUBLISHED', published_at = SYSTIMESTAMP, locked_until = NULL, last_error = NULL
                     WHERE event_id = :eventId
                       AND status = 'PROCESSING'
                       AND locked_until >= SYSTIMESTAMP",
                    ['eventId' => $eventId]
                ) > 0) {
                    ++$published;
                    $this->metrics->incrementSupplierEvent('published');
                }
                $this->refreshPendingMetric();
            } catch (Throwable $exception) {
                $this->connection->executeStatement(
                    "UPDATE supplier_outbox_events
                     SET attempts = attempts + 1,
                         status = CASE WHEN attempts + 1 >= 10 THEN 'FAILED' ELSE 'PENDING' END,
                         next_attempt_at = SYSTIMESTAMP + NUMTODSINTERVAL(LEAST(POWER(2, attempts + 1), 300), 'SECOND'),
                         locked_until = NULL,
                         last_error = :error
                     WHERE event_id = :eventId
                       AND status = 'PROCESSING'
                       AND locked_until >= SYSTIMESTAMP",
                    ['eventId' => $eventId, 'error' => $exception::class]
                );
                $this->refreshPendingMetric();
                $output->writeln(sprintf('<error>Could not publish supplier event %s (%s).</error>', $eventId, $exception::class));
            }
        }

        $output->writeln(sprintf('<info>%d supplier event(s) published.</info>', $published));

        return Command::SUCCESS;
    }
    private function refreshPendingMetric(): void
    {
        try {
            $pending = (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM supplier_outbox_events WHERE status = 'PENDING'"
            );
            $failed = (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM supplier_outbox_events WHERE status = 'FAILED'"
            );
            $oldestPendingAge = (float) $this->connection->fetchOne(
                "SELECT COALESCE((CAST(SYSTIMESTAMP AS DATE) - CAST(MIN(occurred_at) AS DATE)) * 86400, 0)
                 FROM supplier_outbox_events
                 WHERE status = 'PENDING'"
            );
            $this->metrics->setSupplierOutboxPending($pending);
            $this->metrics->setSupplierOutboxFailed($failed);
            $this->metrics->setSupplierOutboxOldestPendingAge($oldestPendingAge);
        } catch (Throwable) {
            // Metrics must never prevent outbox delivery.
        }
    }

}

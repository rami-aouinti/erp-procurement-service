<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Console\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'supplier:outbox:retry-failed', description: 'Requeue failed supplier outbox events for another delivery attempt.')]
final class RetryFailedSupplierOutboxCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('event-id', InputArgument::OPTIONAL, 'Retry only this outbox event ID. Omit to retry all failed events.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $eventId = $input->getArgument('event-id');
        if (is_string($eventId) && $eventId !== '') {
            $count = $this->connection->executeStatement(
                "UPDATE supplier_outbox_events
                 SET status = 'PENDING', attempts = 0, next_attempt_at = SYSTIMESTAMP,
                     locked_until = NULL, last_error = NULL
                 WHERE status = 'FAILED' AND event_id = :eventId",
                ['eventId' => strtoupper($eventId)]
            );
        } else {
            $count = $this->connection->executeStatement(
                "UPDATE supplier_outbox_events
                 SET status = 'PENDING', attempts = 0, next_attempt_at = SYSTIMESTAMP,
                     locked_until = NULL, last_error = NULL
                 WHERE status = 'FAILED'"
            );
        }

        $output->writeln(sprintf('<info>%d failed supplier event(s) returned to the outbox.</info>', $count));

        return Command::SUCCESS;
    }
}

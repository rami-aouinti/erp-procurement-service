<?php

declare(strict_types=1);

namespace App\\Supplier\\Transport\\Console\\Command;

use App\\Supplier\\Infrastructure\\Messaging\\RabbitMqSupplierEventBus;
use RuntimeException;
use Symfony\\Component\\Console\\Attribute\\AsCommand;
use Symfony\\Component\\Console\\Command\\Command;
use Symfony\\Component\\Console\\Input\\InputInterface;
use Symfony\\Component\\Console\\Input\\InputOption;
use Symfony\\Component\\Console\\Output\\OutputInterface;

#[AsCommand(name: 'supplier:events:dead-letter:replay', description: 'Replay supplier events from the RabbitMQ dead-letter queue.')]
final class ReplaySupplierDeadLettersCommand extends Command
{
    public function __construct(private readonly RabbitMqSupplierEventBus $eventBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of messages to replay (1-1000).', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 1000) {
            $output->writeln('<error>The --limit option must be an integer between 1 and 1000.</error>');

            return Command::INVALID;
        }

        try {
            $count = $this->eventBus->replayDeadLetters($limit);
        } catch (RuntimeException $exception) {
            $output->writeln(sprintf('<error>Dead-letter replay failed (%s).</error>', $exception::class));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>%d supplier dead-letter event(s) replayed.</info>', $count));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Console\Command;

use App\Supplier\Application\Service\HandleSupplierEvent;
use App\Supplier\Infrastructure\Messaging\RabbitMqSupplierEventBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'supplier:events:consume', description: 'Consume supplier events and update Redis/Elasticsearch read models.')]
final class ConsumeSupplierEventsCommand extends Command
{
    public function __construct(
        private readonly RabbitMqSupplierEventBus $eventBus,
        private readonly HandleSupplierEvent $handleSupplierEvent,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Supplier event consumer connected. Waiting for messages...</info>');
        $this->eventBus->consume($this->handleSupplierEvent);


        return Command::SUCCESS;
    }
}

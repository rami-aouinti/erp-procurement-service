<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Console\Command;

use App\Supplier\Application\Service\SupplierReadModelSynchronizer;
use App\Supplier\Domain\Repository\SupplierRepository;
use App\Supplier\Infrastructure\Messaging\RabbitMqSupplierEventBus;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'supplier:events:consume', description: 'Consume supplier events and update Redis/Elasticsearch read models.')]
final class ConsumeSupplierEventsCommand extends Command
{
    public function __construct(
        private readonly RabbitMqSupplierEventBus $eventBus,
        private readonly SupplierReadModelSynchronizer $readModel,
        private readonly SupplierRepository $suppliers,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Supplier event consumer connected. Waiting for messages...</info>');
        $this->eventBus->consume(function (array $event): void {
            $type = (string) $event['eventType'];
            $supplierId = (int) $event['aggregateId'];

            if ($type === 'SupplierDeleted') {
                $this->readModel->remove($supplierId);
                return;
            }

            if (!in_array($type, ['SupplierCreated', 'SupplierUpdated'], true)) {
                throw new RuntimeException(sprintf('Unsupported supplier event type "%s".', $type));
            }

            // Always reload the current Oracle state. Delayed or duplicated events
            // therefore cannot overwrite newer supplier data or resurrect deletions.
            $supplier = $this->suppliers->findById($supplierId);
            if ($supplier === null) {
                $this->readModel->remove($supplierId);
                return;
            }

            $this->readModel->upsert($supplier);
        });

        return Command::SUCCESS;
    }
}

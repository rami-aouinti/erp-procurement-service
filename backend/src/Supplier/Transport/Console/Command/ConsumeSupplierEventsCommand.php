<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Console\Command;

use App\Observability\MetricsRegistry;
use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
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
        private readonly SupplierRepository $suppliers,
        private readonly SupplierSearchIndex $searchIndex,
        private readonly SupplierListCache $cache,
        private readonly MetricsRegistry $metrics,
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
                    $this->searchIndex->delete($supplierId);
                    $this->cache->invalidate();
                } else {
                    if (!in_array($type, ['SupplierCreated', 'SupplierUpdated'], true)) {
                        throw new RuntimeException(sprintf('Unsupported supplier event type "%s".', $type));
                    }

                    // Reload current Oracle state: duplicates and delayed messages cannot
                    // overwrite newer data or recreate a supplier that has been deleted.
                    $supplier = $this->suppliers->findById($supplierId);
                    if ($supplier === null) {
                        $this->searchIndex->delete($supplierId);
                    } else {
                        $this->searchIndex->index($supplier);
                    }
                    $this->cache->invalidate();
                }

                $this->metrics->incrementSupplierEvent('consumed');
            } catch (\Throwable $exception) {
                throw $exception;
            }
        });

        return Command::SUCCESS;
    }
}

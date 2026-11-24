<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Console\Command;

use App\Supplier\Application\Service\SupplierReadModelSynchronizer;
use App\Supplier\Domain\Model\Supplier;
use App\Supplier\Infrastructure\Messaging\RabbitMqSupplierEventBus;
use DateTimeImmutable;
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
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Supplier event consumer connected. Waiting for messages...</info>');
        $this->eventBus->consume(function (array $event): void {
            $type = (string) $event['eventType'];
            $payload = $event['payload'];

            if ($type === 'SupplierDeleted') {
                $this->readModel->remove((int) $event['aggregateId']);
                return;
            }

            if (!in_array($type, ['SupplierCreated', 'SupplierUpdated'], true)) {
                throw new RuntimeException(sprintf('Unsupported supplier event type "%s".', $type));
            }

            $createdAt = new DateTimeImmutable((string) ($payload['createdAt'] ?? 'now'));
            $supplier = Supplier::reconstitute(
                (int) $payload['id'],
                (string) $payload['name'],
                isset($payload['email']) ? (string) $payload['email'] : null,
                isset($payload['phone']) ? (string) $payload['phone'] : null,
                isset($payload['taxNumber']) ? (string) $payload['taxNumber'] : null,
                $createdAt,
                (string) ($payload['status'] ?? 'ACTIVE'),
                isset($payload['contactPerson']) ? (string) $payload['contactPerson'] : null,
                isset($payload['street']) ? (string) $payload['street'] : null,
                isset($payload['city']) ? (string) $payload['city'] : null,
                isset($payload['postalCode']) ? (string) $payload['postalCode'] : null,
                isset($payload['country']) ? (string) $payload['country'] : null,
            );
            $this->readModel->upsert($supplier);
        });

        return Command::SUCCESS;
    }
}

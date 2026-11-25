<?php

declare(strict_types=1);

namespace App\Command;

use App\Supplier\Application\UseCase\ManageSupplierBankAccounts;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:fixtures:supplier-bank-accounts',
    description: 'Create demo bank accounts using the configured AES-256-GCM encryption service.',
)]
final class SeedSupplierBankAccountFixturesCommand extends Command
{
    private const FIXTURES = [
        ['purchasing@nordtech.example', 'NordTech Components GmbH', 'DE62370400440532013001'],
        ['orders@hanseatic-office.example', 'Hanseatic Office Supplies GmbH', 'DE35370400440532013002'],
        ['procurement@global-parts.example', 'Global Industrial Parts AG', 'DE08370400440532013003'],
        ['sales@elbe-tools.example', 'Elbe Logistics & Tools GmbH', 'DE78370400440532013004'],
        ['orders@weser-electronics.example', 'Weser Electronics GmbH', 'DE51370400440532013005'],
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ManageSupplierBankAccounts $accounts,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $created = 0;

        foreach (self::FIXTURES as [$email, $holder, $iban]) {
            $supplierId = $this->connection->fetchOne(
                'SELECT id FROM suppliers WHERE email = :email',
                ['email' => $email],
            );

            if ($supplierId === false || $supplierId === null) {
                $output->writeln(sprintf('<comment>Skipped missing supplier %s</comment>', $email));
                continue;
            }

            $existing = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM supplier_bank_accounts WHERE supplier_id = :supplierId',
                ['supplierId' => (int) $supplierId],
            );
            if ($existing > 0) {
                $output->writeln(sprintf('<comment>Bank account already exists for %s</comment>', $email));
                continue;
            }

            $this->accounts->add(
                (int) $supplierId,
                $holder,
                $iban,
                'COBADEFFXXX',
                'Commerzbank Demo',
                'EUR',
                true,
            );
            ++$created;
        }

        $output->writeln(sprintf('<info>Created %d encrypted demo bank account(s).</info>', $created));

        return Command::SUCCESS;
    }
}

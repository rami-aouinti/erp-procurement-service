<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Console;

use App\Supplier\Application\UseCase\ManageSupplierBankAccounts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'supplier:bank-accounts:rotate-encryption-key',
    description: 'Re-encrypt a supplier bank accounts with the active encryption key.',
)]
final class RotateSupplierBankEncryptionCommand extends Command
{
    public function __construct(private readonly ManageSupplierBankAccounts $accounts)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('supplier-id', InputArgument::REQUIRED, 'Supplier ID whose bank accounts should be re-encrypted.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $supplierId = filter_var($input->getArgument('supplier-id'), FILTER_VALIDATE_INT);
        if ($supplierId === false || $supplierId < 1) {
            $output->writeln('<error>Supplier ID must be a positive integer.</error>');

            return Command::INVALID;
        }

        try {
            $rotated = $this->accounts->rotateEncryptionKey($supplierId);
        } catch (SupplierNotFound) {
            $output->writeln(sprintf('<error>Supplier %d was not found.</error>', $supplierId));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('Re-encrypted %d bank account(s) for supplier %d.', $rotated, $supplierId));

        return Command::SUCCESS;
    }
}

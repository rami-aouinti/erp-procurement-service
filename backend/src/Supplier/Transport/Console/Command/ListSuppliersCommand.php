<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Console\Command;

use App\Supplier\Application\UseCase\ListSuppliers;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'supplier:list',
    description: 'List all suppliers.',
)]
final class ListSuppliersCommand extends Command
{
    public function __construct(private readonly ListSuppliers $listSuppliers)
    {
        parent::__construct();
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $suppliers = $this->listSuppliers->execute();

        if ($suppliers === []) {
            $io->info('No suppliers found.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($suppliers as $supplier) {
            $rows[] = [
                $supplier->id(),
                $supplier->name(),
                $supplier->email() ?? '-',
                $supplier->phone() ?? '-',
                $supplier->taxNumber() ?? '-',
                $supplier->createdAt()->format('Y-m-d H:i'),
            ];
        }

        $io->title(sprintf('Suppliers (%d)', count($suppliers)));
        $io->table(['ID', 'Name', 'Email', 'Phone', 'Tax number', 'Created at'], $rows);

        return Command::SUCCESS;
    }
}

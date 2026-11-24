<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Console\Command;

use App\Supplier\Application\DTO\SupplierData;
use App\Supplier\Application\Exception\SupplierValidationException;
use App\Supplier\Application\UseCase\CreateSupplier;
use App\Supplier\Application\Validator\SupplierDataValidator;
use App\Supplier\Domain\Exception\SupplierEmailAlreadyUsed;
use App\Supplier\Domain\Exception\InvalidSupplierData;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'supplier:create',
    description: 'Create a supplier.',
)]
final class CreateSupplierCommand extends Command
{
    public function __construct(
        private readonly CreateSupplier $createSupplier,
        private readonly SupplierDataValidator $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Supplier name')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Supplier email')
            ->addOption('phone', null, InputOption::VALUE_REQUIRED, 'Supplier phone')
            ->addOption('tax-number', null, InputOption::VALUE_REQUIRED, 'Supplier tax number');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $data = new SupplierData(
            (string) $input->getArgument('name'),
            $input->getOption('email') !== null ? (string) $input->getOption('email') : null,
            $input->getOption('phone') !== null ? (string) $input->getOption('phone') : null,
            $input->getOption('tax-number') !== null ? (string) $input->getOption('tax-number') : null,
        );

        try {
            $this->validator->validate($data);
            $supplier = $this->createSupplier->execute($data);
        } catch (SupplierValidationException|InvalidSupplierData|SupplierEmailAlreadyUsed $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Supplier "%s" created with ID %d.', $supplier->name(), $supplier->id()));

        return Command::SUCCESS;
    }
}

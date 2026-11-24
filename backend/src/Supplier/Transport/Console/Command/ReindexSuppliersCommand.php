<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Console\Command;

use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Domain\Repository\SupplierRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'supplier:reindex', description: 'Rebuild the Elasticsearch supplier index from Oracle.')]
final class ReindexSuppliersCommand extends Command
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly SupplierSearchIndex $searchIndex,
        private readonly SupplierListCache $cache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->searchIndex->reindex($this->suppliers->findAll());
        $this->cache->invalidate();
        $output->writeln(sprintf('<info>%d suppliers indexed successfully.</info>', $count));

        return Command::SUCCESS;
    }
}

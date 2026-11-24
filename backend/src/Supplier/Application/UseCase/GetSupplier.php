<?php
declare(strict_types=1);
namespace App\Supplier\Application\UseCase;
use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\Supplier;
use App\Supplier\Domain\Repository\SupplierRepository;
final readonly class GetSupplier
{
    public function __construct(private SupplierRepository $suppliers) {}
    public function execute(int $id): Supplier
    {
        return $this->suppliers->findById($id) ?? throw SupplierNotFound::withId($id);
    }
}

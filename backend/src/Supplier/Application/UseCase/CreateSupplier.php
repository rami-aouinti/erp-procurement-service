<?php
declare(strict_types=1);
namespace App\Supplier\Application\UseCase;
use App\Supplier\Application\DTO\SupplierData;
use App\Supplier\Domain\Exception\SupplierEmailAlreadyUsed;
use App\Supplier\Domain\Model\Supplier;
use App\Supplier\Domain\Repository\SupplierRepository;
final readonly class CreateSupplier
{
    public function __construct(private SupplierRepository $suppliers) {}
    public function execute(SupplierData $data): Supplier
    {
        if ($data->email !== null && $this->suppliers->emailExists(trim($data->email))) {
            throw new SupplierEmailAlreadyUsed('A supplier with this email already exists.');
        }
        return $this->suppliers->save(Supplier::register($data->name, $data->email, $data->phone, $data->taxNumber));
    }
}

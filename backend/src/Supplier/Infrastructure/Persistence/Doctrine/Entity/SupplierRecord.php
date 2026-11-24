<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Persistence\Doctrine\Entity;

use App\Supplier\Domain\Model\Supplier;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'suppliers')]
#[ORM\UniqueConstraint(name: 'uq_suppliers_email', columns: ['email'])]
class SupplierRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'SUPPLIERS_SEQ', allocationSize: 1)]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 150)]
    private string $name;

    #[ORM\Column(name: 'email', length: 150, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'phone', length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'tax_number', length: 50, nullable: true)]
    private ?string $taxNumber = null;

    #[ORM\Column(name: 'status', length: 20, options: ['default' => 'ACTIVE'])]
    private string $status = 'ACTIVE';

    #[ORM\Column(name: 'contact_person', length: 150, nullable: true)]
    private ?string $contactPerson = null;

    #[ORM\Column(name: 'street', length: 180, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(name: 'city', length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(name: 'postal_code', length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(name: 'country', length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(Supplier $supplier)
    {
        $this->createdAt = $supplier->createdAt();
        $this->updateFrom($supplier);
    }

    public function id(): ?int { return $this->id; }
    public function name(): string { return $this->name; }
    public function email(): ?string { return $this->email; }
    public function phone(): ?string { return $this->phone; }
    public function taxNumber(): ?string { return $this->taxNumber; }
    public function status(): string { return $this->status; }
    public function contactPerson(): ?string { return $this->contactPerson; }
    public function street(): ?string { return $this->street; }
    public function city(): ?string { return $this->city; }
    public function postalCode(): ?string { return $this->postalCode; }
    public function country(): ?string { return $this->country; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }

    public function updateFrom(Supplier $supplier): void
    {
        $this->name = $supplier->name();
        $this->email = $supplier->email();
        $this->phone = $supplier->phone();
        $this->taxNumber = $supplier->taxNumber();
        $this->status = $supplier->status();
        $this->contactPerson = $supplier->contactPerson();
        $this->street = $supplier->street();
        $this->city = $supplier->city();
        $this->postalCode = $supplier->postalCode();
        $this->country = $supplier->country();
    }
}

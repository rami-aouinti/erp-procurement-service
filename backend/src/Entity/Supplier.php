<?php

namespace App\Entity;

use App\Repository\SupplierRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
#[ORM\Table(name: 'suppliers')]
#[ORM\UniqueConstraint(name: 'uq_suppliers_email', columns: ['email'])]
class Supplier
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 150)]
    private string $name;

    #[ORM\Column(name: 'email', length: 150, nullable: true, unique: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'phone', length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'tax_number', length: 50, nullable: true)]
    private ?string $taxNumber = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, ?string $email = null, ?string $phone = null, ?string $taxNumber = null)
    {
        $this->rename($name);
        $this->changeContactDetails($email, $phone, $taxNumber);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): ?string { return $this->email; }
    public function getPhone(): ?string { return $this->phone; }
    public function getTaxNumber(): ?string { return $this->taxNumber; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Supplier name is required.');
        }
        if (mb_strlen($name) > 150) {
            throw new \InvalidArgumentException('Supplier name must not exceed 150 characters.');
        }
        $this->name = $name;
    }

    public function changeContactDetails(?string $email, ?string $phone, ?string $taxNumber): void
    {
        $email = $email !== null ? trim($email) : null;
        $phone = $phone !== null ? trim($phone) : null;
        $taxNumber = $taxNumber !== null ? trim($taxNumber) : null;

        if ($email === '') $email = null;
        if ($phone === '') $phone = null;
        if ($taxNumber === '') $taxNumber = null;

        if ($email !== null && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) {
            throw new \InvalidArgumentException('A valid supplier email (max. 150 characters) is required.');
        }
        if ($phone !== null && mb_strlen($phone) > 30) {
            throw new \InvalidArgumentException('Phone must not exceed 30 characters.');
        }
        if ($taxNumber !== null && mb_strlen($taxNumber) > 50) {
            throw new \InvalidArgumentException('Tax number must not exceed 50 characters.');
        }

        $this->email = $email;
        $this->phone = $phone;
        $this->taxNumber = $taxNumber;
    }
}

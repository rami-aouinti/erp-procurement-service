<?php
declare(strict_types=1);
namespace App\Supplier\Infrastructure\Persistence\Doctrine\Entity;
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
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;
    public function __construct(string $name, ?string $email, ?string $phone, ?string $taxNumber, \DateTimeImmutable $createdAt)
    {
        $this->name = $name; $this->email = $email; $this->phone = $phone;
        $this->taxNumber = $taxNumber; $this->createdAt = $createdAt;
    }
    public function id(): ?int { return $this->id; }
    public function name(): string { return $this->name; }
    public function email(): ?string { return $this->email; }
    public function phone(): ?string { return $this->phone; }
    public function taxNumber(): ?string { return $this->taxNumber; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updateFrom(\App\Supplier\Domain\Model\Supplier $supplier): void
    {
        $this->name = $supplier->name(); $this->email = $supplier->email();
        $this->phone = $supplier->phone(); $this->taxNumber = $supplier->taxNumber();
    }
}

<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Model;

use App\Supplier\Domain\Exception\InvalidSupplierData;

final class Supplier
{
    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $email,
        private ?string $phone,
        private ?string $taxNumber,
        private \DateTimeImmutable $createdAt,
    ) {
        $this->name = self::normalizeName($name);
        [$this->email, $this->phone, $this->taxNumber] = self::normalizeContact($email, $phone, $taxNumber);
    }

    public static function register(string $name, ?string $email, ?string $phone, ?string $taxNumber, ?\DateTimeImmutable $createdAt = null): self
    {
        return new self(null, $name, $email, $phone, $taxNumber, $createdAt ?? new \DateTimeImmutable());
    }

    public static function reconstitute(int $id, string $name, ?string $email, ?string $phone, ?string $taxNumber, \DateTimeImmutable $createdAt): self
    {
        return new self($id, $name, $email, $phone, $taxNumber, $createdAt);
    }

    public function assignIdentity(int $id): void
    {
        if ($id <= 0 || $this->id !== null) {
            throw new \LogicException('Supplier identity can only be assigned once.');
        }
        $this->id = $id;
    }

    public function update(string $name, ?string $email, ?string $phone, ?string $taxNumber): void
    {
        $this->name = self::normalizeName($name);
        [$this->email, $this->phone, $this->taxNumber] = self::normalizeContact($email, $phone, $taxNumber);
    }

    public function id(): ?int { return $this->id; }
    public function name(): string { return $this->name; }
    public function email(): ?string { return $this->email; }
    public function phone(): ?string { return $this->phone; }
    public function taxNumber(): ?string { return $this->taxNumber; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 150) {
            throw new InvalidSupplierData('Supplier name is required and must not exceed 150 characters.');
        }
        return $name;
    }

    /** @return array{?string, ?string, ?string} */
    private static function normalizeContact(?string $email, ?string $phone, ?string $taxNumber): array
    {
        $email = self::nullableTrim($email);
        $phone = self::nullableTrim($phone);
        $taxNumber = self::nullableTrim($taxNumber);
        if ($email !== null && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) {
            throw new InvalidSupplierData('A valid supplier email of at most 150 characters is required.');
        }
        if ($phone !== null && mb_strlen($phone) > 30) {
            throw new InvalidSupplierData('Supplier phone must not exceed 30 characters.');
        }
        if ($taxNumber !== null && mb_strlen($taxNumber) > 50) {
            throw new InvalidSupplierData('Supplier tax number must not exceed 50 characters.');
        }
        return [$email, $phone, $taxNumber];
    }

    private static function nullableTrim(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;
        return $value === '' ? null : $value;
    }
}

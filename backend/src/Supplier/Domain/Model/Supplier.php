<?php

declare(strict_types=1);

namespace App\\Supplier\\Domain\\Model;

use App\\Supplier\\Domain\\Exception\\InvalidSupplierData;

final class Supplier
{
    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $email,
        private ?string $phone,
        private ?string $taxNumber,
        private \\DateTimeImmutable $createdAt,
        private string $status = 'ACTIVE',
        private ?string $contactPerson = null,
        private ?string $street = null,
        private ?string $city = null,
        private ?string $postalCode = null,
        private ?string $country = null,
    ) {
        $this->name = self::required($name, 150, 'Supplier name');
        $this->email = self::optional($email, 150, 'Supplier email');
        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidSupplierData('Supplier email must be valid.');
        }
        $this->phone = self::optional($phone, 30, 'Supplier phone');
        $this->taxNumber = self::optional($taxNumber, 50, 'Supplier tax number');
        $this->status = strtoupper(trim($status));
        if (!in_array($this->status, ['ACTIVE', 'INACTIVE', 'BLOCKED'], true)) {
            throw new InvalidSupplierData('Supplier status must be ACTIVE, INACTIVE or BLOCKED.');
        }
        $this->contactPerson = self::optional($contactPerson, 150, 'Contact person');
        $this->street = self::optional($street, 180, 'Street');
        $this->city = self::optional($city, 100, 'City');
        $this->postalCode = self::optional($postalCode, 20, 'Postal code');
        $this->country = self::optional($country, 2, 'Country code');
        if ($this->country !== null) {
            $this->country = strtoupper($this->country);
        }
    }

    public static function register(
        string $name,
        ?string $email,
        ?string $phone,
        ?string $taxNumber,
        ?\\DateTimeImmutable $createdAt = null,
        string $status = 'ACTIVE',
        ?string $contactPerson = null,
        ?string $street = null,
        ?string $city = null,
        ?string $postalCode = null,
        ?string $country = null,
    ): self {
        return new self(null, $name, $email, $phone, $taxNumber, $createdAt ?? new \\DateTimeImmutable(), $status, $contactPerson, $street, $city, $postalCode, $country);
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?string $email,
        ?string $phone,
        ?string $taxNumber,
        \\DateTimeImmutable $createdAt,
        string $status = 'ACTIVE',
        ?string $contactPerson = null,
        ?string $street = null,
        ?string $city = null,
        ?string $postalCode = null,
        ?string $country = null,
    ): self {
        return new self($id, $name, $email, $phone, $taxNumber, $createdAt, $status, $contactPerson, $street, $city, $postalCode, $country);
    }

    public function update(
        string $name,
        ?string $email,
        ?string $phone,
        ?string $taxNumber,
        string $status = 'ACTIVE',
        ?string $contactPerson = null,
        ?string $street = null,
        ?string $city = null,
        ?string $postalCode = null,
        ?string $country = null,
    ): void {
        $updated = new self($this->id, $name, $email, $phone, $taxNumber, $this->createdAt, $status, $contactPerson, $street, $city, $postalCode, $country);
        $this->name = $updated->name;
        $this->email = $updated->email;
        $this->phone = $updated->phone;
        $this->taxNumber = $updated->taxNumber;
        $this->status = $updated->status;
        $this->contactPerson = $updated->contactPerson;
        $this->street = $updated->street;
        $this->city = $updated->city;
        $this->postalCode = $updated->postalCode;
        $this->country = $updated->country;
    }

    public function id(): ?int { return $this->id; }
    public function name(): string { return $this->name; }
    public function email(): ?string { return $this->email; }
    public function phone(): ?string { return $this->phone; }
    public function taxNumber(): ?string { return $this->taxNumber; }
    public function createdAt(): \\DateTimeImmutable { return $this->createdAt; }
    public function status(): string { return $this->status; }
    public function contactPerson(): ?string { return $this->contactPerson; }
    public function street(): ?string { return $this->street; }
    public function city(): ?string { return $this->city; }
    public function postalCode(): ?string { return $this->postalCode; }
    public function country(): ?string { return $this->country; }

    private static function required(string $value, int $max, string $label): string
    {
        $value = trim($value);
        if ($value === '' || self::length($value) > $max) {
            throw new InvalidSupplierData(sprintf('%s is required and must not exceed %d characters.', $label, $max));
        }
        return $value;
    }

    private static function optional(?string $value, int $max, string $label): ?string
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === '') {
            return null;
        }
        if ($value !== null && self::length($value) > $max) {
            throw new InvalidSupplierData(sprintf('%s must not exceed %d characters.', $label, $max));
        }
        return $value;
    }

    private static function length(string $value): int
    {
        preg_match_all('/./us', $value, $matches);
        return count($matches[0]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Application\Validation;

use App\Supplier\Application\DTO\SupplierData;
use App\Supplier\Application\Exception\SupplierValidationException;
use App\Supplier\Application\Validator\SupplierDataValidator;
use PHPUnit\Framework\TestCase;

final class SupplierDataValidatorTest extends TestCase
{
    private SupplierDataValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SupplierDataValidator();
    }

    public function testAcceptsValidSupplierData(): void
    {
        $this->validator->validate(new SupplierData(
            'Acme GmbH',
            'contact@acme.de',
            '+49 421 123456',
            'DE123456789',
        ));

        self::assertTrue(true);
    }

    public function testRejectsBlankSupplierName(): void
    {
        $this->expectException(SupplierValidationException::class);
        $this->validator->validate(new SupplierData('   '));
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(SupplierValidationException::class);
        $this->validator->validate(new SupplierData('Acme GmbH', 'not-an-email'));
    }

    public function testRejectsInvalidPhoneCountryStatusAndPostalCode(): void
    {
        try {
            $this->validator->validate(new SupplierData(
                'Acme GmbH',
                'valid@acme.de',
                'call-me',
                null,
                'PENDING',
                null,
                null,
                'Berlin',
                '!!',
                'GER',
            ));
            self::fail('Expected SupplierValidationException.');
        } catch (SupplierValidationException $exception) {
            self::assertCount(4, $exception->violations());
        }
    }

    public function testRejectsFieldsExceedingMaximumLength(): void
    {
        try {
            $this->validator->validate(new SupplierData(
                str_repeat('a', 151),
                null,
                str_repeat('1', 31),
                str_repeat('T', 51),
            ));
            self::fail('Expected SupplierValidationException.');
        } catch (SupplierValidationException $exception) {
            self::assertCount(3, $exception->violations());
        }
    }
}

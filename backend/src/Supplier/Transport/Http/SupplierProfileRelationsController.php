<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Http;

use App\Supplier\Application\UseCase\ManageSupplierProfileRelations;
use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\SupplierAddress;
use App\Supplier\Domain\Model\SupplierContact;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/suppliers/{id<\\d+>}')]
final readonly class SupplierProfileRelationsController
{
    public function __construct(private ManageSupplierProfileRelations $relations)
    {
    }

    #[Route('/contacts', name: 'api_supplier_contacts_list', methods: ['GET'])]
    public function contacts(int $id): JsonResponse
    {
        try {
            return new JsonResponse(array_map(self::serializeContact(...), $this->relations->listContacts($id)));
        } catch (SupplierNotFound) {
            return new JsonResponse(['error' => 'Supplier not found.'], 404);
        }
    }

    #[Route('/contacts', name: 'api_supplier_contacts_add', methods: ['POST'])]
    public function addContact(int $id, Request $request): JsonResponse
    {
        try {
            $data = $this->payload($request);
            $contact = new SupplierContact(
                null,
                $id,
                self::string($data, 'contactType', 'GENERAL'),
                self::string($data, 'firstName'),
                self::string($data, 'lastName'),
                self::nullableString($data, 'jobTitle'),
                self::nullableString($data, 'email'),
                self::nullableString($data, 'phone'),
                self::boolean($data, 'primary'),
            );

            return new JsonResponse(self::serializeContact($this->relations->addContact($contact)), 201);
        } catch (SupplierNotFound) {
            return new JsonResponse(['error' => 'Supplier not found.'], 404);
        } catch (JsonException | InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    #[Route('/addresses', name: 'api_supplier_addresses_list', methods: ['GET'])]
    public function addresses(int $id): JsonResponse
    {
        try {
            return new JsonResponse(array_map(self::serializeAddress(...), $this->relations->listAddresses($id)));
        } catch (SupplierNotFound) {
            return new JsonResponse(['error' => 'Supplier not found.'], 404);
        }
    }

    #[Route('/addresses', name: 'api_supplier_addresses_add', methods: ['POST'])]
    public function addAddress(int $id, Request $request): JsonResponse
    {
        try {
            $data = $this->payload($request);
            $address = new SupplierAddress(
                null,
                $id,
                strtoupper(self::string($data, 'addressType')),
                self::nullableString($data, 'label'),
                self::nullableString($data, 'companyName'),
                self::string($data, 'street'),
                self::nullableString($data, 'street2'),
                self::string($data, 'postalCode'),
                self::string($data, 'city'),
                self::nullableString($data, 'region'),
                strtoupper(self::string($data, 'country')),
                self::boolean($data, 'default'),
            );

            return new JsonResponse(self::serializeAddress($this->relations->addAddress($address)), 201);
        } catch (SupplierNotFound) {
            return new JsonResponse(['error' => 'Supplier not found.'], 404);
        } catch (JsonException | InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException('A JSON object is required.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key, ?string $default = null): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Field "%s" is required and must be a string.', $key));
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new InvalidArgumentException(sprintf('Field "%s" must be a string or null.', $key));
        }

        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, mixed> $data */
    private static function boolean(array $data, string $key): bool
    {
        $value = $data[$key] ?? false;
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf('Field "%s" must be a boolean.', $key));
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function serializeContact(SupplierContact $contact): array
    {
        return [
            'id' => $contact->id,
            'supplierId' => $contact->supplierId,
            'contactType' => $contact->contactType,
            'firstName' => $contact->firstName,
            'lastName' => $contact->lastName,
            'jobTitle' => $contact->jobTitle,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'primary' => $contact->primary,
        ];
    }

    /** @return array<string, mixed> */
    private static function serializeAddress(SupplierAddress $address): array
    {
        return [
            'id' => $address->id,
            'supplierId' => $address->supplierId,
            'addressType' => $address->addressType,
            'label' => $address->label,
            'companyName' => $address->companyName,
            'street' => $address->street,
            'street2' => $address->street2,
            'postalCode' => $address->postalCode,
            'city' => $address->city,
            'region' => $address->region,
            'country' => $address->country,
            'default' => $address->default,
        ];
    }
}

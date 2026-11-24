<?php

declare(strict_types=1);

namespace App\\Supplier\\Transport\\Http;

use App\\Supplier\\Application\\DTO\\SupplierData;
use App\\Supplier\\Application\\Exception\\SupplierValidationException;
use App\\Supplier\\Application\\UseCase\\CreateSupplier;
use App\\Supplier\\Application\\UseCase\\DeleteSupplier;
use App\\Supplier\\Application\\UseCase\\GetSupplier;
use App\\Supplier\\Application\\UseCase\\ListSuppliers;
use App\\Supplier\\Application\\UseCase\\UpdateSupplier;
use App\\Supplier\\Domain\\Exception\\InvalidSupplierData;
use App\\Supplier\\Domain\\Exception\\SupplierEmailAlreadyUsed;
use App\\Supplier\\Domain\\Exception\\SupplierNotFound;
use App\\Supplier\\Domain\\Model\\Supplier;
use Doctrine\\DBAL\\Exception\\ForeignKeyConstraintViolationException;
use JsonException;
use Symfony\\Component\\HttpFoundation\\JsonResponse;
use Symfony\\Component\\HttpFoundation\\Request;
use Symfony\\Component\\HttpFoundation\\Response;
use Symfony\\Component\\Routing\\Attribute\\Route;

#[Route('/api/suppliers')]
final class SupplierController
{
    #[Route('', name: 'api_suppliers_list', methods: ['GET'])]
    public function list(Request $request, ListSuppliers $useCase): JsonResponse
    {
        $suppliers = $useCase->execute();
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        $status = strtoupper(trim((string) $request->query->get('status', '')));
        if ($search !== '') {
            $suppliers = array_values(array_filter($suppliers, static fn (Supplier $supplier): bool =>
                str_contains(mb_strtolower($supplier->name()), $search)
                || str_contains(mb_strtolower((string) $supplier->email()), $search)
                || str_contains(mb_strtolower((string) $supplier->city()), $search)
            ));
        }
        if ($status !== '') {
            $suppliers = array_values(array_filter($suppliers, static fn (Supplier $supplier): bool => $supplier->status() === $status));
        }
        return new JsonResponse(array_map($this->serialize(...), $suppliers));
    }

    #[Route('/{id<\\d+>}', name: 'api_suppliers_get', methods: ['GET'])]
    public function get(int $id, GetSupplier $useCase): JsonResponse
    {
        try {
            return new JsonResponse($this->serialize($useCase->execute($id)));
        } catch (SupplierNotFound) {
            return $this->error('Supplier not found.', 404);
        }
    }

    #[Route('', name: 'api_suppliers_create', methods: ['POST'])]
    public function create(Request $request, CreateSupplier $useCase): JsonResponse
    {
        try {
            return new JsonResponse($this->serialize($useCase->execute($this->input($request))), 201);
        } catch (JsonException|InvalidSupplierData|\\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 400);
        } catch (SupplierValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage(), 'violations' => $exception->violations()], 422);
        } catch (SupplierEmailAlreadyUsed $exception) {
            return $this->error($exception->getMessage(), 409);
        }
    }

    #[Route('/{id<\\d+>}', name: 'api_suppliers_update', methods: ['PUT'])]
    public function update(int $id, Request $request, UpdateSupplier $useCase): JsonResponse
    {
        try {
            return new JsonResponse($this->serialize($useCase->execute($id, $this->input($request))));
        } catch (JsonException|InvalidSupplierData|\\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 400);
        } catch (SupplierValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage(), 'violations' => $exception->violations()], 422);
        } catch (SupplierNotFound $exception) {
            return $this->error($exception->getMessage(), 404);
        } catch (SupplierEmailAlreadyUsed $exception) {
            return $this->error($exception->getMessage(), 409);
        }
    }

    #[Route('/{id<\\d+>}', name: 'api_suppliers_delete', methods: ['DELETE'])]
    public function delete(int $id, DeleteSupplier $useCase): Response
    {
        try {
            $useCase->execute($id);
            return new Response('', Response::HTTP_NO_CONTENT);
        } catch (SupplierNotFound $exception) {
            return $this->error($exception->getMessage(), 404);
        } catch (ForeignKeyConstraintViolationException) {
            return $this->error('Supplier cannot be deleted while referenced by purchase orders.', 409);
        }
    }

    private function input(Request $request): SupplierData
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || array_is_list($payload)) {
            throw new \\InvalidArgumentException('A JSON object is required.');
        }
        $fields = ['name', 'email', 'phone', 'taxNumber', 'status', 'contactPerson', 'street', 'city', 'postalCode', 'country'];
        foreach ($fields as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null && !is_string($payload[$field])) {
                throw new \\InvalidArgumentException(sprintf('Field "%s" must be a string or null.', $field));
            }
        }
        if (!isset($payload['name']) || !is_string($payload['name'])) {
            throw new \\InvalidArgumentException('Field "name" is required and must be a string.');
        }
        return new SupplierData(
            $payload['name'], $payload['email'] ?? null, $payload['phone'] ?? null,
            $payload['taxNumber'] ?? null, $payload['status'] ?? 'ACTIVE',
            $payload['contactPerson'] ?? null, $payload['street'] ?? null,
            $payload['city'] ?? null, $payload['postalCode'] ?? null, $payload['country'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    private function serialize(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id(), 'name' => $supplier->name(), 'email' => $supplier->email(),
            'phone' => $supplier->phone(), 'taxNumber' => $supplier->taxNumber(),
            'status' => $supplier->status(), 'contactPerson' => $supplier->contactPerson(),
            'street' => $supplier->street(), 'city' => $supplier->city(),
            'postalCode' => $supplier->postalCode(), 'country' => $supplier->country(),
            'createdAt' => $supplier->createdAt()->format(DATE_ATOM),
        ];
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}

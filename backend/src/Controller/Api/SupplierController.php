<?php

namespace App\Controller\Api;

use App\DTO\Supplier\SupplierInput;
use App\Entity\Supplier;
use App\Service\SupplierService;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/api/suppliers')]
final class SupplierController
{
    public function __construct(private readonly SupplierService $service)
    {
    }

    #[Route('', name: 'api_suppliers_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map($this->serialize(...), $this->service->list()));
    }

    #[Route('/{id<\\d+>}', name: 'api_suppliers_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $supplier = $this->service->get($id);
        return $supplier ? new JsonResponse($this->serialize($supplier)) : $this->notFound();
    }

    #[Route('', name: 'api_suppliers_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $supplier = $this->service->create(SupplierInput::fromArray($this->decode($request)));
            return new JsonResponse($this->serialize($supplier), JsonResponse::HTTP_CREATED);
        } catch (JsonException|\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => 'Unable to create supplier.'], JsonResponse::HTTP_CONFLICT);
        }
    }

    #[Route('/{id<\\d+>}', name: 'api_suppliers_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $supplier = $this->service->get($id);
        if (!$supplier) return $this->notFound();

        try {
            $updated = $this->service->update($supplier, SupplierInput::fromArray($this->decode($request)));
            return new JsonResponse($this->serialize($updated));
        } catch (JsonException|\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => 'Unable to update supplier.'], JsonResponse::HTTP_CONFLICT);
        }
    }

    #[Route('/{id<\\d+>}', name: 'api_suppliers_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $supplier = $this->service->get($id);
        if (!$supplier) return $this->notFound();

        try {
            $this->service->delete($supplier);
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => 'Supplier cannot be deleted because it may be referenced by purchase orders.'], JsonResponse::HTTP_CONFLICT);
        }
    }

    /** @return array<string, mixed> */
    private function decode(Request $request): array
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('A JSON object is required.');
        }
        return $data;
    }

    /** @return array<string, mixed> */
    private function serialize(Supplier $supplier): array
    {
        return [
            'id' => $supplier->getId(),
            'name' => $supplier->getName(),
            'email' => $supplier->getEmail(),
            'phone' => $supplier->getPhone(),
            'taxNumber' => $supplier->getTaxNumber(),
            'createdAt' => $supplier->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(['error' => 'Supplier not found.'], JsonResponse::HTTP_NOT_FOUND);
    }
}

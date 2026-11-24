<?php
declare(strict_types=1);
namespace App\Supplier\Infrastructure\Http;
use App\Supplier\Application\DTO\SupplierData;
use App\Supplier\Application\UseCase\CreateSupplier;
use App\Supplier\Application\UseCase\DeleteSupplier;
use App\Supplier\Application\UseCase\GetSupplier;
use App\Supplier\Application\UseCase\ListSuppliers;
use App\Supplier\Application\UseCase\UpdateSupplier;
use App\Supplier\Domain\Exception\InvalidSupplierData;
use App\Supplier\Domain\Exception\SupplierEmailAlreadyUsed;
use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\Supplier;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/api/suppliers')]
final class SupplierController
{
    #[Route('', name: 'api_suppliers_list', methods: ['GET'])]
    public function list(ListSuppliers $useCase): JsonResponse
    {
        return new JsonResponse(array_map($this->serialize(...), $useCase->execute()));
    }
    #[Route('/{id<\\d+>}', name: 'api_suppliers_get', methods: ['GET'])]
    public function get(int $id, GetSupplier $useCase): JsonResponse
    {
        try { return new JsonResponse($this->serialize($useCase->execute($id))); }
        catch (SupplierNotFound) { return $this->error('Supplier not found.', 404); }
    }
    #[Route('', name: 'api_suppliers_create', methods: ['POST'])]
    public function create(Request $request, CreateSupplier $useCase): JsonResponse
    {
        try { return new JsonResponse($this->serialize($useCase->execute($this->input($request))), 201); }
        catch (JsonException|InvalidSupplierData|\InvalidArgumentException $e) { return $this->error($e->getMessage(), 400); }
        catch (SupplierEmailAlreadyUsed $e) { return $this->error($e->getMessage(), 409); }
    }
    #[Route('/{id<\\d+>}', name: 'api_suppliers_update', methods: ['PUT'])]
    public function update(int $id, Request $request, UpdateSupplier $useCase): JsonResponse
    {
        try { return new JsonResponse($this->serialize($useCase->execute($id, $this->input($request)))); }
        catch (JsonException|InvalidSupplierData|\InvalidArgumentException $e) { return $this->error($e->getMessage(), 400); }
        catch (SupplierNotFound $e) { return $this->error($e->getMessage(), 404); }
        catch (SupplierEmailAlreadyUsed $e) { return $this->error($e->getMessage(), 409); }
    }
    #[Route('/{id<\\d+>}', name: 'api_suppliers_delete', methods: ['DELETE'])]
    public function delete(int $id, DeleteSupplier $useCase): JsonResponse
    {
        try { $useCase->execute($id); return new JsonResponse(null, 204); }
        catch (SupplierNotFound $e) { return $this->error($e->getMessage(), 404); }
        catch (\Throwable) { return $this->error('Supplier cannot be deleted while referenced by purchase orders.', 409); }
    }
    private function input(Request $request): SupplierData
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || array_is_list($payload)) { throw new \InvalidArgumentException('A JSON object is required.'); }
        foreach (['name', 'email', 'phone', 'taxNumber'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null && !is_string($payload[$field])) {
                throw new \InvalidArgumentException(sprintf('Field "%s" must be a string or null.', $field));
            }
        }
        if (!isset($payload['name']) || !is_string($payload['name'])) {
            throw new \InvalidArgumentException('Field "name" is required and must be a string.');
        }
        return new SupplierData($payload['name'], $payload['email'] ?? null, $payload['phone'] ?? null, $payload['taxNumber'] ?? null);
    }
    /** @return array<string, mixed> */
    private function serialize(Supplier $supplier): array
    {
        return ['id' => $supplier->id(), 'name' => $supplier->name(), 'email' => $supplier->email(),
            'phone' => $supplier->phone(), 'taxNumber' => $supplier->taxNumber(), 'createdAt' => $supplier->createdAt()->format(DATE_ATOM)];
    }
    private function error(string $message, int $status): JsonResponse { return new JsonResponse(['error' => $message], $status); }
}

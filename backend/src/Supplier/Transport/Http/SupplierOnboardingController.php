<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Http;

use App\Security\Keycloak\KeycloakRoleGuard;
use App\Supplier\Application\DTO\SupplierData;
use App\Supplier\Application\Exception\SupplierValidationException;
use App\Supplier\Application\UseCase\CreateSupplier;
use App\Supplier\Application\UseCase\ManageSupplierOnboarding;
use App\Supplier\Domain\Exception\InvalidSupplierData;
use App\Supplier\Domain\Exception\SupplierEmailAlreadyUsed;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/supplier-onboarding')]
final readonly class SupplierOnboardingController
{
    public function __construct(
        private ManageSupplierOnboarding $onboarding,
        private CreateSupplier $createSupplier,
        private KeycloakRoleGuard $roles,
    ) {
    }

    #[Route('', name: 'api_supplier_onboarding_create', methods: ['POST'])]
    public function createCandidate(Request $request): JsonResponse
    {
        $this->roles->assertAnyRole('supplier-onboarding-submit', 'supplier-admin');

        try {
            $payload = $this->payload($request);
            if (array_key_exists('status', $payload)) {
                throw new \InvalidArgumentException('Operational status is managed internally and must not be supplied.');
            }
            $allowed = ['name', 'email', 'phone', 'taxNumber', 'contactPerson', 'street', 'city', 'postalCode', 'country'];
            foreach ($payload as $field => $value) {
                if (!in_array($field, $allowed, true)) {
                    throw new \InvalidArgumentException(sprintf('Unknown field "%s".', $field));
                }
                if ($value !== null && !is_string($value)) {
                    throw new \InvalidArgumentException(sprintf('Field "%s" must be a string or null.', $field));
                }
            }
            if (!is_string($payload['name'] ?? null)) {
                throw new \InvalidArgumentException('Field "name" is required and must be a string.');
            }

            $supplier = $this->createSupplier->execute(new SupplierData(
                $payload['name'],
                $payload['email'] ?? null,
                $payload['phone'] ?? null,
                $payload['taxNumber'] ?? null,
                'INACTIVE',
                $payload['contactPerson'] ?? null,
                $payload['street'] ?? null,
                $payload['city'] ?? null,
                $payload['postalCode'] ?? null,
                $payload['country'] ?? null,
            ));
            $state = $this->onboarding->submit((int) $supplier->id(), true);

            return new JsonResponse([
                'supplierId' => $supplier->id(),
                'name' => $supplier->name(),
                'email' => $supplier->email(),
                'operationalStatus' => $supplier->status(),
                'onboardingStatus' => $state['status'],
            ], 201);
        } catch (SupplierValidationException $exception) {
            return new JsonResponse(['error' => 'Supplier validation failed.', 'violations' => $exception->violations()], 422);
        } catch (SupplierEmailAlreadyUsed $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (InvalidSupplierData | \InvalidArgumentException | JsonException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    #[Route('/queue', name: 'api_supplier_onboarding_queue', methods: ['GET'])]
    public function queue(): JsonResponse
    {
        $this->roles->assertAnyRole('supplier-reviewer', 'supplier-admin');
        return new JsonResponse(['items' => $this->onboarding->queue()]);
    }

    #[Route('/{supplierId<\d+>}/submit', name: 'api_supplier_onboarding_submit', methods: ['POST'])]
    public function submit(int $supplierId): JsonResponse
    {
        $this->roles->assertAnyRole('supplier-onboarding-submit', 'supplier-admin');
        return $this->run(fn (): array => $this->onboarding->submit($supplierId), 202);
    }

    #[Route('/{supplierId<\d+>}/approve', name: 'api_supplier_onboarding_approve', methods: ['POST'])]
    public function approve(int $supplierId, Request $request): JsonResponse
    {
        $this->roles->assertAnyRole('supplier-reviewer', 'supplier-admin');
        return $this->run(fn (): array => $this->onboarding->approve($supplierId, $this->reason($request)), 200);
    }

    #[Route('/{supplierId<\d+>}/reject', name: 'api_supplier_onboarding_reject', methods: ['POST'])]
    public function reject(int $supplierId, Request $request): JsonResponse
    {
        $this->roles->assertAnyRole('supplier-reviewer', 'supplier-admin');
        return $this->run(fn (): array => $this->onboarding->reject($supplierId, $this->requiredReason($request)), 200);
    }

    #[Route('/{supplierId<\d+>}/request-changes', name: 'api_supplier_onboarding_request_changes', methods: ['POST'])]
    public function requestChanges(int $supplierId, Request $request): JsonResponse
    {
        $this->roles->assertAnyRole('supplier-reviewer', 'supplier-admin');
        return $this->run(fn (): array => $this->onboarding->requestChanges($supplierId, $this->requiredReason($request)), 200);
    }

    #[Route('/{supplierId<\d+>}/history', name: 'api_supplier_onboarding_history', methods: ['GET'])]
    public function history(int $supplierId): JsonResponse
    {
        $this->roles->assertAnyRole('supplier-reviewer', 'supplier-admin');
        return $this->run(fn (): array => ['items' => $this->onboarding->history($supplierId)], 200);
    }

    private function reason(Request $request): ?string
    {
        $payload = $this->payload($request);
        $reason = $payload['reason'] ?? null;
        if ($reason !== null && !is_string($reason)) {
            throw new \InvalidArgumentException('Reason must be a string.');
        }
        return $reason;
    }

    private function requiredReason(Request $request): string
    {
        $reason = $this->reason($request);
        if ($reason === null || trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required.');
        }
        return $reason;
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        if (trim($request->getContent()) === '') {
            return [];
        }
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || array_is_list($payload)) {
            throw new \InvalidArgumentException('A JSON object is required.');
        }
        return $payload;
    }

    /** @param callable(): array<string, mixed> $operation */
    private function run(callable $operation, int $successStatus): JsonResponse
    {
        try {
            return new JsonResponse($operation(), $successStatus);
        } catch (\OutOfBoundsException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (\DomainException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException | JsonException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }
}

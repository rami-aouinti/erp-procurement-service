<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Http;

use App\Security\Keycloak\KeycloakRoleGuard;
use App\Supplier\Application\Port\SupplierClassificationReader;
use App\Supplier\Application\Service\ManageSupplierClassification;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class SupplierClassificationController
{
    public function __construct(private readonly KeycloakRoleGuard $roles) {}

    #[Route('/supplier-classifications/{type}', name: 'supplier_classifications_list', methods: ['GET'])]
    public function list(string $type, ManageSupplierClassification $manager): JsonResponse
    {
        $this->roles->assertAnyRole('procurement-user','supplier-onboarding-submit','supplier-reviewer','supplier-admin');
        return new JsonResponse($manager->list($type));
    }

    #[Route('/supplier-classifications/{type}', name: 'supplier_classifications_create', methods: ['POST'])]
    public function create(string $type, Request $request, ManageSupplierClassification $manager): JsonResponse
    {
        $this->roles->assertAnyRole('supplier-admin');
        return new JsonResponse($manager->create($type, $this->payload($request)), 201);
    }

    #[Route('/supplier-classifications/{type}/{id<\d+>}/status', name: 'supplier_classifications_status', methods: ['PATCH'])]
    public function status(string $type, int $id, Request $request, ManageSupplierClassification $manager): JsonResponse
    {
        $this->roles->assertAnyRole('supplier-admin');
        $payload = $this->payload($request);
        if (!is_bool($payload['active'] ?? null)) throw new UnprocessableEntityHttpException('Field active must be a boolean.');
        return new JsonResponse($manager->setActive($type, $id, $payload['active']));
    }

    #[Route('/suppliers/{id<\d+>}/classification', name: 'supplier_classification_get', methods: ['GET'])]
    public function get(int $id, SupplierClassificationReader $reader, ManageSupplierClassification $manager): JsonResponse
    {
        $this->roles->assertAnyRole('procurement-user','supplier-onboarding-submit','supplier-reviewer','supplier-admin');
        return new JsonResponse($manager->get($id, $reader));
    }

    #[Route('/suppliers/{id<\d+>}/classification', name: 'supplier_classification_assign', methods: ['PUT'])]
    public function assign(int $id, Request $request, ManageSupplierClassification $manager): JsonResponse
    {
        $this->roles->assertAnyRole('procurement-user','supplier-admin');
        $user = $request->attributes->get('_keycloak_user', []);
        return new JsonResponse($manager->assign($id, $this->payload($request), (string)($user['username'] ?? $user['id'] ?? 'system')));
    }

    /** @return array<string,mixed> */
    private function payload(Request $request): array
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || array_is_list($data)) throw new UnprocessableEntityHttpException('A JSON object is required.');
        return $data;
    }
}

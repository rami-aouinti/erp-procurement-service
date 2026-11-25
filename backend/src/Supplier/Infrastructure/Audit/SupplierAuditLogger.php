<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Audit;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SupplierAuditLogger
{
    public function __construct(
        private Connection $connection,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Records field names only. Values are intentionally excluded because
     * supplier records may contain personal and financial information.
     *
     * @param list<string> $changedFields
     */
    public function record(
        int $supplierId,
        string $entityType,
        ?int $entityId,
        string $action,
        array $changedFields,
    ): void {
        $request = $this->requestStack->getCurrentRequest();
        $actor = $request?->attributes->get('_keycloak_user');
        $authenticated = is_array($actor) && is_string($actor['id'] ?? null);
        $requestId = $request?->headers->get('X-Request-ID');
        if ($requestId !== null && (strlen($requestId) > 128 || preg_match('/^[A-Za-z0-9._:-]+$/', $requestId) !== 1)) {
            $requestId = null;
        }

        $this->connection->executeStatement(
            'INSERT INTO supplier_audit_logs
                (supplier_id, entity_type, entity_id, action, actor_type, actor_identifier,
                 actor_label, request_id, changed_fields, occurred_at)
             VALUES
                (:supplierId, :entityType, :entityId, :action, :actorType, :actorIdentifier,
                 :actorLabel, :requestId, :changedFields, SYSTIMESTAMP)',
            [
                'supplierId' => $supplierId,
                'entityType' => $entityType,
                'entityId' => $entityId,
                'action' => $action,
                // Actor identity comes only from the verified Keycloak token.
                'actorType' => $request === null ? 'SYSTEM' : ($authenticated ? 'USER' : 'ANONYMOUS'),
                'actorIdentifier' => $authenticated ? $actor['id'] : null,
                'actorLabel' => $request === null
                    ? 'CLI or background process'
                    : ($authenticated ? (string) ($actor['username'] ?? $actor['id']) : 'Unauthenticated HTTP request'),
                'requestId' => $requestId,
                'changedFields' => json_encode(array_values(array_unique($changedFields)), JSON_THROW_ON_ERROR),
            ],
        );
    }
}

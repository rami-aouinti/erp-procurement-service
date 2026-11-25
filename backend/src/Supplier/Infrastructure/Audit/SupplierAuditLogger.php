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
        $requestId = $request?->headers->get('X-Request-ID');
        if ($requestId !== null && (strlen($requestId) > 128 || preg_match('/^[A-Za-z0-9._:-]+$/', $requestId) !== 1)) {
            $requestId = null;
        }

        $this->connection->executeStatement(
            'INSERT INTO supplier_audit_logs
                (supplier_id, entity_type, entity_id, action, actor_type, actor_identifier,
                 actor_label, request_id, changed_fields, occurred_at)
             VALUES
                (:supplierId, :entityType, :entityId, :action, :actorType, NULL,
                 :actorLabel, :requestId, :changedFields, SYSTIMESTAMP)',
            [
                'supplierId' => $supplierId,
                'entityType' => $entityType,
                'entityId' => $entityId,
                'action' => $action,
                // Authentication is not configured in this application yet.
                // Never trust a client-supplied actor header as identity.
                'actorType' => $request === null ? 'SYSTEM' : 'ANONYMOUS',
                'actorLabel' => $request === null ? 'CLI or background process' : 'Unauthenticated HTTP request',
                'requestId' => $requestId,
                'changedFields' => json_encode(array_values(array_unique($changedFields)), JSON_THROW_ON_ERROR),
            ],
        );
    }
}

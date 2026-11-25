<?php

declare(strict_types=1);

namespace App\Supplier\Application\UseCase;

use App\Security\Keycloak\KeycloakRoleGuard;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ManageSupplierOnboarding
{
    public function __construct(
        private Connection $connection,
        private KeycloakRoleGuard $roles,
        private RequestStack $requests,
    ) {
    }

    /** @return array<string, mixed> */
    public function submit(int $supplierId, bool $activateOnApproval = false): array
    {
        $actor = $this->roles->currentActor();
        return $this->connection->transactional(function (Connection $db) use ($supplierId, $actor, $activateOnApproval): array {
            $supplier = $db->fetchAssociative(
                'SELECT id, status FROM suppliers WHERE id = :id FOR UPDATE',
                ['id' => $supplierId],
            );
            if ($supplier === false) {
                throw new \OutOfBoundsException('Supplier not found.');
            }

            $onboarding = $db->fetchAssociative(
                'SELECT onboarding_status, previous_supplier_status FROM supplier_onboarding WHERE supplier_id = :id FOR UPDATE',
                ['id' => $supplierId],
            );
            if ($onboarding === false) {
                $from = 'DRAFT';
                $db->insert('supplier_onboarding', [
                    'supplier_id' => $supplierId,
                    'onboarding_status' => 'DRAFT',
                    'previous_supplier_status' => ($activateOnApproval || $supplier['STATUS'] === 'INACTIVE') ? 'ACTIVE' : $supplier['STATUS'],
                    'version' => 1,
                ]);
            } else {
                $from = (string) $onboarding['ONBOARDING_STATUS'];
                if (!in_array($from, ['DRAFT', 'CHANGES_REQUESTED'], true)) {
                    throw new \DomainException('Only draft or change-requested suppliers can be submitted.');
                }
            }

            if ($supplier['STATUS'] === 'ACTIVE') {
                $db->executeStatement("UPDATE suppliers SET status = 'INACTIVE' WHERE id = :id", ['id' => $supplierId]);
            }

            $db->executeStatement(
                "UPDATE supplier_onboarding
                 SET onboarding_status = 'PENDING_APPROVAL', submitted_at = SYSTIMESTAMP,
                     decided_at = NULL, decided_by = NULL, decision_reason = NULL,
                     version = version + 1, updated_at = SYSTIMESTAMP
                 WHERE supplier_id = :id",
                ['id' => $supplierId],
            );

            $this->recordTransition($db, $supplierId, $from, 'PENDING_APPROVAL', $actor, null, 'ONBOARDING_SUBMITTED');
            return ['supplierId' => $supplierId, 'status' => 'PENDING_APPROVAL'];
        });
    }

    /** @return array<string, mixed> */
    public function approve(int $supplierId, ?string $reason): array
    {
        return $this->decide($supplierId, 'APPROVED', 'ONBOARDING_APPROVED', $reason);
    }

    /** @return array<string, mixed> */
    public function reject(int $supplierId, string $reason): array
    {
        return $this->decide($supplierId, 'REJECTED', 'ONBOARDING_REJECTED', $reason);
    }

    /** @return array<string, mixed> */
    public function requestChanges(int $supplierId, string $reason): array
    {
        return $this->decide($supplierId, 'CHANGES_REQUESTED', 'ONBOARDING_CHANGES_REQUESTED', $reason);
    }

    /** @return list<array<string, mixed>> */
    public function queue(): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT s.id AS supplier_id, s.name, s.email, o.onboarding_status, o.submitted_at, o.version
             FROM supplier_onboarding o
             INNER JOIN suppliers s ON s.id = o.supplier_id
             WHERE o.onboarding_status = 'PENDING_APPROVAL'
             ORDER BY o.submitted_at ASC, s.id ASC",
        );
    }

    /** @return list<array<string, mixed>> */
    public function history(int $supplierId): array
    {
        $exists = $this->connection->fetchOne('SELECT COUNT(*) FROM suppliers WHERE id = :id', ['id' => $supplierId]);
        if ((int) $exists === 0) {
            throw new \OutOfBoundsException('Supplier not found.');
        }

        return $this->connection->fetchAllAssociative(
            'SELECT from_status, to_status, actor_id, actor_name, reason, occurred_at
             FROM supplier_onboarding_history WHERE supplier_id = :id ORDER BY occurred_at ASC, id ASC',
            ['id' => $supplierId],
        );
    }

    /** @return array<string, mixed> */
    private function decide(int $supplierId, string $target, string $action, ?string $reason): array
    {
        $actor = $this->roles->currentActor();
        if ($target !== 'APPROVED' && ($reason === null || trim($reason) === '')) {
            throw new \InvalidArgumentException('A reason is required for rejection or change requests.');
        }
        if ($reason !== null && mb_strlen(trim($reason)) > 1000) {
            throw new \InvalidArgumentException('Reason must not exceed 1000 characters.');
        }

        return $this->connection->transactional(function (Connection $db) use ($supplierId, $target, $action, $reason, $actor): array {
            $supplier = $db->fetchAssociative(
                'SELECT id, status FROM suppliers WHERE id = :id FOR UPDATE',
                ['id' => $supplierId],
            );
            if ($supplier === false) {
                throw new \OutOfBoundsException('Supplier not found.');
            }

            $onboarding = $db->fetchAssociative(
                'SELECT onboarding_status, previous_supplier_status FROM supplier_onboarding WHERE supplier_id = :id FOR UPDATE',
                ['id' => $supplierId],
            );
            if ($onboarding === false || $onboarding['ONBOARDING_STATUS'] !== 'PENDING_APPROVAL') {
                throw new \DomainException('Only suppliers pending approval can receive a decision.');
            }

            if ($target === 'APPROVED' && $onboarding['PREVIOUS_SUPPLIER_STATUS'] !== null) {
                $db->executeStatement(
                    'UPDATE suppliers SET status = :status WHERE id = :id',
                    ['status' => $onboarding['PREVIOUS_SUPPLIER_STATUS'], 'id' => $supplierId],
                );
            }

            $db->executeStatement(
                'UPDATE supplier_onboarding
                 SET onboarding_status = :status, decided_at = SYSTIMESTAMP, decided_by = :actor,
                     decision_reason = :reason, version = version + 1, updated_at = SYSTIMESTAMP
                 WHERE supplier_id = :id',
                ['status' => $target, 'actor' => $actor['id'], 'reason' => $reason !== null ? trim($reason) : null, 'id' => $supplierId],
            );

            $this->recordTransition($db, $supplierId, 'PENDING_APPROVAL', $target, $actor, $reason, $action);
            return ['supplierId' => $supplierId, 'status' => $target, 'reason' => $reason !== null ? trim($reason) : null];
        });
    }

    /** @param array{id: string, username: string, roles: list<string>} $actor */
    private function recordTransition(
        Connection $db,
        int $supplierId,
        string $from,
        string $to,
        array $actor,
        ?string $reason,
        string $action,
    ): void {
        $requestId = $this->requests->getCurrentRequest()?->headers->get('X-Request-ID');
        if ($requestId !== null && (strlen($requestId) > 128 || preg_match('/^[A-Za-z0-9._:-]+$/', $requestId) !== 1)) {
            $requestId = null;
        }

        $db->insert('supplier_onboarding_history', [
            'supplier_id' => $supplierId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor['id'],
            'actor_name' => $actor['username'],
            'reason' => $reason !== null ? trim($reason) : null,
            'request_id' => $requestId,
        ]);

        $db->executeStatement(
            "INSERT INTO supplier_audit_logs
                (supplier_id, entity_type, entity_id, action, actor_type, actor_identifier,
                 actor_label, request_id, changed_fields, occurred_at)
             VALUES (:supplierId, 'SUPPLIER_ONBOARDING', :supplierId, :action, 'USER', :actorId,
                     :actorName, :requestId, :fields, SYSTIMESTAMP)",
            [
                'supplierId' => $supplierId,
                'action' => $action,
                'actorId' => $actor['id'],
                'actorName' => $actor['username'],
                'requestId' => $requestId,
                'fields' => json_encode(['onboarding_status', 'supplier_status', 'decision_reason'], JSON_THROW_ON_ERROR),
            ],
        );

        $eventType = match ($to) {
            'PENDING_APPROVAL' => 'SupplierOnboardingSubmitted',
            'APPROVED' => 'SupplierOnboardingApproved',
            'REJECTED' => 'SupplierOnboardingRejected',
            default => 'SupplierOnboardingChangesRequested',
        };
        $db->executeStatement(
            "INSERT INTO supplier_outbox_events
                (event_id, event_type, aggregate_id, payload, status, attempts, occurred_at, next_attempt_at)
             VALUES (RAWTOHEX(SYS_GUID()), :eventType, :supplierId, :payload, 'PENDING', 0, SYSTIMESTAMP, SYSTIMESTAMP)",
            [
                'eventType' => $eventType,
                'supplierId' => $supplierId,
                'payload' => json_encode([
                    'schemaVersion' => 1,
                    'supplierId' => $supplierId,
                    'fromStatus' => $from,
                    'toStatus' => $to,
                    'actorId' => $actor['id'],
                ], JSON_THROW_ON_ERROR),
            ],
        );
    }
}

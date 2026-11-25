<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Persistence;

use App\Supplier\Application\Port\SupplierDocumentStorage;
use App\Supplier\Application\Service\SupplierDocumentUploadValidator;
use App\Supplier\Infrastructure\Audit\SupplierAuditLogger;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class OracleSupplierDocumentRepository
{
    public function __construct(
        private Connection $db,
        private SupplierDocumentStorage $storage,
        private SupplierDocumentUploadValidator $validator,
        private SupplierAuditLogger $audit,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function documentTypes(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, code, name, description, allowed_mime_types AS "allowedMimeTypes",
                    max_file_size_bytes AS "maxFileSizeBytes"
             FROM supplier_document_types
             WHERE is_active = 1
             ORDER BY name'
        );

        return array_map(static function (array $row): array {
            $allowed = json_decode((string) $row['allowedMimeTypes'], true);
            return [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'description' => $row['description'],
                'allowedMimeTypes' => is_array($allowed) ? array_values(array_filter($allowed, 'is_string')) : [],
                'maxFileSizeBytes' => (int) $row['maxFileSizeBytes'],
            ];
        }, $rows);
    }

    /** @param list<string> $allowedMimeTypes */
    public function createDocumentType(
        string $code,
        string $name,
        ?string $description,
        array $allowedMimeTypes,
        int $maxFileSizeBytes,
    ): array {
        $code = strtoupper(trim($code));
        $name = trim($name);
        if (preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/', $code) !== 1) {
            throw new UnprocessableEntityHttpException('Document type code must contain 2–60 uppercase letters, digits, underscores or hyphens.');
        }
        if ($name === '' || mb_strlen($name) > 150) {
            throw new UnprocessableEntityHttpException('Document type name is required and must not exceed 150 characters.');
        }
        if ($description !== null && mb_strlen($description) > 500) {
            throw new UnprocessableEntityHttpException('Document type description must not exceed 500 characters.');
        }
        $allowedMimeTypes = array_values(array_unique($allowedMimeTypes));
        if ($allowedMimeTypes === [] || array_diff($allowedMimeTypes, ['application/pdf', 'image/jpeg', 'image/png']) !== []) {
            throw new UnprocessableEntityHttpException('Allowed MIME types must be selected from PDF, JPEG and PNG.');
        }
        if ($maxFileSizeBytes < 1 || $maxFileSizeBytes > 104857600) {
            throw new UnprocessableEntityHttpException('Maximum file size must be between 1 byte and 100 MiB.');
        }

        try {
            $this->db->insert('supplier_document_types', [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'allowed_mime_types' => json_encode($allowedMimeTypes, JSON_THROW_ON_ERROR),
                'max_file_size_bytes' => $maxFileSizeBytes,
                'is_active' => 1,
            ]);
        } catch (\Throwable $exception) {
            if (str_contains(strtoupper($exception->getMessage()), 'ORA-00001')) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('A document type with this code already exists.');
            }
            throw $exception;
        }

        $id = (int) $this->db->fetchOne('SELECT id FROM supplier_document_types WHERE code = :code', ['code' => $code]);
        return [
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'allowedMimeTypes' => $allowedMimeTypes,
            'maxFileSizeBytes' => $maxFileSizeBytes,
            'active' => true,
        ];
    }

    /** @return array<string,mixed> */
    public function upload(
        int $supplierId,
        int $documentTypeId,
        string $title,
        UploadedFile $file,
        string $actorId,
        ?string $expiresAt,
    ): array {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 200) {
            throw new UnprocessableEntityHttpException('Document title is required and must not exceed 200 characters.');
        }
        if ($expiresAt !== null && $expiresAt !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $expiresAt);
            if (!$date || $date->format('Y-m-d') !== $expiresAt) {
                throw new UnprocessableEntityHttpException('expiresAt must use YYYY-MM-DD format.');
            }
        } else {
            $expiresAt = null;
        }

        $type = $this->db->fetchAssociative(
            'SELECT id, code, allowed_mime_types, max_file_size_bytes
             FROM supplier_document_types WHERE id = :id AND is_active = 1',
            ['id' => $documentTypeId]
        );
        if ($type === false) {
            throw new NotFoundHttpException('Active supplier document type not found.');
        }
        $allowed = json_decode((string) $type['allowed_mime_types'], true);
        $allowed = is_array($allowed) ? array_values(array_filter($allowed, 'is_string')) : [];
        $validated = $this->validator->validate($file, $allowed, (int) $type['max_file_size_bytes']);

        // Store bytes first. If Oracle fails, the object is deleted in the catch block.
        $stored = $this->storage->put($file->getPathname(), (int) $type['max_file_size_bytes']);
        $this->db->beginTransaction();
        try {
            // Serialize document ID allocation for this supplier; the lock also verifies existence.
            $supplier = $this->db->fetchOne('SELECT id FROM suppliers WHERE id = :id FOR UPDATE', ['id' => $supplierId]);
            if ($supplier === false) {
                throw new NotFoundHttpException('Supplier not found.');
            }

            $this->db->insert('supplier_documents', [
                'supplier_id' => $supplierId,
                'document_type_id' => $documentTypeId,
                'title' => $title,
            ]);
            $documentId = (int) $this->db->fetchOne(
                'SELECT MAX(id) FROM supplier_documents WHERE supplier_id = :supplierId',
                ['supplierId' => $supplierId]
            );

            $this->db->insert('supplier_document_versions', [
                'document_id' => $documentId,
                'version_number' => 1,
                'object_key' => $stored->objectKey,
                'original_filename' => $validated['filename'],
                'detected_mime_type' => $validated['mimeType'],
                'file_size_bytes' => $stored->sizeBytes,
                'checksum_sha256' => $stored->sha256,
                'scan_status' => 'PENDING',
                'review_status' => 'PENDING',
                'expires_at' => $expiresAt,
                'uploaded_by' => $actorId,
            ]);
            $versionId = (int) $this->db->fetchOne(
                'SELECT MAX(id) FROM supplier_document_versions WHERE document_id = :documentId',
                ['documentId' => $documentId]
            );
            $this->db->executeStatement(
                'UPDATE supplier_documents SET current_version_id = :versionId, updated_at = SYSTIMESTAMP WHERE id = :documentId',
                ['versionId' => $versionId, 'documentId' => $documentId]
            );

            $this->audit->record($supplierId, 'SUPPLIER_DOCUMENT', $documentId, 'UPLOADED', ['title', 'documentTypeId', 'version']);
            $this->db->executeStatement(
                "INSERT INTO supplier_outbox_events
                    (event_id, event_type, aggregate_id, payload, status, attempts, occurred_at, next_attempt_at)
                 VALUES
                    (RAWTOHEX(SYS_GUID()), 'SupplierDocumentUploaded', :supplierId, :payload, 'PENDING', 0, SYSTIMESTAMP, SYSTIMESTAMP)",
                [
                    'supplierId' => $supplierId,
                    'payload' => json_encode([
                        'supplierId' => $supplierId,
                        'documentId' => $documentId,
                        'versionId' => $versionId,
                        'documentTypeCode' => (string) $type['code'],
                        'scanStatus' => 'PENDING',
                        'reviewStatus' => 'PENDING',
                    ], JSON_THROW_ON_ERROR),
                ]
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->isTransactionActive()) {
                $this->db->rollBack();
            }
            try {
                $this->storage->delete($stored->objectKey);
            } catch (\Throwable) {
                // Keep the original persistence error; object cleanup is an operational follow-up.
            }
            throw $exception;
        }

        return [
            'id' => $documentId,
            'supplierId' => $supplierId,
            'documentTypeId' => $documentTypeId,
            'title' => $title,
            'currentVersion' => [
                'id' => $versionId,
                'versionNumber' => 1,
                'filename' => $validated['filename'],
                'mimeType' => $validated['mimeType'],
                'sizeBytes' => $stored->sizeBytes,
                'checksumSha256' => $stored->sha256,
                'scanStatus' => 'PENDING',
                'reviewStatus' => 'PENDING',
                'expiresAt' => $expiresAt,
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function listForSupplier(int $supplierId): array
    {
        if ((int) $this->db->fetchOne('SELECT COUNT(*) FROM suppliers WHERE id = :id', ['id' => $supplierId]) !== 1) {
            throw new NotFoundHttpException('Supplier not found.');
        }

        $rows = $this->db->fetchAllAssociative(
            'SELECT d.id, d.supplier_id AS "supplierId", d.document_type_id AS "documentTypeId",
                    d.title, d.created_at AS "createdAt", d.updated_at AS "updatedAt",
                    v.id AS "versionId", v.version_number AS "versionNumber",
                    v.original_filename AS "filename", v.detected_mime_type AS "mimeType",
                    v.file_size_bytes AS "sizeBytes", v.checksum_sha256 AS "checksumSha256",
                    v.scan_status AS "scanStatus", v.review_status AS "reviewStatus",
                    v.expires_at AS "expiresAt", v.uploaded_at AS "uploadedAt"
             FROM supplier_documents d
             LEFT JOIN supplier_document_versions v ON v.id = d.current_version_id
             WHERE d.supplier_id = :supplierId
             ORDER BY d.updated_at DESC, d.id DESC',
            ['supplierId' => $supplierId]
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'supplierId' => (int) $row['supplierId'],
                'documentTypeId' => (int) $row['documentTypeId'],
                'title' => $row['title'],
                'createdAt' => $row['createdAt'],
                'updatedAt' => $row['updatedAt'],
                'currentVersion' => $row['versionId'] === null ? null : [
                    'id' => (int) $row['versionId'],
                    'versionNumber' => (int) $row['versionNumber'],
                    'filename' => $row['filename'],
                    'mimeType' => $row['mimeType'],
                    'sizeBytes' => (int) $row['sizeBytes'],
                    'checksumSha256' => $row['checksumSha256'],
                    'scanStatus' => $row['scanStatus'],
                    'reviewStatus' => $row['reviewStatus'],
                    'expiresAt' => $row['expiresAt'],
                    'uploadedAt' => $row['uploadedAt'],
                ],
            ];
        }, $rows);
    }
}

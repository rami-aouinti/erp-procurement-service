<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Http;

use App\Security\Keycloak\KeycloakRoleGuard;
use App\Supplier\Infrastructure\Persistence\OracleSupplierDocumentRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class SupplierDocumentController
{
    public function __construct(private readonly KeycloakRoleGuard $roles)
    {
    }

    #[Route('/supplier-document-types', name: 'supplier_document_types_list', methods: ['GET'])]
    public function types(OracleSupplierDocumentRepository $documents): JsonResponse
    {
        $this->roles->assertAnyRole('procurement-user', 'supplier-reviewer', 'supplier-admin');
        return new JsonResponse($documents->documentTypes());
    }

    #[Route('/supplier-document-types', name: 'supplier_document_types_create', methods: ['POST'])]
    public function createType(Request $request, OracleSupplierDocumentRepository $documents): JsonResponse
    {
        $this->roles->assertAnyRole('supplier-admin');
        $payload = $this->jsonObject($request);
        $mimeTypes = $payload['allowedMimeTypes'] ?? [];
        if (!is_array($mimeTypes) || !array_is_list($mimeTypes) || array_filter($mimeTypes, 'is_string') !== $mimeTypes) {
            throw new UnprocessableEntityHttpException('allowedMimeTypes must be an array of strings.');
        }
        $description = $payload['description'] ?? null;
        if ($description !== null && !is_string($description)) {
            throw new UnprocessableEntityHttpException('description must be a string or null.');
        }
        $maxBytes = filter_var($payload['maxFileSizeBytes'] ?? 10485760, FILTER_VALIDATE_INT);
        if ($maxBytes === false) {
            throw new UnprocessableEntityHttpException('maxFileSizeBytes must be an integer.');
        }

        return new JsonResponse($documents->createDocumentType(
            (string) ($payload['code'] ?? ''),
            (string) ($payload['name'] ?? ''),
            $description,
            $mimeTypes,
            $maxBytes,
        ), 201);
    }

    #[Route('/suppliers/{supplierId<\d+>}/documents', name: 'supplier_documents_list', methods: ['GET'])]
    public function list(int $supplierId, Request $request, OracleSupplierDocumentRepository $documents): JsonResponse
    {
        $this->roles->assertAnyRole('procurement-user', 'supplier-reviewer', 'supplier-admin');
        $page = filter_var($request->query->get('page', 1), FILTER_VALIDATE_INT);
        $pageSize = filter_var($request->query->get('pageSize', 25), FILTER_VALIDATE_INT);
        if ($page === false || $pageSize === false) {
            throw new UnprocessableEntityHttpException('page and pageSize must be integers.');
        }
        return new JsonResponse($documents->listForSupplier($supplierId, $page, $pageSize));
    }

    #[Route('/suppliers/{supplierId<\d+>}/documents', name: 'supplier_documents_upload', methods: ['POST'])]
    public function upload(int $supplierId, Request $request, OracleSupplierDocumentRepository $documents): JsonResponse
    {
        // Until supplier-to-Keycloak identity mapping exists, uploads are restricted
        // to internal supplier administrators to avoid cross-supplier IDOR access.
        $this->roles->assertAnyRole('supplier-admin');
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException('A multipart file field named "file" is required.');
        }

        $documentTypeId = filter_var($request->request->get('documentTypeId'), FILTER_VALIDATE_INT);
        if ($documentTypeId === false || $documentTypeId < 1) {
            throw new UnprocessableEntityHttpException('documentTypeId must be a positive integer.');
        }
        $title = $request->request->get('title');
        if (!is_string($title)) {
            throw new UnprocessableEntityHttpException('title is required.');
        }
        $expiresAt = $request->request->get('expiresAt');
        if ($expiresAt !== null && !is_string($expiresAt)) {
            throw new UnprocessableEntityHttpException('expiresAt must be a date string.');
        }

        $actor = $this->roles->currentActor();
        return new JsonResponse($documents->upload(
            $supplierId,
            $documentTypeId,
            $title,
            $file,
            $actor['id'],
            $expiresAt,
        ), 201);
    }

    /** @return array<string,mixed> */
    private function jsonObject(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || array_is_list($payload)) {
            throw new UnprocessableEntityHttpException('A JSON object is required.');
        }
        return $payload;
    }
}

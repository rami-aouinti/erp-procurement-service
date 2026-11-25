# Supplier documents and compliance (SUP-F03)

## Delivery status

**Foundation in progress.** Oracle metadata schema is introduced by migration 026 and CI initializes it after migration 025. Upload/download, private object-storage adapters, malware scanning, lifecycle use cases, API and frontend are not yet delivered. Do not treat the feature as production-ready until the remaining sections below are implemented and verified.

## Architecture and data ownership

- Oracle is the source of truth for document metadata, requirements, versions, scan state, review state and expiry dates.
- Binary files live in a private object store. They must never be stored in Oracle BLOB columns, a public web root, Elasticsearch, Redis, RabbitMQ messages, logs or metrics.
- The storage abstraction must expose opaque object keys only. Keys are generated server-side from random identifiers; never derive paths from the original filename or supplier-provided values.
- The initial local development adapter must write outside the web root with restrictive permissions. The production adapter must support an S3-compatible private bucket, server-side encryption, TLS, and bucket policies that deny public access.
- The application layer depends on a storage port. HTTP controllers must not know whether local storage or S3 is configured.
- A document version is not downloadable until malware scanning is CLEAN and the compliance reviewer has APPROVED it. Any scan error or unavailable scanner leaves the object quarantined (fail closed).
- Downloads must be authorized on every request and streamed through the backend or granted through a short-lived, narrowly scoped signed URL only after authorization.

## Oracle migration 026

Apply database/oracle/026_supplier_documents_compliance.sql after migration 025 using the ERP_APP schema owner.

Tables:

- supplier_document_types: controlled document types, allowed MIME types (JSON), active state and configured maximum size.
- supplier_document_requirements: required/optional evidence scoped by supplier category and/or country, including validity and warning windows.
- supplier_documents: logical document identity linked to a supplier and document type.
- supplier_document_versions: immutable per-upload metadata, checksum, opaque storage key, scan/review state, expiry and reviewer attribution.

The schema stores no file bytes. The 100 MiB database-level upper bound is a hard safety ceiling; each document type can configure a lower limit. Existing suppliers are not modified.

Oracle DDL implicitly commits. Before rollout, back up the schema and apply the additive migration once. If a deployment fails after some DDL statements, inspect the created objects and re-run the idempotent migration; do not drop tables as an automatic rollback.

## Required lifecycle

1. Upload request: authenticate and authorize the actor; verify supplier and active document type; enforce configured size and declared MIME limits.
2. Temporary write: stream to a private quarantine area/object key while calculating SHA-256. Enforce size during streaming, not only from the client Content-Length.
3. Content validation: inspect file signatures (not just extension or browser MIME); reject mismatches, malformed files, path traversal attempts and unsupported formats.
4. Metadata commit: persist the logical document/version and PENDING scan/review states. Object write must complete before a version becomes visible. On Oracle failure, delete the staged object; cleanup failures must be observable and recoverable.
5. Malware scan: scan asynchronously through a port. A clean result moves the version to scan CLEAN; infected or failed scans remain unavailable and are quarantined/rejected.
6. Compliance review: only a reviewer/admin can approve or reject a clean version. Review decision, actor, timestamp and reason are persisted and audited.
7. Download: require supplier-scoped authorization and CLEAN + APPROVED + not deleted state. Never trust a client-supplied object key.
8. Version replacement: create a new immutable version; never overwrite the prior object or erase its review history.
9. Expiry: expiry is metadata-driven. Expired evidence remains in history but is no longer considered compliant.

## Security and privacy requirements

- Suggested roles: procurement-user for metadata read, supplier-onboarding-submit for supplier evidence submission, supplier-reviewer for review decisions, and supplier-admin for document-type and requirement administration. Finance-only bank permissions do not grant document access.
- Enforce authorization in the backend, including supplier ID ownership/scope checks; frontend hiding is not security.
- Limit MIME types and size per document type; allowlist formats. Do not accept executable content or archives by default.
- Generate random object keys. Preserve the original filename only as escaped display metadata; set safe Content-Disposition headers.
- Never expose local filesystem paths, bucket names, object keys, signed URLs, file bytes, or document contents in logs, events, metrics, errors or search indexes.
- Use short-lived access and avoid public bucket ACLs. Define encryption-at-rest, backup, residency, retention and deletion policies before production rollout.
- Audit upload, new version, scan outcome, approval, rejection, expiry and deletion with actor identity and request correlation.

## Metadata-only events

Use the existing transactional Outbox after document metadata transitions. Event payloads may contain supplier ID, document ID, version ID, document type code, lifecycle status and timestamps only. They must not contain file bytes, original document text, bank details, object-store credentials or signed URLs. Consumers must be idempotent.

## API and UI contract to implement

- GET /api/suppliers/{supplierId}/documents — paginated metadata list and compliance state.
- POST /api/suppliers/{supplierId}/documents — multipart upload; response never includes an object key.
- GET /api/suppliers/{supplierId}/documents/{documentId} — metadata and version history.
- POST /api/suppliers/{supplierId}/documents/{documentId}/versions — append a new immutable version.
- POST /api/suppliers/{supplierId}/documents/{documentId}/versions/{versionId}/review — approve/reject after clean scan.
- GET /api/suppliers/{supplierId}/documents/{documentId}/versions/{versionId}/download — authorized download.
- Admin endpoints for document types and requirement policies.

Collection endpoints must be paginated with a bounded page size. Upload, review and download endpoints must document 401, 403, 404, 409, 413, 415 and storage/scanner dependency failures in OpenAPI.

## Verification checklist

- [x] Additive Oracle metadata schema and constraints.
- [x] CI Oracle initialization includes migration 026.
- [x] Protect the supplier document API namespace at the Keycloak request boundary.
- [ ] Add private local and S3-compatible object-storage adapters.
- [ ] Add streaming upload validation, checksum and cleanup behavior.
- [ ] Add malware-scanning port, quarantine state and idempotent worker.
- [ ] Add application use cases, repository, audit and metadata-only Outbox events.
- [ ] Add role/IDOR tests, file validation, size/MIME/signature, storage outage and scanner failure tests.
- [ ] Add expiry/required-document compliance queries and idempotent reminders.
- [ ] Add REST/OpenAPI and React document/compliance screens.
- [ ] Verify migration 026 and all test suites against local Docker Compose and CI.

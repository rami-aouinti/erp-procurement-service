# Supplier documents and compliance (SUP-F03)

## Delivery status

**Implementation in progress.** Migration 026, private local storage, upload validation, Oracle metadata persistence, document-type administration, metadata listing and an authenticated multipart upload endpoint are now present. Upload is currently restricted to `supplier-admin` because supplier-to-Keycloak identity mapping is not implemented; this is an intentional IDOR safety boundary. No antivirus daemon is included and uploads remain `PENDING` for both scan and review. Download/release, reviewer decisions, version append/history, requirement evaluation, production S3 adapter and frontend remain outstanding. Do not treat the feature as production-ready until the remaining controls are implemented and verified.

## Architecture and data ownership

- Oracle is the source of truth for document metadata, requirements, versions, scan state, review state and expiry dates.
- Binary files live in a private object store. They must never be stored in Oracle BLOB columns, a public web root, Elasticsearch, Redis, RabbitMQ messages, logs or metrics.
- The storage abstraction must expose opaque object keys only. Keys are generated server-side from random identifiers; never derive paths from the original filename or supplier-provided values.
- The initial local development adapter must write outside the web root with restrictive permissions. The production adapter must support an S3-compatible private bucket, server-side encryption, TLS, and bucket policies that deny public access.
- The application layer depends on a storage port. HTTP controllers must not know whether local storage or S3 is configured.
- A document version is not downloadable until the compliance reviewer has APPROVED it and the application has completed its configured safety checks. The current development setup does not perform antivirus scanning; do not describe files as malware-scanned or malware-free. Keep downloads disabled until the no-scanner review policy and lifecycle state are explicitly implemented.
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
5. Safety checks: validate file signatures, MIME allowlist and size. Antivirus scanning is intentionally deferred in the lightweight local setup; the optional application port can support a future external scanner without adding a heavy local daemon.
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
- [x] Add a private local object-storage adapter with random opaque keys, restrictive permissions, streaming size enforcement, SHA-256 checksums, and persistent Docker volume.\n- [ ] Add the production S3-compatible adapter.
- [x] Add upload content validation for detected MIME type, allowlist, actual size and sanitized display filename; storage computes SHA-256 and cleans partial writes on failure.\n- [x] Wire validation/storage into the authenticated upload API and Oracle metadata transaction; clean up the private object if the Oracle transaction fails.
- [x] Keep an optional malware-scanner application port for future integration without binding a heavy local service.\n- [ ] Decide and implement the no-scanner document release policy; add an external scanner only if deployment requirements justify it.
- [x] Add Oracle metadata persistence, upload audit and metadata-only Outbox event for initial upload.\n- [ ] Extract application use cases/ports and add immutable append-version workflow.
- [x] Add unit tests for content-based MIME validation, oversize rejection, private object storage, checksum, traversal rejection and partial-write cleanup.\n- [ ] Add role/IDOR tests, storage outage and scanner failure tests.
- [ ] Add expiry/required-document compliance queries and idempotent reminders.
- [x] Add initial REST endpoints for document types, supplier document listing and multipart upload.\n- [ ] Add reviewer/version/download/requirements REST endpoints and React document/compliance screens.
- [ ] Verify migration 026 and all test suites against local Docker Compose and CI.


## Local private storage foundation (2026-11-25)

The current development adapter is `LocalPrivateSupplierDocumentStorage`, configured through `SUPPLIER_DOCUMENT_STORAGE_PATH` and mounted to the persistent Docker volume `erp_supplier_documents_data`. It generates 256-bit random hexadecimal keys, writes outside the web root with directory mode 0700 and file mode 0600, enforces the byte limit while streaming, calculates SHA-256 and removes partial objects after write failures. The key is not derived from the user filename.

`SupplierDocumentUploadValidator` uses PHP fileinfo to detect actual content MIME, intersects it with the configured allowlist (PDF/JPEG/PNG only), checks actual upload size, and sanitizes the display filename. This foundation is not yet exposed as an upload endpoint; no file can be uploaded through the API until the Oracle use case and controller are delivered.

The local adapter is for development only. It is not a substitute for the production S3-compatible adapter, malware scanner, quarantine workflow, authorization checks or backup/retention controls.


## Lightweight malware-scanning decision (2026-11-25)

ClamAV was removed from Docker Compose because its memory footprint is disproportionate for this local development environment. The ClamAV adapter, response parser and their tests were removed as well. The application-level scanner port is retained as an extension point, but it is not bound to any implementation and no antivirus scan is claimed.

The current upload foundation still performs content-based MIME detection, allowlist checks, size enforcement and private storage. These checks are not antivirus protection. The document lifecycle must therefore make the absence of scanning explicit and must not silently mark a file as CLEAN. Before enabling downloads, implement an explicit review/release policy for deployments without antivirus, or integrate an external scanning provider appropriate to the production environment.


## Oracle upload/API vertical slice (2026-11-25)

The first end-to-end metadata upload slice is implemented in `OracleSupplierDocumentRepository` and `SupplierDocumentController`:

- `GET /api/supplier-document-types` lists active document types.
- `POST /api/supplier-document-types` creates a type (supplier-admin only), with a strict PDF/JPEG/PNG allowlist and a maximum size capped at 100 MiB.
- `GET /api/suppliers/{supplierId}/documents` lists document metadata and the current version.
- `POST /api/suppliers/{supplierId}/documents` accepts multipart fields `file`, `documentTypeId`, `title`, and optional `expiresAt` (YYYY-MM-DD).

The upload writes the binary to private storage first, then locks the supplier row and persists logical document/version metadata, audit data and a metadata-only outbox event in one Oracle transaction. If persistence fails, it attempts to delete the newly stored object. The response and list API never expose the object key or local path.

Until an identity-to-supplier ownership mapping is available, document upload is restricted to `supplier-admin`; this prevents a generic onboarding role from submitting to arbitrary supplier IDs. Read access is limited to procurement/reviewer/admin roles. Every new version currently starts with `scanStatus=PENDING` and `reviewStatus=PENDING`. There is deliberately no download endpoint, no automatic CLEAN state and no claim of malware protection.

This slice has been committed but still requires local Oracle/API integration tests and CI verification. The implementation uses a supplier-row lock to serialize the initial document ID allocation per supplier. Pagination, explicit document type requirement management, full version history, reviewer decisions and safe release/download remain follow-up work.

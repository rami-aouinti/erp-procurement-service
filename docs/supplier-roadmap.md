# Supplier Management — Product Roadmap & Delivery Tracker

> **Purpose:** this document is the source of truth for the Supplier Management / Supplier Relationship Management (SRM) roadmap. It separates verified implementation from planned work. A feature is not considered delivered because it is described here or because an issue exists.

## 1. Product vision

Evolve the current supplier master-data module into an enterprise-grade SRM capability covering the supplier lifecycle:

1. Discover/register a supplier.
2. Validate and approve the supplier.
3. Maintain company, contact, address and payment information securely.
4. Collect compliance evidence and manage contracts.
5. Monitor delivery, quality, commercial performance and risk.
6. Collaborate with suppliers through a controlled portal.
7. Connect supplier information to purchase orders, goods receipts, invoices and payment systems.
8. Provide auditable reporting and operational visibility.

The module remains part of the modular Symfony application. Do not split it into microservices without measured operational or organizational need.

## 2. Verified current implementation

The following is based on the current `main` source tree, Supplier PHP classes, Oracle migrations, tests and existing documentation inspected while preparing this roadmap.

### 2.1 Implemented capabilities

| Capability | Current implementation | Status / boundary |
|---|---|---|
| Supplier master CRUD | `SupplierController`, `CreateSupplier`, `UpdateSupplier`, `GetSupplier`, `ListSuppliers`, `DeleteSupplier`, domain `Supplier` | **Implemented baseline.** Existing status enum is ACTIVE / INACTIVE / BLOCKED; all legacy Supplier routes now require Keycloak authentication and role authorization. |
| Validation and duplicate protection | `SupplierDataValidator`, value objects for name/email/phone, repository email existence check | **Implemented.** Email duplicate protection exists; broader legal-entity duplicate detection is future work. |
| Supplier profile | `SupplierProfile`, profile repository/use cases/controller and Oracle migration 009 | **Implemented baseline.** Legal name, registration number, VAT number, website, language, currency and payment terms. |
| Multiple contacts | `SupplierContact`, `ManageSupplierProfileRelations`, profile repository and `supplier_contacts` | **Implemented.** Listing, adding, editing and deleting contacts; primary-contact consistency is transactional. |
| Multiple addresses | `SupplierAddress`, `ManageSupplierProfileRelations`, profile repository and `supplier_addresses` | **Implemented.** Listing, adding, editing and deleting addresses; default-address consistency is transactional. |
| Bank accounts | `SupplierBankAccount`, `ManageSupplierBankAccounts`, `SupplierBankDataEncryptor`, encrypted Oracle columns | **Implemented.** IBAN validation, AES-256-GCM encryption, masked responses, primary account selection/deletion and key-version metadata; backend access is restricted to supplier-finance or supplier-admin. |
| Bank encryption operations | `supplier:bank-accounts:rotate-encryption` command and key-version metadata | **Implemented.** Operational key backup/rotation procedures still require disciplined deployment controls. |
| Supplier search | `SearchSuppliers`, `RedisSupplierListCache`, `ElasticsearchSupplierSearchIndex` | **Implemented.** Redis cache, Elasticsearch search and Oracle fallback; current indexed fields are documented in the root README. |
| Reliable event publication | `supplier_outbox_events`, `PublishSupplierOutboxCommand`, `RabbitMqSupplierEventBus` | **Implemented.** Same-Oracle-transaction outbox, publisher confirms, row claims, lease recovery, exponential retry and terminal FAILED state. |
| Event consumption | `ConsumeSupplierEventsCommand`, `HandleSupplierEvent`, `supplier_consumed_events` | **Implemented.** At-least-once delivery, persistent deduplication, Oracle reload, Elasticsearch update and Redis invalidation. Not exactly-once delivery. |
| Dead-letter recovery | retry queue, `supplier.events.dead`, `supplier:events:dead-letter:replay` | **Implemented.** Bounded replay after correcting the underlying cause. |
| API and test documentation | Supplier Swagger/OpenAPI, unit/functional/integration test suites | **Implemented baseline.** See `backend/docs/supplier-testing-and-swagger.md`. |
| Audit trail | `supplier_audit_logs` + `SupplierAuditLogger` | **Partial.** Supplier, profile, contact, address and bank-account lifecycle changes are written in the same Oracle transaction. Only changed field names are stored; no before/after values or financial data. HTTP requests are currently attributed as ANONYMOUS because authentication/actor identity is not configured. |
| Operational metrics | Supplier event/outbox metrics exposed on `/metrics` and Grafana overview | **Implemented baseline.** Includes published/consumed/deduplicated/failed/dead-letter counters and pending/failed/oldest-pending gauges. |

### 2.2 Important architecture rules already in place

- **Oracle is the system of record** for supplier master data and profile relationships.
- **Redis is a disposable read cache**, not a source of truth. Cache failure must not prevent normal supplier reads/writes.
- **Elasticsearch is a search read model**, not a source of truth. Search must have a defined Oracle fallback.
- **RabbitMQ is transport**, not durable business storage. The Oracle Outbox is the durable publication intent.
- **Delivery is at least once.** Consumers must remain idempotent; do not claim exactly-once delivery.
- Consumers reload the current supplier from Oracle before synchronizing search/cache read models. Event payloads must not be treated as the latest aggregate state.
- Sensitive bank account values must never be sent to Elasticsearch, logs, metrics, or general-purpose event payloads.

### 2.3 Current implementation gaps (do not describe these as already delivered)

- Supplier onboarding is **PARTIAL**: Keycloak RS256 JWT validation, role-protected candidate submission/review endpoints, Oracle transition history, transactional outbox events, purchase-order eligibility trigger, React OIDC login (Authorization Code + PKCE), candidate submission form and reviewer queue/decision/history screens are implemented. Legacy Supplier/profile/contact/address/bank-account routes now also enforce backend authentication and role authorization. Remaining: broader end-to-end and Oracle concurrency tests. Existing ACTIVE / INACTIVE / BLOCKED status is not equivalent to a review workflow.
- No supplier document repository, malware-scanning lifecycle or compliance checklist is currently implemented.
- A privacy-safe append-only audit baseline is implemented for Supplier master data, profiles, contacts, addresses and bank accounts. Actor identity is still unresolved until authentication/RBAC is introduced; audit records currently identify HTTP requests as ANONYMOUS and CLI/worker activity as SYSTEM.
- No contract lifecycle module is currently implemented.
- No evidence-backed performance scorecard or risk assessment module is currently implemented.
- No supplier-facing self-service portal is currently implemented.
- No complete supplier notification/scheduler workflow for expiry and review reminders is currently implemented.
- No end-to-end supplier spend analytics / invoice-payment integration is currently implemented.
- SUP-F02 classification is implemented and locally verified: normalized Oracle taxonomy, assignments, role-protected APIs, Elasticsearch/Oracle search filters, Redis invalidation and React management/assignment screens; the user confirmed all test suites pass on 2026-11-25.

## 3. Feature status overview

Status values:
- **DONE** — code, tests, documentation and verification are complete.
- **PARTIAL** — a useful baseline exists, but the stated feature is not complete.
- **PLANNED** — not implemented; tracked by an issue.
- **BLOCKED** — depends on an unresolved decision or prerequisite.

| ID | Feature | Status | Tracking issue | Main dependency |
|---|---|---:|---|---|
| BASE | Supplier CRUD, validation, search, profile, contacts, addresses, bank accounts | PARTIAL / existing baseline | [Epic #3](https://github.com/rami-aouinti/erp-procurement-service/issues/3) | Existing module |
| F01 | Supplier onboarding and approval workflow | PARTIAL | [#4](https://github.com/rami-aouinti/erp-procurement-service/issues/4) | Broader end-to-end/Oracle concurrency tests |
| F02 | Supplier categories, classification and segmentation | DONE — local test suites confirmed passing | [#5](https://github.com/rami-aouinti/erp-procurement-service/issues/5) | Delivered |
| F03 | Supplier documents and compliance tracking | PARTIAL — Oracle metadata foundation and security namespace committed; lifecycle pending | [#6](https://github.com/rami-aouinti/erp-procurement-service/issues/6) | Private object storage + malware scanning |
| F04 | Supplier audit trail and change history | PARTIAL | [#7](https://github.com/rami-aouinti/erp-procurement-service/issues/7) | Authenticated actor identity + retention policy + audit read API |
| F05 | Supplier contract lifecycle | PLANNED | [#8](https://github.com/rami-aouinti/erp-procurement-service/issues/8) | F03 document storage + reminders |
| F06 | Supplier performance scorecards | PLANNED | [#9](https://github.com/rami-aouinti/erp-procurement-service/issues/9) | Reliable PO/receipt/quality data |
| F07 | Supplier risk assessment and alerts | PLANNED | [#10](https://github.com/rami-aouinti/erp-procurement-service/issues/10) | Risk model + audit |
| F08 | Supplier notifications and scheduled reminders | PLANNED | [#11](https://github.com/rami-aouinti/erp-procurement-service/issues/11) | Notification provider + scheduler |
| F09 | Supplier self-service portal and scoped access | PLANNED | [#12](https://github.com/rami-aouinti/erp-procurement-service/issues/12) | Identity provider + RBAC + F01 |
| F10 | Purchase-order, invoice and payment integration | PLANNED | [#13](https://github.com/rami-aouinti/erp-procurement-service/issues/13) | Data ownership and AP contract |
| F11 | Supplier analytics and spend reporting | PLANNED | [#14](https://github.com/rami-aouinti/erp-procurement-service/issues/14) | F06/F07/F10 source data |

The GitHub feature issues contain the detailed implementation task checklist and acceptance criteria. Keep the issue and this document synchronized when scope or status changes.

## 4. Delivery sequence

This order is a dependency-aware proposal, not a promise that every feature must be delivered before the next one.

### Foundation hardening (before expanding workflows)
- [ ] Implement contact/address update and delete operations (currently add/list only; tracked as remaining foundation work).
- [x] Return Oracle-generated IDs from contact/address creation responses.
- [x] Serialize contact/address/bank-account primary/default changes by locking the parent supplier row inside the write transaction.
- [ ] Run the new Oracle functional regression test locally and confirm the CI result.
- [ ] Review profile/bank-account permissions and authorization tests.
- [ ] Add consistent pagination, filtering and API error contracts to relationship endpoints.
- [ ] Confirm supplier deletion policy (hard delete vs deactivate/archive) in the presence of audit, contracts, documents and purchase history.
- [ ] Define actor identity and permission model needed by approvals and audit.
- [ ] Add database migration inventory/version tracking and documented rollback approach.

### Wave 1 — Core governance
- [~] **F01:** Backend workflow, Keycloak roles, audit/history, outbox, PO eligibility guard and React OIDC candidate/reviewer UI implemented; legacy API authorization and full end-to-end/Oracle concurrency coverage remain.
- [x] **F02:** Normalized categories, commodity codes, tags, business units, strategic tier, supplier assignment UI, taxonomy administration and filtered search implemented; user confirmed all test suites pass locally.
- [ ] **F04:** Audit trail (start early; onboarding and sensitive changes depend on it).

### Wave 2 — Compliance and commercial lifecycle
- [~] **F03:** Oracle metadata schema, CI initialization and protected API namespace are in place; storage, scan/review lifecycle, API and UI remain.
- [ ] **F05:** Contract lifecycle.
- [ ] **F08:** Notifications and scheduled reminders.

### Wave 3 — Intelligence
- [ ] **F06:** Performance scorecards after source metrics are reliable.
- [ ] **F07:** Risk assessments and alerts.

### Wave 4 — Ecosystem and reporting
- [ ] **F09:** Supplier portal after identity isolation and approval controls are proven.
- [ ] **F10:** PO/invoice/payment integration after data ownership is documented.
- [ ] **F11:** Analytics after authoritative spend, performance and risk data is available.

## 5. Ticket quality standard

Every feature issue must contain all of the following sections. The existing SUP-F01 … SUP-F11 issues follow this format.

1. **Goal** — business outcome and reason for the work.
2. **User stories** — who needs the capability and what they need to do.
3. **Scope and boundaries** — what is included and what is explicitly excluded.
4. **Implementation tasks** — concrete checkboxes, including database, domain/application, infrastructure, API/UI, security, tests and documentation as applicable.
5. **Acceptance criteria** — observable behavior that must be true before closing.
6. **Failure cases** — expected behavior for unavailable dependencies, invalid input, duplicate requests and concurrency.
7. **Security/privacy** — access control, secrets, sensitive data, retention and audit requirements.
8. **Operational readiness** — metrics, logs, retries, recovery and runbooks where asynchronous work is involved.
9. **Migration strategy** — additive schema change, backfill, compatibility, rollout and rollback.

Do not create vague tasks such as “implement backend” or “add tests”. Break work into reviewable outcomes and state what must be verified.

## 6. Definition of Ready

A feature is ready to start only when:
- [ ] Business owner/persona and problem are clear.
- [ ] Scope and non-goals are explicit.
- [ ] State transitions and business invariants are agreed where applicable.
- [ ] Data ownership and source of truth are identified.
- [ ] API contract and authorization rules are sketched.
- [ ] Migration/backfill strategy is understood.
- [ ] Dependencies and external providers are identified.
- [ ] Acceptance criteria are testable.

## 7. Definition of Done

A feature can be marked **DONE** only when all applicable items pass:
- [ ] Domain rules and application use cases implemented with clear dependency direction.
- [ ] Oracle schema changes are additive, constrained, indexed where justified, and documented.
- [ ] API routes, validation, error responses and OpenAPI examples are updated.
- [ ] Frontend flows include loading, empty, error and permission-denied states.
- [ ] Authorization is enforced server-side and tested (including cross-supplier/IDOR cases where relevant).
- [ ] Unit tests cover domain rules and application behavior.
- [ ] Functional tests cover Oracle persistence and HTTP behavior.
- [ ] Integration tests cover RabbitMQ/Redis/Elasticsearch/object storage/provider behavior where relevant.
- [ ] Failure and concurrency cases are tested or documented as controlled operational tests.
- [ ] Logs and metrics are useful, structured and free of secrets/PII.
- [ ] Runbooks cover setup, migration, recovery, replay and rollback.
- [ ] README and feature documentation reflect the actual implementation.
- [ ] Docker Compose verification and relevant CI checks pass.
- [ ] This roadmap status and GitHub issue checklist are updated together.

## 8. Cross-cutting engineering standards

### Architecture
- Keep the module modular inside Symfony; avoid premature microservices.
- Domain must not depend on Symfony, Doctrine, RabbitMQ, Redis or Elasticsearch.
- Application use cases depend on domain contracts/ports.
- Infrastructure adapters implement ports; Transport handles HTTP/CLI.
- Keep controllers thin and DTOs explicit.
- Use explicit transactions for multi-row business invariants.

### Persistence and migrations
- Oracle is authoritative for supplier data.
- Prefer normalized relational tables and foreign keys over serialized arrays.
- Add constraints for invariants that must hold under concurrency.
- Add indexes based on access patterns and explain plans, not speculation.
- Migrations must preserve existing suppliers and purchase history.
- For Oracle DDL, document implicit commit behavior, preflight checks and rollback/forward-fix plan.
- Never run destructive schema changes automatically in application startup.

### Events and asynchronous processing
- Use the transactional Outbox when a committed business change must reliably trigger asynchronous work.
- Publish persistent messages and use publisher confirms.
- Assume at-least-once delivery; every consumer side effect must be idempotent or safely repeatable.
- Use bounded retries/backoff and DLQ; document how to inspect and replay.
- Keep event schemas versioned and payloads minimal. Never publish secrets, bank plaintext or file contents.
- Track backlog age, failed events, retries and DLQ depth.

### Search and caching
- Oracle remains the source of truth.
- Redis cache must have TTL and explicit invalidation; cache loss must be safe.
- Elasticsearch is rebuildable from Oracle; provide reindex/recovery commands.
- Search fallback behavior and result consistency must be tested.
- Never index bank account data, credentials or document contents.

### Security and privacy
- Apply least privilege and server-side authorization to every resource.
- Protect external supplier access against IDOR and tenant/supplier boundary bypass.
- Encrypt sensitive financial data; mask it in API responses.
- Keep encryption keys in secret management, version keys and test rotation/recovery.
- Validate uploaded files by content signature and scan/quarantine before use.
- Redact sensitive data from logs, events, metrics, traces and audit diffs.
- Define retention, deletion, export and access review requirements before launch.

### API and frontend
- Use stable resource-oriented REST contracts and consistent status/error responses.
- Validate input at boundaries and enforce business invariants in application/domain.
- Paginate collection endpoints; set maximum page sizes and bounded filters.
- Document authorization requirements and examples in OpenAPI.
- Frontend must distinguish loading, empty, error, forbidden and stale data states.

### Testing and delivery
- Unit tests: domain and use cases, fast and isolated.
- Functional tests: Symfony HTTP + Oracle.
- Integration tests: real dependencies and asynchronous pipeline.
- Security tests: permissions, IDOR, injection, file upload and sensitive-data redaction.
- Failure tests: broker outage, retry exhaustion, stale event, duplicate delivery, provider outage.
- CI must run relevant suites and schema validation.
- Track local verification separately from CI; never claim tests passed unless they were run.

## 9. Operational checklist for every asynchronous feature

- [ ] Worker/command name and deployment model documented.
- [ ] Retry count, backoff, timeout and terminal state defined.
- [ ] Idempotency key and duplicate behavior defined.
- [ ] DLQ or failed-record recovery path documented.
- [ ] Metrics for processed, failed, retried, backlog count and oldest age exposed where useful.
- [ ] Structured logs include correlation/event IDs but no sensitive payload.
- [ ] Health/readiness behavior defined.
- [ ] Runbook explains inspect, retry, replay and safe recovery.

## 10. Current next action

Continue F03 by implementing private local and S3-compatible object storage, streaming upload validation, malware quarantine/scanning, metadata persistence and supplier-scoped authorization. Migration 026 is additive and is initialized by CI after migration 025. Do not run the destructive development reset merely to apply this migration.

## 11. Change log

| Date | Change | Evidence |
|---|---|---|
| 2026-11-25 | Implemented and locally verified SUP-F02; user confirmed all test suites pass. | Migration 025, Supplier classification services/controller, `SupplierClassificationOracleTest`, API/event tests, frontend pages and `docs/supplier-classification.md` |
| 2026-11-25 | Foundation hardening started: contact/address create responses now return persisted Oracle IDs; profile writes lock the supplier row to serialize primary/default changes; functional regression test added. Local Oracle test and CI verification still pending. | Commits on main: repository fix + SupplierApiTest regression coverage |
| 2026-11-25 | Initial roadmap created after reviewing current `main` Supplier source, Oracle migrations, tests and docs. Existing implementation and planned features separated explicitly. | GitHub issues [#3–#14](https://github.com/rami-aouinti/erp-procurement-service/issues) |
| 2026-11-25 | Added privacy-safe append-only audit persistence for Supplier, profile, contact, address and bank-account mutations. Added repeatable Supplier/procurement fixtures and a guarded development reset workflow. Local Oracle verification is pending. | Migration 020, `SupplierAuditLogger`, `021`/`022` fixtures, `scripts/dev-reset.sh`, audit functional assertions |

| 2026-11-25 | Started SUP-F03: added additive Oracle document metadata, requirement, logical document and immutable version tables; wired migration 026 into CI and protected the supplier-document API namespace. Added architecture, lifecycle, security and verification documentation. Remaining storage, scanner, API, UI and local verification are explicitly not marked complete. | `database/oracle/026_supplier_documents_compliance.sql`, CI workflow, `KeycloakRequestSubscriber`, `docs/supplier-documents-compliance.md` |

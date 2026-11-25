# ERP Procurement Service

A hands-on full-stack ERP procurement and inventory project built to learn enterprise workflows, Oracle Database, SQL/PLSQL, PHP, Symfony and React.

## Project goals

Build a realistic procurement workflow:

1. Manage suppliers and products.
2. Create purchase orders with order lines.
3. Receive goods and update inventory.
4. Track inventory movements.
5. Register and reconcile supplier invoices.
6. Explore Oracle transactions, constraints, indexes, PL/SQL and query plans.
7. Expose business workflows through a Symfony REST API.
8. Build an ERP user interface with React.

## Architecture

- **Frontend:** React + Vite (JavaScript), port 5173.
- **Backend:** Symfony 7.4 on PHP 8.4, port 8000.
- **Database:** Oracle Database Free, service `FREEPDB1`, port 1521.
- **Communication:** HTTP/JSON. Vite proxies `/api` requests to Symfony during development.

The application starts as one modular Symfony backend. We will keep clear boundaries inside the application instead of prematurely splitting it into microservices.

## Current status

**Oracle foundation**

- [x] Docker Compose definition for Oracle Database Free
- [x] Persistent database volume
- [x] Initial procurement schema and demo data scripts
- [x] Practical SQL query exercises
- [x] Goods receipt and inventory tables
- [x] PL/SQL goods-receiving procedure and manual test
- [x] Local Oracle database initialized and goods-receiving workflow tested

**Full-stack foundation**

- [x] Symfony 7.4 application skeleton
- [x] Symfony health endpoint at `/api/health`
- [x] React + Vite application skeleton
- [x] React dashboard starter that calls the Symfony health endpoint
- [x] Docker Compose services for backend and frontend
- [ ] Start and verify the full stack locally
- [ ] Validate the PHP Oracle driver and connect Symfony to Oracle
- [x] Implement supplier CRUD API with profile details and status filters
- [ ] Implement products API
- [ ] Implement purchase orders and goods-receiving API
- [x] Build supplier management React screen with search, status filters, client-side pagination and edit/create forms
- [ ] Build product and purchase-order screens

## Requirements

- Docker Desktop or Docker Engine with the Compose plugin
- Git

No local PHP, Composer or Node.js installation is required for the Docker-based development workflow.

## Quick start

1. Copy the environment template if you have not already done so:

   ```bash
   cp .env.example .env
   ```

   Keep the passwords already used by your existing Oracle container. Do not replace them with the example values if your database has already been initialized.

2. Start all services:

   ```bash
   docker compose up -d --build
   docker compose ps
   ```

   The first start installs Composer and npm dependencies inside the development containers. It can take a little longer.

3. Open the applications:

   - React: http://localhost:5173
   - Symfony health endpoint: http://localhost:8000/api/health
   - Oracle listener: localhost:1521

4. Follow logs when needed:

   ```bash
   docker compose logs -f backend frontend
   ```

5. Stop the application without deleting database data:

   ```bash
   docker compose down
   ```

   **Warning:** never use `docker compose down -v` unless you intentionally want to delete the Oracle data volume.

## Oracle setup

Oracle data is stored in the named Docker volume `erp_oracle_data`. Stopping or recreating the container does not remove this volume.

Connection settings:

| Setting | Value |
|---|---|
| Host from your computer | `localhost` |
| Host from another Compose container | `oracle` |
| Listener port | `1521` |
| Service name | `FREEPDB1` |
| Application user | Value of `APP_USER` in `.env` |
| Application password | Value of `APP_USER_PASSWORD` in `.env` |

The application user is created by the Oracle container image. Connect to the pluggable database service `FREEPDB1`, not to the container database root.

### SQL scripts

The scripts in `database/oracle` build the procurement schema and its sample workflow:

- `001_create_procurement_schema.sql` — suppliers, products and purchase orders.
- `002_seed_procurement_data.sql` — repeatable sample data using Oracle MERGE.
- `003_practice_queries.sql` — joins, aggregation and reporting exercises.
- `004_create_goods_receiving.sql` — goods receipts, inventory, movement history and the PL/SQL receiving procedure.
- `005_test_goods_receiving.sql` — manual example for testing goods receiving.
- `006_create_suppliers_sequence.sql` — sequence used by Doctrine with the OCI8 driver.
- `007_extend_supplier_management.sql` — additive supplier status, contact and address columns. Existing data is preserved.
- `008_create_supplier_outbox.sql` — transactional outbox for reliable Supplier events.
- `009_supplier_profile_management.sql` — supplier profile tables and constraints.
- `010_create_supplier_consumed_events.sql` — persistent consumer idempotency ledger.
- `011_harden_goods_receiving.sql` — additive hardening for existing databases: indexes receipt-line lookups and switches receipt numbers to an Oracle sequence.
- `012_enforce_receipt_order_consistency.sql` — adds composite foreign keys so each receipt line must belong to the same order as its receipt document and purchase-order line.
- `013_test_goods_receiving_guards.sql` — non-destructive negative-path tests for invalid quantities, invalid order statuses and unknown SKUs; it must run in a dedicated SQL*Plus session.
- `014`–`018` — isolated two-session concurrency test fixture, session scripts, verification and cleanup.
- `019_fix_supplier_profile_conditional_unique_indexes.sql` — repairs Oracle function-based unique indexes for primary contacts and default addresses.
- `020_create_supplier_audit_trail.sql` — creates the append-only, privacy-safe Supplier audit log.
- `021_seed_supplier_profile_fixtures.sql` and `022_seed_goods_receiving_fixtures.sql` — repeatable supplier profile, contact, address, receipt and inventory fixtures.
- `030_reset_dev_data.sql` — development-only Oracle business-data cleanup; run through the guarded `scripts/dev-reset.sh` orchestrator.
- `031_verify_dev_fixtures.sql` — asserts expected entity counts and partial/complete receiving scenarios after seeding.
- `test_goods_receiving_concurrency.sh` — orchestrates two independent SQL*Plus sessions; run it from the repository root with `bash database/oracle/test_goods_receiving_concurrency.sh`.

Run a script from the repository root, for example:

```bash
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/004_create_goods_receiving.sql
```

The initial schema-creation scripts are intended for their documented order. Do not rerun non-idempotent schema scripts against an already initialized database. For an existing database, apply only the additive migrations that match the features already installed. The goods-receiving hardening migration below preserves existing business data:

```bash
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/007_extend_supplier_management.sql

# Apply once to an existing database that already has goods-receiving objects
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/011_harden_goods_receiving.sql

# Apply once after 011 to enforce receipt/order consistency
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/012_enforce_receipt_order_consistency.sql

# Run non-destructive rejection tests in a dedicated SQL*Plus session
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/013_test_goods_receiving_guards.sql

# Run the two-session concurrency test (creates and then removes isolated test data)
bash database/oracle/test_goods_receiving_concurrency.sh
```

## Symfony backend

The Symfony application lives in `backend/`.

Initial structure:

- `src/Controller` — HTTP controllers.
- `config/` — Symfony configuration.
- `public/index.php` — application entry point.
- `tests/` — automated tests (to be expanded with the first business feature).

The initial endpoint is:

```http
GET /api/health
```

It returns a small JSON response and is used by the React starter screen.

**Oracle integration is intentionally a separate next step.** Before installing Doctrine ORM or choosing a DBAL driver, we will validate the available PHP Oracle extension (OCI8 or PDO_OCI) with PHP 8.4. The PL/SQL receiving procedure currently manages its own COMMIT/ROLLBACK, so we will preserve that transaction boundary when calling it from PHP.

## React frontend

The React application lives in `frontend/` and uses Vite.

Learning path:

1. JSX and components.
2. Props and component composition.
3. State with `useState`.
4. Side effects and API calls with `useEffect`.
5. Forms and validation.
6. React Router for application pages.
7. Reusable hooks and API service modules.

Vite proxies requests beginning with `/api` to the Symfony backend, so browser code can call relative URLs such as `fetch('/api/health')`.

## Learning approach

This is an educational project. We will implement business rules incrementally and document the enterprise concepts used by each workflow. Credentials in this repository are examples only; use local secrets in `.env`.


## Supplier search infrastructure

The supplier module uses three complementary storage systems:

- **Oracle** remains the source of truth for supplier data and relationships.
- **Redis** caches supplier search/list responses for 60 seconds. Supplier writes invalidate the supplier cache.
- **Elasticsearch** provides multi-field supplier search over name, email, tax number, contact person, phone and address.

Redis and Elasticsearch run as Docker Compose services with persistent named volumes and health checks. The backend image includes the PHP Redis extension. If Redis is unavailable, requests bypass the cache. If Elasticsearch is unavailable, supplier search falls back to Oracle.

After starting the stack for the first time, populate the search index from Oracle:

```bash
docker compose exec backend php bin/console supplier:reindex
```

Useful local endpoints:

- Redis: `localhost:6379`
- Elasticsearch: `http://localhost:9200`
- Elasticsearch health: `http://localhost:9200/_cluster/health`
- Supplier index: `http://localhost:9200/suppliers/_search`

The local Elasticsearch container has security disabled for development only. Its host port is bound to localhost; do not expose this configuration to a public or production network. If Elasticsearch exits with a `vm.max_map_count` bootstrap error on Linux/WSL, raise the host setting to `262144` before starting the stack.


## Reliable supplier synchronization (Oracle + Outbox + RabbitMQ)

Supplier writes append a durable event to `supplier_outbox_events` in the same Oracle transaction as the supplier row. A publisher claims pending outbox rows and publishes persistent messages to RabbitMQ with publisher confirms. A separate consumer updates Elasticsearch and invalidates Redis. RabbitMQ uses durable exchanges and queues, a delayed retry queue (five retries), and a dead-letter queue for messages that still fail. The outbox publisher retries broker outages with exponential backoff and marks events FAILED after ten unsuccessful publish attempts. Delivery is at-least-once, not exactly-once. The consumer reloads the current supplier from Oracle before updating the read model and uses `supplier_consumed_events` to persist processed event IDs. A duplicate already marked `PROCESSED` is acknowledged without repeating read-model work; a live processing claim is retried rather than acknowledged.

Apply the additive outbox schema once to the existing Oracle application schema:

```bash
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/008_create_supplier_outbox.sql
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/010_create_supplier_consumed_events.sql
```

Set `RABBITMQ_USER` and `RABBITMQ_PASSWORD` in your local `.env` (copy the values from `.env.example` and choose your own password). Rebuild and start the stack:

```bash
docker compose up -d --build
docker compose ps
```

RabbitMQ management UI: http://localhost:15672 (use the credentials from `.env`). The outbox publisher and event consumer run as separate Compose services. Useful checks:

```bash
docker compose logs -f supplier-outbox-publisher supplier-events-consumer
docker compose exec backend php bin/console supplier:outbox:publish --limit=100
docker compose exec backend php bin/console supplier:outbox:retry-failed
docker compose exec oracle bash -lc 'echo "SELECT status, COUNT(*) FROM supplier_outbox_events GROUP BY status;" | sqlplus -s "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"'
```

The RabbitMQ dead-letter queue is `supplier.events.dead`; inspect it in the management UI and only requeue messages after the underlying consumer error is corrected. Outbox rows marked `FAILED` can be reset with `supplier:outbox:retry-failed`, optionally passing a specific 32-character event ID.


## Observability (Prometheus + Grafana + Loki)

The local Docker Compose stack includes Prometheus, Grafana, Loki, Grafana Alloy, cAdvisor and Node Exporter.

- Grafana dashboard: http://localhost:3000
- Prometheus UI and alert rules: http://localhost:9090
- Symfony application metrics: http://localhost:8000/metrics
- cAdvisor container metrics: http://localhost:8088
- Node Exporter metrics: http://localhost:9100/metrics

Add \`GRAFANA_ADMIN_USER\` and a strong \`GRAFANA_ADMIN_PASSWORD\` to your local \`.env\` before starting the stack. Prometheus automatically scrapes backend HTTP metrics and infrastructure exporters. The provisioned **ERP Procurement - Overview** dashboard includes backend availability, request rate, 5xx rate, p95 latency, container CPU and memory. Prometheus also evaluates backend-down, high-error-rate and high-latency alert rules. Alloy collects Docker stdout/stderr logs into Loki, and the dashboard includes a centralized container-log panel. Symfony HTTP logs are structured JSON with a request ID.

See [docs/observability.md](docs/observability.md) for setup, metric definitions, LogQL/PromQL examples, limitations and security notes. The broader [project audit](docs/project-audit.md) lists fixed findings and remaining production-readiness work.


## Load testing with k6

A fixed-load k6 scenario with 20 virtual users is available in `k6/health.js`. Run it with:

```bash
docker compose --profile loadtest run --rm k6
```

See [Cloud deployment and load testing](docs/cloud-deployment.md) for thresholds and Kubernetes instructions.

## Kubernetes cloud deployment

Cloud-oriented Dockerfiles and starter Kubernetes manifests are provided in `backend/Dockerfile.cloud`, `frontend/Dockerfile` and `k8s/erp.yaml`. The manifests assume managed Oracle, Redis, Elasticsearch and RabbitMQ endpoints; configure the ConfigMap and Kubernetes Secret before deploying. See [docs/cloud-deployment.md](docs/cloud-deployment.md).


## Supplier Management roadmap and delivery tracking

The Supplier module is being evolved toward an enterprise Supplier Relationship Management (SRM) capability. The roadmap explicitly distinguishes **already implemented capabilities** from **planned work**; a feature is not considered delivered until its code, tests, documentation and verification are complete.

### Verified current baseline

- [x] Supplier master CRUD, ACTIVE / INACTIVE / BLOCKED status, input validation and duplicate-email protection.
- [x] Supplier company profile, including legal name, registration/VAT numbers, website, preferred language/currency and payment terms.
- [x] Supplier contacts and addresses (current baseline supports listing and adding; see roadmap for lifecycle hardening).
- [x] Bank accounts with IBAN validation, AES-256-GCM encryption, masked API responses, primary-account management and key-version metadata.
- [x] Oracle as source of truth; Redis read cache; Elasticsearch search with Oracle fallback.
- [x] Transactional Outbox, RabbitMQ publisher confirms, bounded retries, lease recovery, consumer idempotency and DLQ replay.
- [x] Supplier event/outbox metrics and automated test suites.

### Planned feature backlog

| Feature | Status | Ticket |
|---|---|---|
| Supplier onboarding and approval workflow | Planned | [SUP-F01 #4](https://github.com/rami-aouinti/erp-procurement-service/issues/4) |
| Supplier categories and classification | Planned | [SUP-F02 #5](https://github.com/rami-aouinti/erp-procurement-service/issues/5) |
| Documents and compliance tracking | Planned | [SUP-F03 #6](https://github.com/rami-aouinti/erp-procurement-service/issues/6) |
| Audit trail and change history | Planned | [SUP-F04 #7](https://github.com/rami-aouinti/erp-procurement-service/issues/7) |
| Contract lifecycle management | Planned | [SUP-F05 #8](https://github.com/rami-aouinti/erp-procurement-service/issues/8) |
| Performance scorecards | Planned | [SUP-F06 #9](https://github.com/rami-aouinti/erp-procurement-service/issues/9) |
| Risk assessment and alerts | Planned | [SUP-F07 #10](https://github.com/rami-aouinti/erp-procurement-service/issues/10) |
| Notifications and scheduled reminders | Planned | [SUP-F08 #11](https://github.com/rami-aouinti/erp-procurement-service/issues/11) |
| Supplier self-service portal | Planned | [SUP-F09 #12](https://github.com/rami-aouinti/erp-procurement-service/issues/12) |
| Purchase-order, invoice and payment integration | Planned | [SUP-F10 #13](https://github.com/rami-aouinti/erp-procurement-service/issues/13) |
| Supplier analytics and spend reporting | Planned | [SUP-F11 #14](https://github.com/rami-aouinti/erp-procurement-service/issues/14) |

**Epic:** [Evolve Supplier Management into an enterprise SRM module (#3)](https://github.com/rami-aouinti/erp-procurement-service/issues/3).

Each feature ticket contains its goal, implementation checklist, concrete acceptance criteria, dependencies and relevant security/testing/documentation requirements. Keep the issue checklist and roadmap status synchronized as work is delivered.

See **[docs/supplier-roadmap.md](docs/supplier-roadmap.md)** for the full current-state audit, architecture rules, feature dependencies, delivery waves, Definition of Ready/Done, migration/security/testing standards and change log. Existing technical documentation remains in [backend/docs/supplier-architecture.md](backend/docs/supplier-architecture.md), [backend/docs/supplier-clean-architecture.md](backend/docs/supplier-clean-architecture.md), [backend/docs/supplier-testing-and-swagger.md](backend/docs/supplier-testing-and-swagger.md) and [docs/supplier-profile-management.md](docs/supplier-profile-management.md).


## Development fixtures and full reset

The repository contains a repeatable demo dataset for the procurement and Supplier workflows:

- 5 suppliers covering ACTIVE, INACTIVE and BLOCKED statuses.
- Supplier company profiles, 10 contacts and 10 addresses.
- 5 encrypted demo bank accounts created through the application encryption service.
- 8 products, 5 purchase orders and purchase-order lines.
- Goods receipts covering partial and complete receiving, plus inventory balances and movements.
- 35 privacy-safe audit events covering the seeded suppliers, profiles, contacts, addresses and encrypted bank accounts.

After the full Oracle schema has been installed (including migration 019), run the following command to reset local development data. The script applies audit migration 020 automatically:

```bash
bash scripts/dev-reset.sh --yes
```

The script requires the backend container to report `APP_ENV=dev`, uses the `FREEPDB1` service and refuses to run as SYS. Ensure `.env` contains a valid base64-encoded 32-byte `SUPPLIER_BANK_ENCRYPTION_KEY`; the fixtures use the real AES-256-GCM application service. It stops the Supplier workers, deletes business rows in foreign-key-safe order, reseeds the data, creates encrypted bank fixtures, clears Redis and Symfony's dev cache, purges Supplier RabbitMQ queues, recreates the Elasticsearch Supplier index from Oracle and restarts the workers.

**Destructive command:** this removes all ERP business data in the configured development schema, including suppliers, products, purchase orders, goods receipts, inventory, outbox/consumer state and audit history. It preserves the Oracle schema, migrations, sequences, Docker volumes and monitoring history. Do not run it against production or a shared database.

The script applies the idempotent audit schema migration 020. It expects the rest of the schema to be installed already; it does not bootstrap a brand-new Oracle database.

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

Run a script from the repository root, for example:

```bash
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/004_create_goods_receiving.sql
```

The initial schema-creation scripts are intended for their documented order. Do not rerun non-idempotent schema scripts against an already initialized database. For an existing database, apply only the new additive supplier extension:

```bash
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/007_extend_supplier_management.sql
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

Supplier writes append a durable event to `supplier_outbox_events` in the same Oracle transaction as the supplier row. A publisher claims pending outbox rows and publishes persistent messages to RabbitMQ with publisher confirms. A separate consumer updates Elasticsearch and invalidates Redis. RabbitMQ uses durable exchanges and queues, a delayed retry queue (five retries), and a dead-letter queue for messages that still fail. The outbox publisher retries broker outages with exponential backoff and marks events FAILED after ten unsuccessful publish attempts. Delivery is at-least-once: the consumer reloads the current supplier from Oracle before updating the read model, so duplicate and delayed create/update events cannot overwrite newer data or recreate a deleted supplier.

Apply the additive outbox schema once to the existing Oracle application schema:

```bash
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/008_create_supplier_outbox.sql
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

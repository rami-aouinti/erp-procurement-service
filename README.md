# ERP Procurement Service

A hands-on ERP procurement and inventory project built to learn enterprise workflows, Oracle Database, SQL/PLSQL, PHP and Symfony.

## Project goals

Build a realistic procurement workflow:

1. Manage suppliers and products.
2. Create purchase orders with order lines.
3. Receive goods and update inventory.
4. Track inventory movements.
5. Register and reconcile supplier invoices.
6. Explore Oracle transactions, constraints, indexes, PL/SQL and query plans.

## Current status

**Phase 1 — Oracle infrastructure**

- [x] Docker Compose definition for Oracle Database Free
- [x] Environment variable template
- [x] Persistent database volume
- [ ] Start Oracle locally and verify the connection
- [ ] Connect the Symfony application to Oracle

## Requirements

- Docker Desktop or Docker Engine with the Compose plugin
- Git
- A SQL client (DBeaver or Oracle SQL Developer is optional)

## Quick start

1. Copy the environment template:

   ```bash
   cp .env.example .env
   ```

2. Set strong local passwords in `.env`. Do not commit this file.

3. Start Oracle:

   ```bash
   docker compose pull
   docker compose up -d
   docker compose ps
   ```

4. Follow the first database initialization:

   ```bash
   docker compose logs -f oracle
   ```

5. When the container is healthy, connect with SQL*Plus:

   ```bash
   docker compose exec oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"'
   ```

6. Run the verification query from [docs/oracle-setup.md](docs/oracle-setup.md).

## Oracle connection settings

| Setting | Value |
|---|---|
| Host from your computer | `localhost` |
| Listener port | `1521` |
| Service name | `FREEPDB1` |
| Application user | Value of `APP_USER` in `.env` |
| Application password | Value of `APP_USER_PASSWORD` in `.env` |

The application user is created by the Oracle container image. The application connects to the pluggable database service `FREEPDB1`, not to the container database root.

## Data persistence

Oracle data is stored in the named Docker volume `erp_oracle_data`. Stopping or recreating the container does not remove this volume.

**Warning:** `docker compose down -v` removes the volume and permanently deletes the local database data.

## Planned stack

- PHP 8.4
- Symfony 7.4
- Oracle Database Free
- Doctrine DBAL / ORM (Oracle driver compatibility will be validated before choosing the integration)
- PHPUnit
- RabbitMQ for selected asynchronous workflows (later phase)
- Vue.js frontend (later phase)

## Learning approach

This is an educational project. We will implement business rules incrementally and document the Oracle concepts used by each workflow. Credentials in this repository are examples only; use local secrets in `.env`.

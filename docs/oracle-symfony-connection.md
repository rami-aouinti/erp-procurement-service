# Symfony and Oracle connection

The Symfony backend connects to the Oracle Free PDB through Doctrine DBAL and the native PHP OCI8 extension.

## Connection settings

Docker Compose provides these variables to the backend container:

- `ORACLE_HOST=oracle` — Docker service name, not localhost.
- `ORACLE_PORT=1521`
- `ORACLE_SERVICE=FREEPDB1`
- `ORACLE_USER=ERP_APP`
- `ORACLE_PASSWORD` — sourced from the root `.env` file.

The backend waits for the Oracle health check before starting.

## PHP driver

PHP 8.4 no longer bundles OCI8. The backend image installs Oracle Instant Client Basic Lite and SDK, then compiles OCI8 from PECL. Doctrine DBAL uses its `oci8` driver.

## Verify the connection

After rebuilding and starting the stack:

```bash
docker compose up -d --build
docker compose ps
docker compose logs -f backend
```

Open:

```text
http://localhost:8000/api/database/health
```

A successful response contains `"connected": true`.

The endpoint runs `SELECT 1 FROM DUAL`; it does not modify application data.

## Important transaction boundary

The current `receive_purchase_order_line` PL/SQL procedure performs its own `COMMIT` and `ROLLBACK`. Treat it as a self-contained transaction when calling it from Symfony. Do not wrap it in a Doctrine transaction and assume Symfony can roll back work already committed by the procedure. We will revisit this boundary when implementing the goods-receipt use case.

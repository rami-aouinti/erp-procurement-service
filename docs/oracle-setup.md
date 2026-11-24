# Oracle local setup

This project uses Oracle Database Free in Docker for local learning and development.

## 1. Prepare environment variables

From the repository root:

```bash
cp .env.example .env
```

Edit `.env` and replace both example passwords with strong local passwords. Keep `.env` out of Git.

## 2. Start the database

```bash
docker compose config
docker compose pull
docker compose up -d
docker compose ps
```

The first startup initializes the database and can take several minutes.

Follow the logs:

```bash
docker compose logs -f oracle
```

Wait until `docker compose ps` reports the Oracle service as `healthy`.

## 3. Understand the Oracle database names

The image creates an Oracle Database Free container database (CDB) named `FREE` and a pluggable database (PDB) named `FREEPDB1`.

The application user configured by `APP_USER` is created for the pluggable database. The application should connect to the service `FREEPDB1`.

- Host from the host computer: `localhost`
- Listener port: `1521`
- Service name: `FREEPDB1`
- Username: value of `APP_USER`
- Password: value of `APP_USER_PASSWORD`

## 4. Verify the PDB as SYSDBA

```bash
docker compose exec oracle sqlplus / as sysdba
```

In SQL*Plus:

```sql
SHOW PDBS;
EXIT;
```

Verify that `FREEPDB1` is open.

## 5. Connect as the application user

Use the credentials from your local `.env` file:

```bash
docker compose exec oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"'
```

The command reads the configured values from the Oracle container environment, so it also works if you changed the username.

Run:

```sql
SELECT
    USER AS username,
    SYS_CONTEXT('USERENV', 'CON_NAME') AS container_name
FROM DUAL;
```

Expected values:

- `USERNAME`: your configured application user (default `ERP_APP`)
- `CONTAINER_NAME`: `FREEPDB1`

## 6. Connect with a GUI client

For DBeaver or Oracle SQL Developer, use:

| Field | Value |
|---|---|
| Connection type | Oracle |
| Host | localhost |
| Port | 1521 |
| Connection identifier | Service name |
| Service name | FREEPDB1 |
| Username | ERP_APP (or your configured APP_USER) |
| Password | value of APP_USER_PASSWORD |

## 7. Useful Docker commands

```bash
# Container status
docker compose ps

# Follow database logs
docker compose logs -f oracle

# Open a shell in the database container
docker compose exec oracle bash

# Stop the container (keeps the volume)
docker compose down

# Start it again (keeps the volume)
docker compose up -d
```

**Data warning:** do not run `docker compose down -v` unless you intentionally want to delete the `erp_oracle_data` volume and all local database data.

## Troubleshooting

### Port 1521 is already in use

Stop the other local Oracle container/service or change the host-side port mapping. If you change it to `1522:1521`, GUI clients on the host must use port `1522`; containers on the same Compose network still use port `1521`.

### Container is unhealthy or still starting

Check `docker compose logs oracle`. The first initialization takes longer than subsequent starts. Do not try application connections until the health check passes.

### Password changes do not take effect

The database stores its initialized state in the persistent volume. Changing values in `.env` does not automatically recreate database users or reset passwords in an already initialized database. Use Oracle SQL to change the password, or intentionally recreate the local volume if you are comfortable losing all local data.

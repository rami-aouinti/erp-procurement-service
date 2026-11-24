# CloudBeaver – Oracle database UI

CloudBeaver provides a browser-based database administration interface for the local Oracle database.

## Start

From the repository root:

```bash
docker compose pull cloudbeaver
docker compose up -d cloudbeaver
docker compose ps
```

Open http://localhost:8978 and complete the first-time administrator setup.

## Connect to Oracle

Create a new Oracle connection with:

| Setting | Value |
|---|---|
| Connection type | Oracle |
| Host | `oracle` |
| Port | `1521` |
| Connection mode | Service name |
| Service name | `FREEPDB1` |
| Username | `ERP_APP` |
| Password | Value of `APP_USER_PASSWORD` from the local `.env` |

Use `oracle` as the hostname because CloudBeaver and Oracle are on the same Docker Compose network. Do not use `localhost` from inside CloudBeaver.

The CloudBeaver UI is bound to `127.0.0.1` on the host, so it is intended for local development only.

## Persistent configuration

CloudBeaver settings and workspace data are stored in the named Docker volume `erp_cloudbeaver_data`.

Do not use `docker compose down -v`: it removes named volumes, including the Oracle database data volume.

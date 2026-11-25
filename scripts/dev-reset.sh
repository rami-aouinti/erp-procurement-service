#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

if [[ "$#" -ne 1 || "$1" != "--yes" ]]; then
  cat <<'USAGE'
This command DELETES ALL ERP BUSINESS DATA from the configured development Oracle database,
purges the local Supplier RabbitMQ queues, flushes Redis and rebuilds the Supplier search index.

It preserves Oracle schema objects, migrations, sequences, Docker volumes and monitoring history.

Usage:
  bash scripts/dev-reset.sh --yes
USAGE
  exit 2
fi

command -v docker >/dev/null 2>&1 || { echo "Docker is required." >&2; exit 1; }
docker compose ps --status running >/dev/null

APP_ENV_VALUE="$(docker compose exec -T backend printenv APP_ENV | tr -d '\r')"
DB_SERVICE_VALUE="$(docker compose exec -T backend printenv ORACLE_SERVICE | tr -d '\r')"
DB_USER_VALUE="$(docker compose exec -T backend printenv ORACLE_USER | tr -d '\r')"

if [[ "$APP_ENV_VALUE" != "dev" || "$DB_SERVICE_VALUE" != "FREEPDB1" || "$DB_USER_VALUE" == "SYS" || -z "$DB_USER_VALUE" ]]; then
  echo "Refusing destructive reset: expected APP_ENV=dev, ORACLE_SERVICE=FREEPDB1 and a non-SYS application user." >&2
  exit 1
fi

echo "Target confirmed: APP_ENV=$APP_ENV_VALUE, Oracle service=$DB_SERVICE_VALUE, user=$DB_USER_VALUE"
echo "Stopping Supplier workers so they cannot race with the reset..."
docker compose stop supplier-outbox-publisher supplier-events-consumer

run_sql() {
  local file="$1"
  echo "Oracle: $file"
  docker compose exec -T oracle bash -lc \
    'sqlplus -L -s "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < "$file"
}

# The additive migration is idempotent and must exist before the reset references its table.
run_sql database/oracle/020_create_supplier_audit_trail.sql
run_sql database/oracle/030_reset_dev_data.sql

# Seed the procurement core, complete supplier profiles and representative stock movements.
run_sql database/oracle/002_seed_procurement_data.sql
run_sql database/oracle/021_seed_supplier_profile_fixtures.sql
run_sql database/oracle/022_seed_goods_receiving_fixtures.sql

echo "Creating encrypted bank-account fixtures through the application service..."
docker compose exec -T backend php bin/console app:fixtures:supplier-bank-accounts --env=dev
run_sql database/oracle/031_verify_dev_fixtures.sql

echo "Clearing the dedicated development Redis database..."
docker compose exec -T redis redis-cli FLUSHDB
docker compose exec -T backend php bin/console cache:clear --env=dev

echo "Removing the Supplier Elasticsearch read model..."
ES_STATUS="$(docker compose exec -T elasticsearch sh -lc \
  "curl -sS -o /dev/null -w '%{http_code}' -X DELETE http://localhost:9200/suppliers")"
if [[ "$ES_STATUS" != "200" && "$ES_STATUS" != "404" ]]; then
  echo "Unable to remove the Supplier Elasticsearch index (HTTP $ES_STATUS)." >&2
  exit 1
fi

echo "Purging pending, retry and dead-letter Supplier messages..."
QUEUES="$(docker compose exec -T rabbitmq rabbitmqctl -q list_queues -p /erp name)"
for queue in supplier.events.queue supplier.events.retry supplier.events.dead; do
  if grep -Fxq "$queue" <<<"$QUEUES"; then
    docker compose exec -T rabbitmq rabbitmqctl -q purge_queue -p /erp "$queue"
  fi
done

echo "Starting Supplier workers again..."
docker compose up -d supplier-outbox-publisher supplier-events-consumer

echo "Waiting for the backend health endpoint..."
HEALTHY=0
for _ in $(seq 1 30); do
  if curl -fsS http://localhost:8000/api/health >/dev/null; then
    HEALTHY=1
    break
  fi
  sleep 2
done
if [[ "$HEALTHY" -ne 1 ]]; then
  echo "Backend is not healthy. Data and fixtures were applied, but Elasticsearch reindex was not triggered." >&2
  exit 1
fi

echo "Rebuilding the Supplier Elasticsearch index from Oracle..."
curl -fsS http://localhost:8000/api/suppliers >/dev/null

echo
echo "Development data reset completed."
echo "Supplier API: curl -fsS http://localhost:8000/api/suppliers"
echo "Search index: curl -fsS 'http://localhost:9200/suppliers/_count?pretty'"

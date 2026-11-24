# Observability

The local stack uses Prometheus for metrics, Loki for centralized logs, Grafana for dashboards, Grafana Alloy for log collection, cAdvisor for container metrics, and Node Exporter for host-level metrics.

## Services and URLs

| Service | URL | Purpose |
|---|---|---|
| Grafana | http://localhost:3000 | Dashboards and visualization |
| Prometheus | http://localhost:9090 | Targets, PromQL and alert rule status |
| cAdvisor | http://localhost:8088 | Docker container resource metrics |
| Node Exporter | http://localhost:9100/metrics | Host/kernel metrics |
| Symfony metrics | http://localhost:8000/metrics | Application HTTP metrics |
| Loki | http://localhost:3100 | Log query API |
| Alloy | http://localhost:12345 | Collector UI and health |

Grafana provisions Prometheus and Loki datasources and the **ERP Procurement - Overview** dashboard automatically. The dashboard includes centralized container logs. Sign in with \`GRAFANA_ADMIN_USER\` and \`GRAFANA_ADMIN_PASSWORD\` from your local \`.env\`.

## Start the monitoring stack

Copy the new Grafana variables into your existing \`.env\` (do not overwrite existing Oracle, RabbitMQ or encryption credentials):

\`\`\`dotenv
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=<choose-a-strong-local-password>
\`\`\`

Then run:

\`\`\`bash
docker compose config --quiet
docker compose up -d --build
docker compose ps
\`\`\`

Open Grafana at http://localhost:3000 and select **ERP Procurement - Overview** in the **ERP Procurement** folder.

## Application metrics

The Symfony backend exposes Prometheus text format at \`GET /metrics\`.

- \`erp_http_requests_total\`: HTTP response counter, labeled by HTTP method, Symfony route name and status code.
- \`erp_http_request_duration_seconds\`: response-duration histogram with buckets from 5 ms to 10 s.
- \`erp_backend_uptime_seconds\`: age of the current PHP application process.

The metrics subscriber excludes the scrape endpoint itself. Route names are used instead of raw URL paths to avoid high-cardinality labels and leaking identifiers. No request body, query string, email, IBAN or other business data is recorded.

The current PHP development server keeps application counters in process memory. Counters reset when the backend restarts and are not shared between multiple PHP workers/replicas. Prometheus handles counter resets, but a production deployment with multiple workers should replace this in-memory registry with a shared Prometheus-compatible collector/exporter.

## Infrastructure metrics

- cAdvisor collects CPU and memory usage for Docker containers.
- Node Exporter collects host/kernel metrics. With Docker Desktop, these metrics describe the Linux VM used by Docker, not necessarily the full Windows host.
- Prometheus retains 15 days of metrics in the persistent \`erp_prometheus_data\` volume.
- Grafana stores its state in \`erp_grafana_data\`.

## Alert rules

Prometheus evaluates the following rules:

- \`ErpBackendDown\`: backend scrape target is unavailable for one minute.
- \`ErpBackendHigh5xxRate\`: 5xx responses exceed 5% for five minutes.
- \`ErpBackendHighP95Latency\`: p95 response duration exceeds one second for five minutes.

Review **Status → Rules** in Prometheus. Rules are evaluated by Prometheus; no Alertmanager or email/chat notification receiver is configured yet.

## Useful PromQL

\`\`\`promql
# Request rate
sum(rate(erp_http_requests_total[5m]))

# 5xx error percentage
100 * sum(rate(erp_http_requests_total{status=~"5.."}[5m]))
  / clamp_min(sum(rate(erp_http_requests_total[5m])), 0.001)

# p95 HTTP latency
histogram_quantile(
  0.95,
  sum by (le) (rate(erp_http_request_duration_seconds_bucket[5m]))
)

# Container memory
container_memory_working_set_bytes{name!=""}
\`\`\`

## Security notes

Monitoring ports are bound to \`127.0.0.1\` for local development. Keep them off public interfaces unless authentication, TLS, network restrictions and appropriate retention have been configured. Change the example Grafana password before using the stack beyond a local machine.


## Centralized logs with Loki and Alloy

- Loki stores local logs for seven days in the persistent `erp_loki_data` volume.
- Grafana Alloy discovers Docker containers and forwards stdout/stderr logs to Loki.
- Container labels include the container name and Docker Compose service when available.
- Symfony HTTP logs are JSON on stderr and include request ID, route name, method, status and duration. Raw URLs, query strings, request bodies and business data are not logged.
- The PSR-3 logger filters sensitive context keys and exception messages before writing JSON to stderr.

Explore logs in Grafana → Explore → Loki. Useful LogQL:

```logql
{job="docker"}
{job="docker", compose_service="backend"}
{job="docker", compose_service="supplier-events-consumer"} |= "error"
{job="docker"} | json | level="error"
```

The local Alloy collector mounts `/var/run/docker.sock`. Access to this socket is effectively privileged access to the Docker host: keep this collector on a trusted developer machine and do not expose the Docker API or Alloy UI publicly. Loki is unauthenticated and bound to loopback for local development only.

For Kubernetes, apply `k8s/observability.yaml`. It deploys single-replica Loki with a 10 GiB PVC and an Alloy DaemonSet that tails pod logs using Kubernetes metadata. Loki is ClusterIP-only. This is a learning/small-cluster setup, not HA production. Production needs object storage, authentication/tenant isolation, network policies, backup/retention policy and scalable Loki or a managed logging service.

## Troubleshooting Docker image credential errors

If `docker compose up` or `docker compose pull` fails with an error such as
`error getting credentials - err: exit status 1`, this is usually a local Docker
credential-helper problem, not an application or Loki configuration error. On
Docker Desktop with WSL, the configured helper (often
`docker-credential-desktop.exe`) may be unavailable or unable to access the
Windows credential store.

Inspect the Docker client configuration without sharing its contents (it may
contain credential-related settings):

```bash
docker context show
docker version
command -v docker-credential-desktop.exe || true
command -v docker-credential-wincred.exe || true
cat ~/.docker/config.json
```

If the configured helper is missing, restart Docker Desktop and its WSL
integration first. Then sign in again using Docker Desktop or
`docker login`. Do not delete `~/.docker/config.json` blindly and do not
paste its contents into an issue or chat. If the helper configuration is stale,
back up the file and follow Docker Desktop's credential-store repair guidance
for your installed version before changing the `credsStore` setting.

After repairing credentials, retry:

```bash
docker compose config --quiet
docker compose pull loki alloy
docker compose up -d loki alloy
docker compose ps loki alloy
docker compose logs --tail=100 loki alloy
```

Oracle's host port is bound to `127.0.0.1:1521` in this Compose stack so the
development database is not exposed on every host network interface. Other
services should connect to Oracle using the Compose hostname `oracle:1521`.

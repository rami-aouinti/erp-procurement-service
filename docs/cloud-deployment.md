# k6 load testing and Kubernetes cloud deployment

## k6: fixed 20 virtual users

The test in `k6/health.js` uses a constant-VU scenario: exactly 20 virtual users for 60 seconds. Each VU calls the read-only Symfony health endpoint and checks the HTTP status and JSON response.

Thresholds:
- HTTP request failure rate below 1%.
- p95 response duration below 500 ms.
- Check success rate above 99%.

Run locally from the repository root:

```bash
docker compose --profile loadtest run --rm k6
```

The test targets the Compose backend by default. To target another environment:

```bash
docker run --rm -i -e BASE_URL=https://staging.example.com -v "$PWD/k6:/scripts:ro" grafana/k6:0.57.0 run /scripts/health.js
```

Do not run load tests against production without permission and a planned test window. Twenty VUs is a controlled learning scenario, not a capacity certification.

## Kubernetes deployment architecture

The starter manifests are in `k8s/erp.yaml`. They deploy:
- Symfony backend (2 replicas) and ClusterIP Service.
- Supplier outbox publisher (1 replica).
- Supplier event consumer (2 replicas).
- React frontend (2 replicas) behind an Ingress.
- Backend HPA (2–5 replicas), resource requests/limits, health probes and PodDisruptionBudget.

Oracle, Redis, Elasticsearch and RabbitMQ are expected to be cloud-managed services. This avoids putting a single-node development database or unsecured development brokers into a public cluster. Update the endpoint values in the `erp-config` ConfigMap for your cloud provider.

## Build and publish images

The repository provides a cloud-oriented backend image (PHP-FPM + Nginx managed by Supervisor) and a multi-stage frontend image (Vite build served by Nginx). The GitHub Actions workflow `.github/workflows/container-images.yml` builds both images on pushes to `main` (when relevant files change) and publishes `latest` plus an immutable commit-SHA tag to GHCR. It uses the repository's `GITHUB_TOKEN` with package-write permission. Ensure GitHub Actions is allowed to publish packages for this repository.

You can also build and push images manually to a registry reachable by your Kubernetes cluster (replace the registry/tag with your own):

```bash
docker build -f backend/Dockerfile.cloud -t ghcr.io/rami-aouinti/erp-procurement-backend:latest ./backend
docker build -f frontend/Dockerfile -t ghcr.io/rami-aouinti/erp-procurement-frontend:latest ./frontend
docker push ghcr.io/rami-aouinti/erp-procurement-backend:latest
docker push ghcr.io/rami-aouinti/erp-procurement-frontend:latest
```

For a private registry, create an imagePullSecret and attach it to the workloads' pod specs.

## Configure Kubernetes secrets

Do not put credentials in the committed YAML. Create the namespace first, then create the secret from your cloud secret manager or your secure terminal:

```bash
kubectl apply -f k8s/erp.yaml --dry-run=client
kubectl create namespace erp-procurement --dry-run=client -o yaml | kubectl apply -f -
kubectl -n erp-procurement create secret generic erp-runtime-secrets \
  --from-literal=APP_SECRET='REPLACE_WITH_RANDOM_SECRET' \
  --from-literal=ORACLE_PASSWORD='REPLACE_WITH_ORACLE_PASSWORD' \
  --from-literal=RABBITMQ_PASSWORD='REPLACE_WITH_RABBITMQ_PASSWORD' \
  --from-literal=SUPPLIER_BANK_ENCRYPTION_KEY='REPLACE_WITH_BASE64_32_BYTE_KEY'
```

The Secret must contain the four keys above. In production, prefer External Secrets Operator or the cloud provider's secret manager integration over manually managed Kubernetes Secrets. Kubernetes Secrets are base64-encoded, not encrypted by default; enable encryption at rest and restrict RBAC.

Update `ORACLE_HOST`, `REDIS_HOST`, `ELASTICSEARCH_URL`, `RABBITMQ_HOST`, and the Ingress host in `k8s/erp.yaml`. The Oracle endpoint must be reachable from the cluster and expose the `FREEPDB1` service. Ensure firewall rules allow traffic only from the cluster/network.

## Deploy and verify

```bash
kubectl apply -f k8s/erp.yaml
kubectl -n erp-procurement get deployments,pods,services,ingress,hpa
kubectl -n erp-procurement rollout status deployment/erp-backend
kubectl -n erp-procurement rollout status deployment/erp-frontend
kubectl -n erp-procurement logs deployment/erp-backend
```

Install an NGINX Ingress Controller and configure DNS/TLS (for example with cert-manager) before exposing the host. Replace `erp.example.com` with your domain. The HPA also requires Metrics Server.

## Centralized Kubernetes logs

Apply the separate logging stack after the cluster is ready:

```bash
kubectl apply -f k8s/observability.yaml
kubectl -n monitoring get pods,services,pvc
kubectl -n monitoring logs daemonset/alloy
```

Loki is exposed only as `loki.monitoring.svc.cluster.local:3100`. Alloy runs as a DaemonSet, discovers pods through Kubernetes API permissions and tails `/var/log/pods`. If Grafana is deployed in the cluster, configure its Loki datasource to that internal service address. The PVC uses the default StorageClass. This single-replica filesystem setup is for learning/small environments, not HA production.

## Important production follow-ups

The current Symfony adapters connect to Redis by host/port and to Elasticsearch by URL; they do not yet configure Redis AUTH or Elasticsearch API-key/basic authentication. For this first cloud-learning deployment, use private network endpoints with access restricted to the cluster. Before exposing this as a production system, implement and test the provider credentials/TLS settings in the adapters and source secrets from the cloud secret manager.

- Replace mutable `latest` tags with immutable commit-SHA tags and update the manifests.
- Add TLS, WAF/rate limiting, network policies, Pod security standards and least-privilege RBAC.
- Use a managed Oracle-compatible database/network, managed Redis, Elasticsearch and RabbitMQ.
- Configure backup/restore, monitoring and alert routing for the cloud environment.
- Validate Oracle Instant Client compatibility, PHP extensions and application startup against the target cluster.
- The current backend metrics registry is process-local; multi-replica Prometheus metrics need a shared collector or per-process scrape strategy.

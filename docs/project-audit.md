# ERP Procurement Service — project audit

Audit date: 2026-11-24  
Scope: repository source and configuration review on `main`. No local Docker runtime or cloud cluster was available for end-to-end validation during this review.

## Overall assessment

The repository is now a strong portfolio-grade learning platform: Symfony API, Oracle persistence, Redis cache, Elasticsearch search, RabbitMQ event flow, outbox publisher, React frontend, Prometheus/Grafana metrics, k6 load testing, container image publishing and Kubernetes starter manifests.

It is **not yet production-ready**. The main remaining gaps are operational hardening, reproducible CI, cloud credentials/TLS, high availability and runtime verification.

## Findings fixed in this audit

1. **Critical — PSR logger was not registered.** The database health controller required `Psr\\Log\\LoggerInterface`, but the project did not install Monolog or register a PSR-3 logger. Added a JSON stderr logger and explicit interface alias.
2. **High — database diagnostics disclosed internal exception details.** The health endpoint returned raw driver details and logged exception messages. Removed details from the HTTP response and now log safe metadata and exception class only.
3. **High — application logs were not structured for aggregation.** Added an HTTP subscriber that emits JSON to stderr with request ID, route, method, status and duration. It avoids raw URLs, query strings, request bodies and business data.
4. **High — worker logs could expose exception details.** RabbitMQ failure logs now record exception classes instead of raw exception messages.
5. **High — readiness and liveness used the same shallow endpoint.** Backend readiness now checks Oracle through `/api/database/health`; startup and liveness remain on lightweight `/api/health`.
6. **High — the Prometheus validation command used the image's default Prometheus entrypoint, so Docker interpreted `promtool` as an unexpected Prometheus argument.** Corrected the container entrypoint to `promtool`.\n7. **Medium — the IBAN masking test expected the wrong suffix.** The sample IBAN ends in `3000`; updated the assertion to the actual last four characters (the masking implementation was correct).\n8. **Medium — backend CI used `composer update`.** Changed it to `composer install` so the committed lock file is used. The separate tooling directory has no lock file yet and remains non-reproducible.

## Added

- Loki 3.4.2 with TSDB schema, filesystem storage and seven-day retention for local development.
- Grafana Alloy 1.8.3 Docker discovery and log forwarding to Loki.
- Grafana Loki datasource and centralized logs panel.
- Kubernetes Loki Deployment, internal ClusterIP Service, PVC, Alloy DaemonSet and pod-discovery RBAC in `k8s/observability.yaml`.
- Unit test for JSON log output and sensitive-context filtering.

## Remaining risks and recommended priorities

### P1 — before any public cloud deployment

- Replace placeholder endpoints and mutable `latest` image references with real managed-service endpoints and immutable commit-SHA tags.
- Implement Redis authentication/TLS and Elasticsearch authentication/TLS in the adapters. Current adapters do not yet consume cloud credentials.
- Use external secret management, Kubernetes encryption at rest and least-privilege access.
- Add TLS to Ingress, DNS, rate limiting/WAF, NetworkPolicies and appropriate Pod Security settings.
- Do not expose Loki, Prometheus, Grafana or Alloy publicly without authentication and TLS.
- Docker socket access in local Alloy is highly privileged; keep it confined to a trusted development machine.
- Kubernetes Loki is single-replica filesystem storage. Production needs object storage, HA/scalable Loki, tenant isolation, access control and backup.

### P2 — reliability and scalability

- Readiness currently checks Oracle only. Define explicit health policies for Redis, RabbitMQ and Elasticsearch, distinguishing required dependencies from degradable optimizations.
- Add connection timeouts, retry/backoff and circuit-breaker policies around external services.
- Prometheus application counters are process-local. With multiple PHP workers or replicas, use a shared/multiprocess collector or scrape each pod independently.
- Add Alertmanager or managed alerting and test notification routes; current Prometheus rules only evaluate alerts.
- Define and test backup/restore for Oracle and persistent Loki data.
- Verify graceful shutdown, message redelivery and idempotency when RabbitMQ consumer pods terminate.
- Consider worker autoscaling based on queue depth instead of CPU alone.

### P3 — engineering quality

- Add a lock file for `backend/tooling` so static-analysis tools are reproducible in CI.
- Validate Kubernetes YAML with kubeconform and Alloy River configuration in CI.
- Add integration tests for logger wiring, HTTP request logging, health checks and worker retry/dead-letter behavior.
- Add an explicit deployment workflow with environment approval; image publishing does not deploy to a cluster.
- Add staging smoke tests and k6 tests for real business flows. Current 20-VU test exercises only the health endpoint.

## Validation status

- Source/configuration review: completed for the files listed in this audit.
- CI: updated to validate Loki configuration; latest workflow must be checked in GitHub Actions.
- PHP unit tests, full Compose startup, log ingestion, Kubernetes schema validation and actual cloud deployment: **not executed as part of this repository-only review**.

## Suggested next sequence

1. Get CI green and run the full local Compose stack.
2. Verify a request appears in Grafana Explore with its request ID.
3. Run k6 and correlate latency with logs and Prometheus metrics.
4. Deploy the app and Loki/Alloy to a development Kubernetes cluster.
5. Harden cloud credentials, TLS, secrets, network policies and backups before production exposure.


## Oracle procurement schema audit (2026-11-25)

The local Oracle schema was inspected through read-only SQL queries. The observed schema has ten core tables, enabled primary/foreign/unique/check constraints, seven identity columns, and valid indexes. Read-only consistency checks found no orphaned foreign-key relationships and no difference between recorded purchase-order received quantities and summed receipt-line quantities. The stock-versus-movement check also returned no differences for IN/OUT movements; ADJUSTMENT semantics still need to be defined before that reconciliation is considered complete. The standalone `RECEIVE_PURCHASE_ORDER_LINE` procedure was reported as VALID with no compilation errors.

### Changes added on main

- Added `IDX_GRL_RECEIPT_ID` to support lookups of receipt lines by their parent receipt.
- Added `GOODS_RECEIPT_NUMBER_SEQ` and changed new receipt numbers to a sequence-backed format (`GR-YYYY-0000000001`) to avoid relying on millisecond timestamp uniqueness.
- Added `database/oracle/011_harden_goods_receiving.sql` as an additive migration for databases where goods receiving is already installed.
- Updated `004_create_goods_receiving.sql` so fresh installations receive the same index and sequence-based numbering.

The migration changes schema objects only; it does not create receipts or modify existing stock/order quantities. Oracle DDL implicitly commits, so apply it during a controlled maintenance window and inspect the output. Existing receipt numbers are retained.

### Remaining Oracle work

- Verify the new migration against the user's local Oracle container and confirm the index, sequence and procedure are VALID.
- Exercise the procedure with a disposable submitted purchase order, including success, over-receipt, invalid status, unknown SKU and concurrent calls. The procedure currently owns COMMIT/ROLLBACK; preserve this documented transaction contract unless the application integration is deliberately redesigned.
- Define whether ADJUSTMENT quantities are signed or whether a separate direction is required, then include them in stock reconciliation.
- Consider enforcing that a goods receipt and its receipt line refer to the same purchase order, not merely independently valid parent rows.
- Review actual SQL plans and workload before adding further indexes.

# Configuration performance tests

These scripts exercise the authenticated Configuration read endpoints. They do not mutate global site settings.

## Prerequisites

- Start the stack: `docker compose up -d`
- Obtain an access token for a dedicated Keycloak test user.
- Export the token in your shell. Never commit tokens to the repository.

## Load test

From the repository root:

```bash
AUTH_TOKEN='<test-user-access-token>' docker compose --profile loadtest run --rm \
  -e AUTH_TOKEN -e BASE_URL=http://backend:8000 \
  k6 run /scripts/configuration-load.js
```

The test ramps from 5 to 40 iterations/second. Each iteration calls both the global site configuration and the current user's preferences endpoint.

## Stress test

```bash
AUTH_TOKEN='<test-user-access-token>' docker compose --profile loadtest run --rm \
  -e AUTH_TOKEN -e BASE_URL=http://backend:8000 \
  k6 run /scripts/configuration-stress.js
```

This ramps to 150 iterations/second (up to 300 HTTP requests/second because each iteration makes two requests). Run it only against a local or isolated test environment.

## Optional write-path test

The load script can also exercise preference updates. Use a dedicated test user because it changes that user's palette and color mode:

```bash
AUTH_TOKEN='<dedicated-test-user-token>' K6_WRITE_TESTS=true \
  docker compose --profile loadtest run --rm \
  -e AUTH_TOKEN -e K6_WRITE_TESTS -e BASE_URL=http://backend:8000 \
  k6 run /scripts/configuration-load.js
```

## Initial service-level objectives

- Normal load: request failure rate below 1%.
- Normal load: global p95 below 500 ms and p99 below 1 second.
- Configuration read endpoint p95 below 350 ms.
- Stress test is a capacity exploration, not a production SLO. Review p95/p99, dropped iterations, CPU, memory, Redis and Oracle metrics together.

Do not run stress tests against production without an approved load-test window and a dedicated tenant/user.

# Keycloak authentication and Supplier onboarding

## Local identity provider

The Compose stack includes Keycloak 26 at http://localhost:8081. The realm import is in
`infra/keycloak/erp-procurement-realm.json` and creates:

- Realm: `erp-procurement`
- API audience: `erp-procurement-api`
- Browser client: `erp-procurement-frontend` (Authorization Code + PKCE S256)
- Local-only test client: `erp-procurement-dev-cli` (password grant enabled only to simplify local API exercises)
- Realm roles: `procurement-user`, `supplier-onboarding-submit`, `supplier-reviewer`, `supplier-admin`

Start the identity provider:

```bash
docker compose up -d keycloak
docker compose logs -f keycloak
```

Open the Keycloak administration console at http://localhost:8081/admin/.
The local admin credentials come from `KEYCLOAK_ADMIN` and
`KEYCLOAK_ADMIN_PASSWORD` in `.env`. Change the example credentials before using
anything beyond a local development machine.

Create internal users in the realm and assign only the required realm roles. For a
local API exercise, create a user, set a non-temporary password and assign either
`supplier-onboarding-submit` or `supplier-reviewer` in the user's realm role mapping.
Then obtain a local-only access token (replace the example credentials):

```bash
TOKEN=$(curl -fsS -X POST \
  http://localhost:8081/realms/erp-procurement/protocol/openid-connect/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=password' \
  --data-urlencode 'client_id=erp-procurement-dev-cli' \
  --data-urlencode 'username=procurement.demo' \
  --data-urlencode 'password=CHANGE_ME' | jq -r .access_token)

curl -fsS http://localhost:8000/api/supplier-onboarding/queue \
  -H "Authorization: Bearer $TOKEN"
```

The API
accepts access tokens whose issuer is
`http://localhost:8081/realms/erp-procurement` and whose audience includes
`erp-procurement-api`. The backend downloads public signing keys from Keycloak's JWKS
endpoint over the Docker network and caches each RSA key in-process for five minutes.
It validates RS256 signature, issuer, audience, expiration and not-before claims. It
does not accept unsigned tokens, client-supplied identity headers or ID tokens in place
of access tokens.

The password-grant client is for local testing only. Do not enable Resource Owner
Password Credentials in production. Production/browser clients must use Authorization
Code with PKCE; machine clients should use client credentials and narrowly scoped
service accounts.

## Supplier onboarding roles

| Role | Permissions |
|---|---|
| `supplier-onboarding-submit` | Register a candidate and submit it for review |
| `supplier-reviewer` | Read the approval queue/history; approve, reject or request changes |
| `supplier-admin` | Both submit and review |
| `procurement-user` | General procurement role; no approval authority by itself |

Role checks happen in the API controller after the token has been cryptographically
validated. A missing/invalid token returns 401; a valid token without the required role
returns 403.

## Workflow and invariants

The onboarding state is separate from the operational supplier status.

- Onboarding: `DRAFT -> PENDING_APPROVAL -> APPROVED | REJECTED | CHANGES_REQUESTED`.
- A corrected supplier can be submitted again: `CHANGES_REQUESTED -> PENDING_APPROVAL`.
- Only `PENDING_APPROVAL` can receive a decision.
- Rejection and change requests require a reason (max 1000 characters).
- A candidate created through the onboarding endpoint is initially operationally
  `INACTIVE`; approval restores its intended `ACTIVE` status.
- Submitting an already active supplier temporarily sets it to `INACTIVE`; approval
  restores its previous operational status. A BLOCKED supplier remains BLOCKED.
- Oracle row locks serialize concurrent decisions. A second reviewer sees the committed
  state and receives 409 rather than overwriting the first decision.
- Transition history, privacy-safe audit metadata and a versioned outbox event are
  written in the same Oracle transaction.
- Existing suppliers are backfilled as `APPROVED`; their ACTIVE / INACTIVE / BLOCKED
  status is not changed.

## API

All endpoints below require a Keycloak access token.

| Method | Endpoint | Required role |
|---|---|---|
| POST | `/api/supplier-onboarding` | supplier-onboarding-submit or supplier-admin |
| POST | `/api/supplier-onboarding/{id}/submit` | supplier-onboarding-submit or supplier-admin |
| GET | `/api/supplier-onboarding/queue` | supplier-reviewer or supplier-admin |
| POST | `/api/supplier-onboarding/{id}/approve` | supplier-reviewer or supplier-admin |
| POST | `/api/supplier-onboarding/{id}/reject` | supplier-reviewer or supplier-admin |
| POST | `/api/supplier-onboarding/{id}/request-changes` | supplier-reviewer or supplier-admin |
| GET | `/api/supplier-onboarding/{id}/history` | supplier-reviewer or supplier-admin |

Candidate registration accepts supplier business fields only. Supplying `status` is
rejected; the application controls the operational status. Example:

```http
POST /api/supplier-onboarding
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "name": "Acme GmbH",
  "email": "contact@acme.example",
  "country": "DE"
}
```

The API returns 201 with the supplier ID, operational status INACTIVE and onboarding
status PENDING_APPROVAL. The reviewer queue exposes only pending requests.

Decision body:

```json
{ "reason": "VAT certificate is missing." }
```

The reason is mandatory for rejection and change requests, optional for approval.
The timeline records actor subject, username, timestamp, transition and reason.

## Apply the Oracle migration

Apply once to an existing database after migration 020:

```bash
docker compose exec -T oracle bash -lc 'sqlplus "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' < database/oracle/023_supplier_onboarding_workflow.sql
```

The migration is safe to re-run for local recovery: it creates missing objects, backfills
suppliers not yet represented in the onboarding table as APPROVED, and refreshes the
audit action constraint. It does not reset supplier operational statuses.

## Important rollout boundary

This first security increment protects the new onboarding API and captures verified
Keycloak identity in audit records. The pre-existing Supplier CRUD/profile endpoints
remain on their legacy access policy for backward compatibility with the current UI and
functional tests. Do not expose the application publicly yet: the next security task is
to migrate those legacy endpoints and the React frontend to Keycloak, then add full
authorization and negative-path tests for every supplier/profile/bank-account operation.

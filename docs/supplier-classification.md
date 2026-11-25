# Supplier classification (SUP-F02)

## Taxonomy model

Supplier classification is optional: existing and newly registered suppliers remain valid without assignments.

- **Categories** are hierarchical through `parent_id`; codes are unique and immutable after creation.
- **Commodity codes** are controlled internal procurement codes. The schema leaves external standards such as UNSPSC as a future mapping concern.
- **Tags** are controlled labels, not arbitrary comma-separated supplier text.
- **Business units** are many-to-many because suppliers may serve more than one internal unit.
- **Strategic tier** is one optional value: `STANDARD`, `PREFERRED` or `STRATEGIC`.

Taxonomy entries are deactivated rather than deleted. Deactivation prevents new assignments but deliberately preserves existing supplier relationships and historical reporting. Reactivation is supported.

## Oracle schema

Apply `database/oracle/025_supplier_classification.sql` as `ERP_APP` after migration 024. The migration is safe to rerun for the objects it creates. It does not backfill or mutate existing supplier records.

Tables:
- `supplier_categories`
- `supplier_commodity_codes`
- `supplier_tags`
- `supplier_business_units`
- `supplier_supplier_categories`
- `supplier_supplier_commodities`
- `supplier_supplier_tags`
- `supplier_supplier_business_units`
- `supplier_classification_profiles`

Assignment replacement is one Oracle transaction. It validates every referenced taxonomy entry is active, replaces all relationship rows, writes the audit record, and appends `SupplierClassificationUpdated` to the transactional outbox. Redis is invalidated after commit. RabbitMQ consumers reload the current supplier and rebuild the Elasticsearch document, so delayed events cannot restore stale classification payloads.

## API

All endpoints require a valid Keycloak access token.

| Method | Endpoint | Permission | Purpose |
|---|---|---|---|
| GET | `/api/supplier-classifications/{type}` | procurement user, onboarding submitter, reviewer, admin | List taxonomy |
| POST | `/api/supplier-classifications/{type}` | supplier-admin | Create entry |
| PATCH | `/api/supplier-classifications/{type}/{id}/status` | supplier-admin | Activate/deactivate |
| GET | `/api/suppliers/{id}/classification` | supplier read roles | Read assignments |
| PUT | `/api/suppliers/{id}/classification` | procurement-user, supplier-admin | Replace assignments |

Allowed `type` values: `categories`, `commodities`, `tags`, `business-units`.

Example assignment:

```json
{
  "categoryIds": [1, 4],
  "commodityIds": [12, 18],
  "tagIds": [2],
  "businessUnitIds": [3],
  "strategicTier": "PREFERRED"
}
```

Supplier search accepts `categoryId`, `commodityId`, `tagId`, `businessUnitId` and `strategicTier` alongside the existing `search` and `status` filters. Multiple supplied filters are combined with AND semantics.

## Read models and consistency

Oracle remains the source of truth. Elasticsearch stores classification IDs under `classification.*` and applies exact term filters. Oracle fallback uses correlated `EXISTS` predicates against the same normalized relationship tables. Redis cache keys include every classification filter; successful assignment invalidates the supplier cache.

After deploying the migration, rebuild the index once so the new mapping and unclassified legacy documents are consistent:

```bash
docker compose exec backend php bin/console supplier:reindex --env=dev
```

No destructive development reset is required to apply this migration.

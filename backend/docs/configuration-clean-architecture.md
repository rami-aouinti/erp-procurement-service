# Configuration module — Clean Architecture

The Configuration module separates global application settings from personal UI preferences.

## Responsibilities

- **Domain** defines persistence contracts only; it has no Symfony, Redis or Doctrine dependency.
- **Application** owns defaults, validation, read/write use cases and the cache port.
- **Infrastructure** adapts Oracle through DBAL and Redis through the PHP Redis extension.
- **Transport** exposes a small HTTP API and delegates all behavior to use cases.

Dependencies point inward: Transport/Infrastructure -> Application -> Domain.

## Data ownership

- Global site settings are managed by users with the `supplier-admin` role.
- Personal preferences are scoped to the authenticated Keycloak subject (`sub`) and can only be read or changed by that user.
- Palette and light/dark mode are personal preferences, never global site settings.
- Defaults are defined in `ConfigurationDefaults`; an empty database does not prevent the application from starting.
- Unknown fields are rejected. Adding a setting means extending the defaults/schema and the application validator.

## API

- `GET /api/configuration/site` — admin only; returns settings and the form schema.
- `PUT /api/configuration/site` — admin only; validates and persists global settings.
- `GET /api/configuration/me/preferences` — authenticated user; returns personal preferences.
- `PUT /api/configuration/me/preferences` — authenticated user; updates only their own preferences.

## Cache

Global site settings are cached in Redis for five minutes. A successful write invalidates the cache. Redis is an optimization: if it is unavailable, reads fall back to Oracle and writes remain available.

## Oracle schema

Run `database/oracle/024_create_configuration_settings.sql` as `ERP_APP`.

# Supplier module architecture

The Supplier module keeps transport adapters separate from technical infrastructure.

```text
Supplier/
├── Domain/
│   ├── Model/
│   ├── Repository/                 # Repository contracts
│   └── Exception/
├── Application/
│   ├── DTO/
│   ├── Exception/
│   ├── UseCase/
│   └── Validator/                  # Application input validation
├── Transport/
│   ├── Http/                       # REST controllers and Swagger
│   └── Console/Command/            # Symfony CLI commands
└── Infrastructure/
    └── Persistence/Doctrine/       # Oracle/Doctrine adapter and ORM entity
```

## Responsibilities

- **Domain** owns supplier business rules and the repository contract.
- **Application** coordinates use cases and validates input DTOs.
- **Transport** adapts HTTP requests and CLI arguments to application use cases.
- **Infrastructure** implements persistence and database-specific behavior.

The Domain repository interface is implemented by
`Infrastructure/Persistence/Doctrine/DoctrineSupplierRepository` and wired in
`config/services.yaml`.

## Console commands

List suppliers:

```bash
docker compose exec backend php bin/console supplier:list
```

Create a supplier:

```bash
docker compose exec backend php bin/console supplier:create "Acme GmbH" \
  --email="contact@acme.de" \
  --phone="+49 421 123456" \
  --tax-number="DE123456789"
```

## Validation

`Application/Validator/SupplierDataValidator` validates the SupplierData DTO.
Both the HTTP controller and Console command use the same validator. Invalid
business input is reported as HTTP 422 or a failed CLI command.

## Continuous integration

`.github/workflows/ci.yml` runs on pushes and pull requests targeting `main`.
It builds the PHP 8.4 + OCI8 backend image, starts Oracle Free with the SQL
initialization scripts, installs Composer dependencies, and runs the PHPUnit
suite against Oracle.

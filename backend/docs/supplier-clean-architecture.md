# Supplier module — Clean Architecture

The Supplier feature is organized around business rules, not around Symfony or Doctrine.

## Dependency direction

- Domain: the Supplier model, business invariants, domain exceptions and repository contract. It has no framework or persistence dependency.
- Application: use cases (create, update, get, list, delete) and input data. It depends on the domain contract.
- Infrastructure: Symfony HTTP adapter and Doctrine/Oracle persistence adapter. It implements the domain repository contract.

Dependencies point inward: Infrastructure -> Application -> Domain.

## Existing Oracle schema

The module maps to the existing SUPPLIERS table. No schema migration is required. SupplierRecord is a persistence model and is deliberately separate from the domain Supplier.

## HTTP API

- GET /api/suppliers
- GET /api/suppliers/{id}
- POST /api/suppliers
- PUT /api/suppliers/{id}
- DELETE /api/suppliers/{id}

## Adding a use case

1. Add a use case in Application/UseCase.
2. Put business invariants in the Domain model or a domain service.
3. Extend the repository interface only when the use case needs a new persistence operation.
4. Implement that operation in the Doctrine adapter.
5. Expose it through an HTTP adapter only if it belongs in the API.
6. Test domain and application behavior without Oracle or Symfony.

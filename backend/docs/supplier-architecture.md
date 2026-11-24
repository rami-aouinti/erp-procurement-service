# Supplier module architecture

The Supplier module follows Clean Architecture with a pragmatic CQRS split:

- **Commands / write use cases:** `CreateSupplier`, `UpdateSupplier`, and `DeleteSupplier`.
- **Queries / read use cases:** `GetSupplier`, `ListSuppliers`, and `SearchSuppliers`.
- **Domain:** `Supplier`, `SupplierStatus`, value objects, domain exceptions, and supplier domain events.
- **Infrastructure:** Oracle persistence, transactional outbox, RabbitMQ transport, Redis cache, and Elasticsearch projection.
- **Transport:** HTTP controllers and Symfony Console commands.

Writes persist the Supplier aggregate and its outbox event in the same Oracle transaction. The outbox publisher forwards events to RabbitMQ. The consumer delegates to `SupplierProjectionEventHandler`, which reloads current Oracle state before updating Elasticsearch and invalidating Redis. This makes duplicate and delayed deliveries safe and avoids synchronous Elasticsearch writes in command handlers.

The current command/query use cases are intentionally direct PHP application handlers; a Messenger bus is not required for this module yet. A Symfony EventSubscriber is also not a substitute for domain-event handling: add one only when the module needs to react to a real Symfony kernel/framework event. RabbitMQ integration events remain handled by the dedicated application event handler.

Supplier status is persisted and exposed as the existing string values (`ACTIVE`, `INACTIVE`, `BLOCKED`) to preserve Oracle and API compatibility.

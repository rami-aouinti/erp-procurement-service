# Domain overview

The first bounded context is **Procurement & Inventory**. The initial implementation will be a modular Symfony application with clear internal boundaries. We will not split every domain concept into a separate deployable microservice prematurely.

## Core business flow

1. A buyer manages suppliers and products.
2. A buyer creates a purchase order containing one or more product lines.
3. Warehouse staff record a goods receipt against the purchase order.
4. The receipt updates inventory and creates an inventory movement.
5. Accounts payable registers a supplier invoice.
6. The invoice is checked against the purchase order and received quantities.

## Initial data model

- `SUPPLIERS`: supplier master data.
- `PRODUCTS`: product catalogue and purchasing unit.
- `PURCHASE_ORDERS`: order header and supplier relationship.
- `PURCHASE_ORDER_ITEMS`: products, quantities and agreed unit prices.
- `GOODS_RECEIPTS`: goods receipt header.
- `GOODS_RECEIPT_ITEMS`: quantities received per order line.
- `INVENTORY`: current stock per product and warehouse.
- `INVENTORY_MOVEMENTS`: immutable stock movement history.
- `SUPPLIER_INVOICES`: supplier invoice and reconciliation status.

## Design principles

- Use primary keys, foreign keys, check constraints and unique constraints to protect data integrity.
- Keep monetary values in appropriate fixed-precision numeric columns.
- Record stock changes as movements; do not rely only on a manually editable stock total.
- Make goods receipt processing transactional: receipt records, stock updates and movement records must commit or roll back together.
- Add indexes based on real query patterns and validate their effect with Oracle execution plans.
- Keep business rules in application services where appropriate; use PL/SQL deliberately to learn stored procedures, functions, packages and triggers.

## Learning topics

Oracle SQL, joins, aggregation, subqueries, sequences/identity columns, transactions, locking, isolation, PL/SQL, indexes, views and execution plans.

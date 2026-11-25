-- DEVELOPMENT ONLY: deletes ERP business data while preserving schema,
-- migrations, constraints, indexes, sequences, procedures and users.
-- Run through scripts/dev-reset.sh, which verifies APP_ENV=dev first.

WHENEVER SQLERROR EXIT SQL.SQLCODE;

DELETE FROM supplier_onboarding_history;
DELETE FROM supplier_onboarding;
DELETE FROM supplier_consumed_events;
DELETE FROM supplier_outbox_events;
DELETE FROM supplier_audit_logs;

DELETE FROM inventory_movements;
DELETE FROM goods_receipt_lines;
DELETE FROM goods_receipts;
DELETE FROM inventory;

DELETE FROM purchase_order_lines;
DELETE FROM purchase_orders;

DELETE FROM supplier_bank_accounts;
DELETE FROM supplier_addresses;
DELETE FROM supplier_contacts;
DELETE FROM supplier_profiles;

DELETE FROM products;
DELETE FROM suppliers;

COMMIT;

PROMPT ERP development business data cleared. Schema and sequences preserved.

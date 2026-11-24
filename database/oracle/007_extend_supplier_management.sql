-- Supplier Management Phase 1 extension.
-- Safe additive migration: existing supplier and purchase-order data is preserved.
-- Run as ERP_APP against FREEPDB1 after the initial schema.
WHENEVER SQLERROR EXIT SQL.SQLCODE;

ALTER TABLE suppliers ADD (
    status VARCHAR2(20) DEFAULT 'ACTIVE' NOT NULL,
    contact_person VARCHAR2(150),
    street VARCHAR2(180),
    city VARCHAR2(100),
    postal_code VARCHAR2(20),
    country VARCHAR2(2)
);

ALTER TABLE suppliers ADD CONSTRAINT chk_suppliers_status
    CHECK (status IN ('ACTIVE', 'INACTIVE', 'BLOCKED'));

CREATE INDEX idx_suppliers_status ON suppliers (status);
CREATE INDEX idx_suppliers_name ON suppliers (name);

COMMENT ON COLUMN suppliers.status IS 'Supplier lifecycle status: ACTIVE, INACTIVE, BLOCKED';
COMMENT ON COLUMN suppliers.contact_person IS 'Primary contact person at the supplier';
COMMENT ON COLUMN suppliers.country IS 'ISO 3166-1 alpha-2 country code';

PROMPT Supplier management Phase 1 columns added successfully.

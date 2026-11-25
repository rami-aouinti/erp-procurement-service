-- Correct existing function-based unique indexes for supplier contacts and addresses.
-- Oracle omits an index entry only when ALL indexed expressions are NULL.
-- Put the supplier_id inside the CASE expression so non-primary/non-default
-- rows are entirely absent from the unique index.

WHENEVER SQLERROR EXIT SQL.SQLCODE

BEGIN
    EXECUTE IMMEDIATE 'DROP INDEX uq_supplier_contacts_primary';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -1418 THEN RAISE; END IF;
END;
/

CREATE UNIQUE INDEX uq_supplier_contacts_primary
    ON supplier_contacts (
        CASE WHEN is_primary = 'Y' THEN supplier_id END,
        CASE WHEN is_primary = 'Y' THEN contact_type END
    );

BEGIN
    EXECUTE IMMEDIATE 'DROP INDEX uq_supplier_addresses_default';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -1418 THEN RAISE; END IF;
END;
/

CREATE UNIQUE INDEX uq_supplier_addresses_default
    ON supplier_addresses (
        CASE WHEN is_default = 'Y' THEN supplier_id END,
        CASE WHEN is_default = 'Y' THEN address_type END
    );

PROMPT Supplier profile conditional unique indexes corrected successfully.

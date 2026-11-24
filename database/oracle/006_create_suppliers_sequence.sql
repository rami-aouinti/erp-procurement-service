-- Create a sequence for Doctrine's OCI8-compatible supplier ID generation.
-- Run once against FREEPDB1 as ERP_APP:
--   @database/oracle/006_create_suppliers_sequence.sql
--
-- Existing rows are preserved. The sequence starts above the current maximum ID.

DECLARE
    v_start NUMBER;
BEGIN
    SELECT NVL(MAX(id), 0) + 1
      INTO v_start
      FROM suppliers;

    EXECUTE IMMEDIATE
        'CREATE SEQUENCE suppliers_seq START WITH ' || TO_CHAR(v_start) ||
        ' INCREMENT BY 1 NOCACHE';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE = -955 THEN
            NULL; -- Sequence already exists; safe to rerun.
        ELSE
            RAISE;
        END IF;
END;
/

PROMPT Supplier sequence created (or already exists).

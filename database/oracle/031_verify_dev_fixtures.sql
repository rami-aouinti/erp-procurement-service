-- Verify the expected development fixture dataset after scripts/dev-reset.sh.

WHENEVER SQLERROR EXIT SQL.SQLCODE;

DECLARE
    v_count NUMBER;

    PROCEDURE assert_count(p_table_name IN VARCHAR2, p_expected IN NUMBER) IS
        v_actual NUMBER;
    BEGIN
        -- p_table_name is supplied only by the fixed assertions below.
        EXECUTE IMMEDIATE 'SELECT COUNT(*) FROM ' || p_table_name
            INTO v_actual;
        IF v_actual <> p_expected THEN
            RAISE_APPLICATION_ERROR(
                -20301,
                p_table_name || ' expected ' || p_expected || ' rows, found ' || v_actual
            );
        END IF;
    END assert_count;
BEGIN
    assert_count('SUPPLIERS', 5);
    assert_count('PRODUCTS', 8);
    assert_count('PURCHASE_ORDERS', 5);
    assert_count('SUPPLIER_PROFILES', 5);
    assert_count('SUPPLIER_CONTACTS', 10);
    assert_count('SUPPLIER_ADDRESSES', 10);
    assert_count('SUPPLIER_BANK_ACCOUNTS', 5);
    assert_count('GOODS_RECEIPTS', 2);
    assert_count('GOODS_RECEIPT_LINES', 5);
    assert_count('INVENTORY', 5);
    assert_count('INVENTORY_MOVEMENTS', 5);
    assert_count('SUPPLIER_AUDIT_LOGS', 5);

    SELECT COUNT(*) INTO v_count
      FROM purchase_orders
     WHERE status = 'PARTIALLY_RECEIVED';
    IF v_count <> 1 THEN
        RAISE_APPLICATION_ERROR(-20302, 'Expected exactly one partially received purchase order.');
    END IF;

    SELECT COUNT(*) INTO v_count
      FROM purchase_orders
     WHERE status = 'RECEIVED';
    IF v_count <> 1 THEN
        RAISE_APPLICATION_ERROR(-20303, 'Expected exactly one fully received purchase order.');
    END IF;
END;
/

PROMPT Development fixture verification passed.

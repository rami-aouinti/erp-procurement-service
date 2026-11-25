-- Create an isolated fixture for the two-session goods-receiving concurrency test.
-- Safe to run once; cleanup is performed by 018_cleanup_goods_receiving_concurrency_test.sql.
WHENEVER SQLERROR EXIT SQL.SQLCODE;
DECLARE
    v_count NUMBER;
    v_supplier_id suppliers.id%TYPE;
BEGIN
    SELECT COUNT(*) INTO v_count FROM products WHERE sku = 'TEST-CONCURRENCY-001';
    IF v_count > 0 THEN
        RAISE_APPLICATION_ERROR(-20130, 'Concurrency test product already exists; inspect or clean up the previous test first.');
    END IF;
    SELECT COUNT(*) INTO v_count FROM purchase_orders WHERE order_number = 'PO-TEST-CONCURRENCY-001';
    IF v_count > 0 THEN
        RAISE_APPLICATION_ERROR(-20131, 'Concurrency test order already exists; inspect or clean up the previous test first.');
    END IF;

    SELECT id INTO v_supplier_id
      FROM suppliers
     WHERE email = 'purchasing@nordtech.example';

    INSERT INTO products (sku, name, description, unit_price, active)
    VALUES ('TEST-CONCURRENCY-001', 'Concurrency Test Product', 'Temporary product for Oracle row-lock test', 1, 1);

    INSERT INTO purchase_orders (order_number, supplier_id, status, order_date)
    VALUES ('PO-TEST-CONCURRENCY-001', v_supplier_id, 'SUBMITTED', SYSDATE);

    INSERT INTO purchase_order_lines (purchase_order_id, product_id, quantity, unit_price)
    SELECT po.id, p.id, 10, 1
      FROM purchase_orders po
      CROSS JOIN products p
     WHERE po.order_number = 'PO-TEST-CONCURRENCY-001'
       AND p.sku = 'TEST-CONCURRENCY-001';

    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Concurrency fixture ready: ordered quantity = 10.');
END;
/

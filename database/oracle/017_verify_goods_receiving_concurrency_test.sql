-- Verify the committed result before cleaning up the isolated test fixture.
WHENEVER SQLERROR EXIT SQL.SQLCODE;
SET SERVEROUTPUT ON;
DECLARE
    v_received NUMBER;
    v_stock NUMBER;
    v_receipts NUMBER;
    v_movements NUMBER;
BEGIN
    SELECT pol.received_quantity
      INTO v_received
      FROM purchase_order_lines pol
      JOIN purchase_orders po ON po.id = pol.purchase_order_id
      JOIN products p ON p.id = pol.product_id
     WHERE po.order_number = 'PO-TEST-CONCURRENCY-001'
       AND p.sku = 'TEST-CONCURRENCY-001';

    SELECT i.quantity_on_hand INTO v_stock
      FROM inventory i
      JOIN products p ON p.id = i.product_id
     WHERE p.sku = 'TEST-CONCURRENCY-001';

    SELECT COUNT(*) INTO v_receipts
      FROM goods_receipts gr
     WHERE gr.purchase_order_id = (SELECT id FROM purchase_orders WHERE order_number = 'PO-TEST-CONCURRENCY-001');

    SELECT COUNT(*) INTO v_movements
      FROM inventory_movements im
      JOIN goods_receipt_lines grl ON grl.id = im.goods_receipt_line_id
      JOIN goods_receipts gr ON gr.id = grl.goods_receipt_id
     WHERE gr.purchase_order_id = (SELECT id FROM purchase_orders WHERE order_number = 'PO-TEST-CONCURRENCY-001');

    IF v_received <> 6 OR v_stock <> 6 OR v_receipts <> 1 OR v_movements <> 1 THEN
        RAISE_APPLICATION_ERROR(-20133, 'Concurrency verification failed. received=' || v_received ||
            ', stock=' || v_stock || ', receipts=' || v_receipts || ', movements=' || v_movements);
    END IF;

    DBMS_OUTPUT.PUT_LINE('PASS final state: received=6, stock=6, receipts=1, movements=1.');
END;
/

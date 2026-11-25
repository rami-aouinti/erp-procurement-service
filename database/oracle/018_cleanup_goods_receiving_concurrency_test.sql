-- Remove only the dedicated concurrency-test fixture and its receipt artifacts.
WHENEVER SQLERROR EXIT SQL.SQLCODE;
DECLARE
    v_product_id products.id%TYPE;
    v_order_id purchase_orders.id%TYPE;
BEGIN
    SELECT id INTO v_product_id FROM products WHERE sku = 'TEST-CONCURRENCY-001';
    SELECT id INTO v_order_id FROM purchase_orders WHERE order_number = 'PO-TEST-CONCURRENCY-001';

    DELETE FROM inventory_movements
     WHERE goods_receipt_line_id IN (
         SELECT grl.id
           FROM goods_receipt_lines grl
           JOIN goods_receipts gr ON gr.id = grl.goods_receipt_id
          WHERE gr.purchase_order_id = v_order_id
     );
    DELETE FROM goods_receipt_lines
     WHERE goods_receipt_id IN (SELECT id FROM goods_receipts WHERE purchase_order_id = v_order_id);
    DELETE FROM goods_receipts WHERE purchase_order_id = v_order_id;
    DELETE FROM inventory WHERE product_id = v_product_id;
    DELETE FROM purchase_order_lines WHERE purchase_order_id = v_order_id;
    DELETE FROM purchase_orders WHERE id = v_order_id;
    DELETE FROM products WHERE id = v_product_id;

    COMMIT;
END;
/
PROMPT Dedicated concurrency-test fixture cleaned up.

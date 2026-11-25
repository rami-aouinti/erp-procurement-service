-- Deterministic goods-receiving fixtures. Requires the procurement and receiving schema.
-- PO-2026-0001 becomes PARTIALLY_RECEIVED; PO-2026-0004 becomes RECEIVED.

WHENEVER SQLERROR EXIT SQL.SQLCODE;

DECLARE
    v_order_id NUMBER;
    v_receipt_id NUMBER;
    v_sequence NUMBER;

    PROCEDURE add_receipt_line(
        p_receipt_id IN NUMBER,
        p_order_id IN NUMBER,
        p_sku IN VARCHAR2,
        p_quantity IN NUMBER
    ) IS
        v_order_line_id NUMBER;
        v_product_id NUMBER;
        v_line_id NUMBER;
    BEGIN
        SELECT pol.id, pol.product_id
          INTO v_order_line_id, v_product_id
          FROM purchase_order_lines pol
          JOIN products p ON p.id = pol.product_id
         WHERE pol.purchase_order_id = p_order_id
           AND p.sku = p_sku
         FOR UPDATE OF pol.received_quantity;

        INSERT INTO goods_receipt_lines
            (goods_receipt_id, purchase_order_id, purchase_order_line_id, quantity)
        VALUES
            (p_receipt_id, p_order_id, v_order_line_id, p_quantity)
        RETURNING id INTO v_line_id;

        UPDATE purchase_order_lines
           SET received_quantity = received_quantity + p_quantity
         WHERE id = v_order_line_id;

        MERGE INTO inventory target
        USING (SELECT v_product_id product_id FROM dual) source
           ON (target.product_id = source.product_id)
        WHEN MATCHED THEN UPDATE SET
            target.quantity_on_hand = target.quantity_on_hand + p_quantity,
            target.updated_at = SYSTIMESTAMP
        WHEN NOT MATCHED THEN INSERT (product_id, quantity_on_hand, updated_at)
            VALUES (v_product_id, p_quantity, SYSTIMESTAMP);

        INSERT INTO inventory_movements
            (product_id, goods_receipt_line_id, movement_type, quantity, movement_date)
        VALUES
            (v_product_id, v_line_id, 'IN', p_quantity, SYSTIMESTAMP);
    END add_receipt_line;
BEGIN
    SELECT id INTO v_order_id
      FROM purchase_orders
     WHERE order_number = 'PO-2026-0001';

    SELECT goods_receipt_number_seq.NEXTVAL INTO v_sequence FROM dual;
    INSERT INTO goods_receipts (receipt_number, purchase_order_id)
    VALUES ('GR-DEMO-' || LPAD(TO_CHAR(v_sequence), 10, '0'), v_order_id)
    RETURNING id INTO v_receipt_id;

    add_receipt_line(v_receipt_id, v_order_id, 'IT-DOCK-001', 4);
    add_receipt_line(v_receipt_id, v_order_id, 'IT-LAP-001', 2);
    add_receipt_line(v_receipt_id, v_order_id, 'IT-MON-027', 3);

    UPDATE purchase_orders SET status = 'PARTIALLY_RECEIVED' WHERE id = v_order_id;

    SELECT id INTO v_order_id
      FROM purchase_orders
     WHERE order_number = 'PO-2026-0004';

    SELECT goods_receipt_number_seq.NEXTVAL INTO v_sequence FROM dual;
    INSERT INTO goods_receipts (receipt_number, purchase_order_id)
    VALUES ('GR-DEMO-' || LPAD(TO_CHAR(v_sequence), 10, '0'), v_order_id)
    RETURNING id INTO v_receipt_id;

    add_receipt_line(v_receipt_id, v_order_id, 'NET-SW-024', 12);
    add_receipt_line(v_receipt_id, v_order_id, 'IT-CAB-USB-C', 40);

    UPDATE purchase_orders SET status = 'RECEIVED' WHERE id = v_order_id;

    COMMIT;
END;
/

PROMPT Goods receipt, inventory and movement fixtures seeded.

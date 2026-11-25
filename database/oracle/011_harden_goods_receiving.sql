-- Additive hardening for the goods-receiving workflow.
-- Apply once to an existing schema after 004_create_goods_receiving.sql.
-- Existing receipts, stock and purchase-order data are preserved.
-- This migration does not execute a receipt and does not commit application data.

WHENEVER SQLERROR EXIT SQL.SQLCODE;

PROMPT Adding the missing goods-receipt lookup index (if needed)...

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
      INTO v_count
      FROM user_indexes
     WHERE index_name = 'IDX_GRL_RECEIPT_ID';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE
            'CREATE INDEX idx_grl_receipt_id ON goods_receipt_lines (goods_receipt_id)';
    END IF;
END;
/

PROMPT Creating the receipt-number sequence (if needed)...

DECLARE
    v_start NUMBER;
BEGIN
    SELECT NVL(MAX(id), 0) + 1
      INTO v_start
      FROM goods_receipts;

    EXECUTE IMMEDIATE
        'CREATE SEQUENCE goods_receipt_number_seq START WITH ' ||
        TO_CHAR(v_start) || ' INCREMENT BY 1 NOCACHE';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -955 THEN
            RAISE;
        END IF;
END;
/

PROMPT Replacing the receiving procedure with sequence-based receipt numbers...

CREATE OR REPLACE PROCEDURE receive_purchase_order_line (
    p_order_number IN VARCHAR2,
    p_sku          IN VARCHAR2,
    p_quantity     IN NUMBER
) AS
    v_order_id          purchase_orders.id%TYPE;
    v_order_status      purchase_orders.status%TYPE;
    v_order_line_id     purchase_order_lines.id%TYPE;
    v_product_id        products.id%TYPE;
    v_ordered_quantity  purchase_order_lines.quantity%TYPE;
    v_received_quantity purchase_order_lines.received_quantity%TYPE;
    v_receipt_id        goods_receipts.id%TYPE;
    v_receipt_number    goods_receipts.receipt_number%TYPE;
    v_receipt_sequence  NUMBER;
    v_new_status        purchase_orders.status%TYPE;
    v_remaining_lines   NUMBER;
BEGIN
    IF p_quantity IS NULL OR p_quantity <= 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'Receipt quantity must be greater than zero.');
    END IF;

    -- Serialize receipts for the same order to prevent over-receiving.
    SELECT id, status
      INTO v_order_id, v_order_status
      FROM purchase_orders
     WHERE order_number = p_order_number
       FOR UPDATE;

    IF v_order_status NOT IN ('SUBMITTED', 'PARTIALLY_RECEIVED') THEN
        RAISE_APPLICATION_ERROR(-20002, 'Only submitted or partially received orders can receive goods.');
    END IF;

    SELECT pol.id, pol.product_id, pol.quantity, pol.received_quantity
      INTO v_order_line_id, v_product_id, v_ordered_quantity, v_received_quantity
      FROM purchase_order_lines pol
      JOIN products p ON p.id = pol.product_id
     WHERE pol.purchase_order_id = v_order_id
       AND p.sku = p_sku
       FOR UPDATE OF pol.received_quantity;

    IF p_quantity > (v_ordered_quantity - v_received_quantity) THEN
        RAISE_APPLICATION_ERROR(-20003, 'Receipt quantity exceeds the remaining ordered quantity.');
    END IF;

    SELECT goods_receipt_number_seq.NEXTVAL
      INTO v_receipt_sequence
      FROM dual;

    v_receipt_number := 'GR-' || TO_CHAR(SYSDATE, 'YYYY') || '-' ||
                        LPAD(TO_CHAR(v_receipt_sequence), 10, '0');

    INSERT INTO goods_receipts (receipt_number, purchase_order_id)
    VALUES (v_receipt_number, v_order_id)
    RETURNING id INTO v_receipt_id;

    INSERT INTO goods_receipt_lines (goods_receipt_id, purchase_order_line_id, quantity)
    VALUES (v_receipt_id, v_order_line_id, p_quantity);

    UPDATE purchase_order_lines
       SET received_quantity = received_quantity + p_quantity
     WHERE id = v_order_line_id;

    MERGE INTO inventory target
    USING (SELECT v_product_id AS product_id FROM dual) source
       ON (target.product_id = source.product_id)
     WHEN MATCHED THEN
        UPDATE SET
            target.quantity_on_hand = target.quantity_on_hand + p_quantity,
            target.updated_at = SYSTIMESTAMP
     WHEN NOT MATCHED THEN
        INSERT (product_id, quantity_on_hand, updated_at)
        VALUES (source.product_id, p_quantity, SYSTIMESTAMP);

    INSERT INTO inventory_movements (
        product_id, goods_receipt_line_id, movement_type, quantity
    ) VALUES (
        v_product_id,
        (SELECT id FROM goods_receipt_lines WHERE goods_receipt_id = v_receipt_id),
        'IN',
        p_quantity
    );

    SELECT COUNT(*)
      INTO v_remaining_lines
      FROM purchase_order_lines
     WHERE purchase_order_id = v_order_id
       AND received_quantity < quantity;

    IF v_remaining_lines = 0 THEN
        v_new_status := 'RECEIVED';
    ELSE
        v_new_status := 'PARTIALLY_RECEIVED';
    END IF;

    UPDATE purchase_orders
       SET status = v_new_status
     WHERE id = v_order_id;

    -- Keep the existing transaction contract: this procedure owns COMMIT/ROLLBACK.
    COMMIT;

    DBMS_OUTPUT.PUT_LINE(
        'Goods received: ' || p_quantity || ' x ' || p_sku ||
        ' for ' || p_order_number || '. Receipt: ' || v_receipt_number
    );
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        ROLLBACK;
        RAISE_APPLICATION_ERROR(-20004, 'Purchase order or product line was not found.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

SHOW ERRORS PROCEDURE receive_purchase_order_line;

PROMPT Goods-receiving hardening migration completed.

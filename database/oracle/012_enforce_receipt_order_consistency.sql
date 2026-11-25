-- Enforce that a goods receipt and its receipt lines belong to the same purchase order.
-- Apply once to an existing schema after 011_harden_goods_receiving.sql.
-- Existing receipt and inventory data are preserved.
WHENEVER SQLERROR EXIT SQL.SQLCODE;

PROMPT Checking existing receipt/order relationships...

DECLARE
    v_mismatches NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_mismatches
      FROM goods_receipt_lines grl
      JOIN goods_receipts gr ON gr.id = grl.goods_receipt_id
      JOIN purchase_order_lines pol ON pol.id = grl.purchase_order_line_id
     WHERE gr.purchase_order_id <> pol.purchase_order_id;

    IF v_mismatches > 0 THEN
        RAISE_APPLICATION_ERROR(-20120, 'Migration stopped: receipt lines reference a different purchase order.');
    END IF;
END;
/

ALTER TABLE goods_receipts
    ADD CONSTRAINT uq_gr_id_order UNIQUE (id, purchase_order_id);

ALTER TABLE purchase_order_lines
    ADD CONSTRAINT uq_pol_id_order UNIQUE (id, purchase_order_id);

ALTER TABLE goods_receipt_lines ADD (purchase_order_id NUMBER);

UPDATE goods_receipt_lines grl
   SET purchase_order_id = (
       SELECT pol.purchase_order_id
         FROM purchase_order_lines pol
        WHERE pol.id = grl.purchase_order_line_id
   );

ALTER TABLE goods_receipt_lines MODIFY (purchase_order_id NOT NULL);

ALTER TABLE goods_receipt_lines
    ADD CONSTRAINT fk_grl_receipt_order
    FOREIGN KEY (goods_receipt_id, purchase_order_id)
    REFERENCES goods_receipts (id, purchase_order_id);

ALTER TABLE goods_receipt_lines
    ADD CONSTRAINT fk_grl_line_order
    FOREIGN KEY (purchase_order_line_id, purchase_order_id)
    REFERENCES purchase_order_lines (id, purchase_order_id);

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

    SELECT id, status INTO v_order_id, v_order_status
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

    SELECT goods_receipt_number_seq.NEXTVAL INTO v_receipt_sequence FROM dual;
    v_receipt_number := 'GR-' || TO_CHAR(SYSDATE, 'YYYY') || '-' ||
                        LPAD(TO_CHAR(v_receipt_sequence), 10, '0');

    INSERT INTO goods_receipts (receipt_number, purchase_order_id)
    VALUES (v_receipt_number, v_order_id)
    RETURNING id INTO v_receipt_id;

    INSERT INTO goods_receipt_lines (
        goods_receipt_id, purchase_order_id, purchase_order_line_id, quantity
    ) VALUES (
        v_receipt_id, v_order_id, v_order_line_id, p_quantity
    );

    UPDATE purchase_order_lines
       SET received_quantity = received_quantity + p_quantity
     WHERE id = v_order_line_id;

    MERGE INTO inventory target
    USING (SELECT v_product_id AS product_id FROM dual) source
       ON (target.product_id = source.product_id)
     WHEN MATCHED THEN
        UPDATE SET target.quantity_on_hand = target.quantity_on_hand + p_quantity,
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

    SELECT COUNT(*) INTO v_remaining_lines
      FROM purchase_order_lines
     WHERE purchase_order_id = v_order_id
       AND received_quantity < quantity;

    IF v_remaining_lines = 0 THEN
        v_new_status := 'RECEIVED';
    ELSE
        v_new_status := 'PARTIALLY_RECEIVED';
    END IF;

    UPDATE purchase_orders SET status = v_new_status WHERE id = v_order_id;

    -- Preserve the existing transaction contract: this procedure owns COMMIT/ROLLBACK.
    COMMIT;

    DBMS_OUTPUT.PUT_LINE('Goods received: ' || p_quantity || ' x ' || p_sku ||
                         ' for ' || p_order_number || '. Receipt: ' || v_receipt_number);
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
PROMPT Receipt/order ownership constraints installed successfully.

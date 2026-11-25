-- Session A: hold the purchase-order row lock before receiving 6 of 10 units.
WHENEVER SQLERROR EXIT SQL.SQLCODE;
SET SERVEROUTPUT ON;
PROMPT SESSION_A_LOCKING_ORDER
SELECT id
  FROM purchase_orders
 WHERE order_number = 'PO-TEST-CONCURRENCY-001'
 FOR UPDATE;
PROMPT SESSION_A_LOCK_ACQUIRED
HOST sleep 5
BEGIN
    receive_purchase_order_line(
        p_order_number => 'PO-TEST-CONCURRENCY-001',
        p_sku          => 'TEST-CONCURRENCY-001',
        p_quantity     => 6
    );
END;
/
PROMPT SESSION_A_RECEIPT_COMMITTED

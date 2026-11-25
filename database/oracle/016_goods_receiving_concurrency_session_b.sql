-- Session B: this call starts while Session A holds the order lock.
-- It must wait, then reject 5 because only 4 units remain.
WHENEVER SQLERROR EXIT SQL.SQLCODE;
SET SERVEROUTPUT ON;
DECLARE
BEGIN
    receive_purchase_order_line(
        p_order_number => 'PO-TEST-CONCURRENCY-001',
        p_sku          => 'TEST-CONCURRENCY-001',
        p_quantity     => 5
    );
    RAISE_APPLICATION_ERROR(-20132, 'Concurrency test failed: Session B unexpectedly received 5 units.');
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE = -20003 THEN
            DBMS_OUTPUT.PUT_LINE('PASS concurrency: Session B waited and then saw only 4 units remaining.');
        ELSE
            RAISE;
        END IF;
END;
/

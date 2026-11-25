-- Non-destructive negative-path tests for RECEIVE_PURCHASE_ORDER_LINE.
-- Requires migrations 004, 011 and 012 to be applied.
-- Every case must fail before any INSERT/UPDATE. No successful receipt is performed.
-- Run in a dedicated SQL*Plus session; the procedure rolls back on errors.

WHENEVER SQLERROR EXIT SQL.SQLCODE;
SET SERVEROUTPUT ON;

DECLARE
    PROCEDURE expect_error(
        p_test_name       IN VARCHAR2,
        p_expected_code   IN NUMBER,
        p_order_number    IN VARCHAR2,
        p_sku             IN VARCHAR2,
        p_quantity        IN NUMBER
    ) IS
    BEGIN
        receive_purchase_order_line(
            p_order_number => p_order_number,
            p_sku          => p_sku,
            p_quantity     => p_quantity
        );

        RAISE_APPLICATION_ERROR(
            -20999,
            'Test failed: ' || p_test_name || ' unexpectedly succeeded.'
        );
    EXCEPTION
        WHEN OTHERS THEN
            IF SQLCODE = -20999 THEN
                RAISE;
            ELSIF SQLCODE <> p_expected_code THEN
                DBMS_OUTPUT.PUT_LINE(
                    'FAIL ' || p_test_name || ': expected ' ||
                    p_expected_code || ', got ' || SQLCODE || ' - ' || SQLERRM
                );
                RAISE;
            ELSE
                DBMS_OUTPUT.PUT_LINE(
                    'PASS ' || p_test_name || ': expected error ' || SQLCODE
                );
            END IF;
    END;
BEGIN
    -- PO-2026-0001 is SUBMITTED and contains IT-DOCK-001.
    expect_error(
        'zero quantity',
        -20001,
        'PO-2026-0001',
        'IT-DOCK-001',
        0
    );

    -- A very large quantity must be rejected before any receipt is inserted.
    expect_error(
        'quantity exceeds remaining ordered quantity',
        -20003,
        'PO-2026-0001',
        'IT-DOCK-001',
        999999
    );

    -- PO-2026-0002 is a seeded DRAFT order.
    expect_error(
        'draft order cannot receive goods',
        -20002,
        'PO-2026-0002',
        'OFF-CHAIR-001',
        1
    );

    -- PO-2026-0005 is a seeded CANCELLED order.
    expect_error(
        'cancelled order cannot receive goods',
        -20002,
        'PO-2026-0005',
        'TOOL-DRILL-001',
        1
    );

    -- The order exists, but this SKU is not part of its order lines.
    expect_error(
        'unknown SKU for order',
        -20004,
        'PO-2026-0001',
        'SKU-DOES-NOT-EXIST',
        1
    );

    DBMS_OUTPUT.PUT_LINE('All non-destructive goods-receiving guard tests passed.');
END;
/

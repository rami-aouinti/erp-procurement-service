-- Manual test for the goods receiving workflow.
-- Run 004_create_goods_receiving.sql first.
-- This receives 2 docking stations against PO-2026-0001.
-- The order line originally contains 10 units.

SET SERVEROUTPUT ON;

BEGIN
    receive_purchase_order_line(
        p_order_number => 'PO-2026-0001',
        p_sku          => 'IT-DOCK-001',
        p_quantity     => 2
    );
END;
/

PROMPT Check the purchase order line:

SELECT
    po.order_number,
    p.sku,
    pol.quantity AS ordered_quantity,
    pol.received_quantity,
    pol.quantity - pol.received_quantity AS remaining_quantity,
    po.status
FROM purchase_orders po
JOIN purchase_order_lines pol ON pol.purchase_order_id = po.id
JOIN products p ON p.id = pol.product_id
WHERE po.order_number = 'PO-2026-0001'
  AND p.sku = 'IT-DOCK-001';

PROMPT Check current inventory:

SELECT p.sku, p.name, i.quantity_on_hand
FROM inventory i
JOIN products p ON p.id = i.product_id
WHERE p.sku = 'IT-DOCK-001';

PROMPT Check the stock movement audit trail:

SELECT
    im.movement_type,
    im.quantity,
    im.movement_date,
    gr.receipt_number,
    po.order_number,
    p.sku
FROM inventory_movements im
JOIN goods_receipt_lines grl ON grl.id = im.goods_receipt_line_id
JOIN goods_receipts gr ON gr.id = grl.goods_receipt_id
JOIN purchase_order_lines pol ON pol.id = grl.purchase_order_line_id
JOIN purchase_orders po ON po.id = gr.purchase_order_id
JOIN products p ON p.id = im.product_id
WHERE po.order_number = 'PO-2026-0001'
  AND p.sku = 'IT-DOCK-001'
ORDER BY im.movement_date DESC;

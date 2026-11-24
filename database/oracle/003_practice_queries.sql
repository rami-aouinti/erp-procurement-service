-- Practical SQL queries for the ERP Procurement Service.
-- Run after 001_create_procurement_schema.sql and 002_seed_procurement_data.sql.

SET PAGESIZE 100;
SET LINESIZE 220;

PROMPT 1. Purchase orders with supplier and calculated order total

SELECT
    po.order_number,
    po.status,
    po.order_date,
    s.name AS supplier_name,
    COUNT(pol.id) AS number_of_lines,
    SUM(pol.quantity * pol.unit_price) AS order_total
FROM purchase_orders po
JOIN suppliers s ON s.id = po.supplier_id
LEFT JOIN purchase_order_lines pol ON pol.purchase_order_id = po.id
GROUP BY po.order_number, po.status, po.order_date, s.name
ORDER BY po.order_date DESC;

PROMPT 2. Total ordered value by supplier

SELECT
    s.name AS supplier_name,
    COUNT(DISTINCT po.id) AS number_of_orders,
    NVL(SUM(pol.quantity * pol.unit_price), 0) AS total_order_value
FROM suppliers s
LEFT JOIN purchase_orders po ON po.supplier_id = s.id
LEFT JOIN purchase_order_lines pol ON pol.purchase_order_id = po.id
GROUP BY s.id, s.name
ORDER BY total_order_value DESC;

PROMPT 3. Number of purchase orders by status

SELECT status, COUNT(*) AS number_of_orders
FROM purchase_orders
GROUP BY status
ORDER BY number_of_orders DESC;

PROMPT 4. Most frequently ordered products

SELECT
    p.sku,
    p.name,
    SUM(pol.quantity) AS total_quantity_ordered
FROM products p
JOIN purchase_order_lines pol ON pol.product_id = p.id
JOIN purchase_orders po ON po.id = pol.purchase_order_id
WHERE po.status <> 'CANCELLED'
GROUP BY p.sku, p.name
ORDER BY total_quantity_ordered DESC;

PROMPT 5. Suppliers without any purchase order

SELECT s.id, s.name, s.email
FROM suppliers s
LEFT JOIN purchase_orders po ON po.supplier_id = s.id
WHERE po.id IS NULL
ORDER BY s.name;

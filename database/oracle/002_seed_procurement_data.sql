-- Repeatable demo data for the ERP Procurement Service.
-- Run after 001_create_procurement_schema.sql.
-- MERGE keeps this script safe to execute more than once.

SET DEFINE OFF;
WHENEVER SQLERROR EXIT SQL.SQLCODE;

PROMPT Seeding suppliers...

MERGE INTO suppliers target
USING (
    SELECT 'NordTech Components GmbH' name, 'purchasing@nordtech.example' email, '+49 421 5550101' phone, 'DE-NORD-1001' tax_number FROM dual
    UNION ALL SELECT 'Hanseatic Office Supplies GmbH', 'orders@hanseatic-office.example', '+49 421 5550102', 'DE-HAN-1002' FROM dual
    UNION ALL SELECT 'Global Industrial Parts AG', 'procurement@global-parts.example', '+49 40 5550103', 'DE-GIP-1003' FROM dual
    UNION ALL SELECT 'Elbe Logistics & Tools GmbH', 'sales@elbe-tools.example', '+49 40 5550104', 'DE-ELB-1004' FROM dual
    UNION ALL SELECT 'Weser Electronics GmbH', 'orders@weser-electronics.example', '+49 421 5550105', 'DE-WES-1005' FROM dual
) source
ON (target.email = source.email)
WHEN MATCHED THEN UPDATE SET
    target.name = source.name,
    target.phone = source.phone,
    target.tax_number = source.tax_number
WHEN NOT MATCHED THEN INSERT (name, email, phone, tax_number)
    VALUES (source.name, source.email, source.phone, source.tax_number);

PROMPT Seeding products...

MERGE INTO products target
USING (
    SELECT 'IT-DOCK-001' sku, 'USB-C Docking Station' name, 'Business docking station with dual display support' description, 129.90 unit_price, 1 active FROM dual
    UNION ALL SELECT 'IT-LAP-001', 'Business Laptop 14', '14-inch business laptop', 899.00, 1 FROM dual
    UNION ALL SELECT 'IT-MON-027', 'Monitor 27 inch', '27-inch office monitor', 219.00, 1 FROM dual
    UNION ALL SELECT 'IT-CAB-USB-C', 'USB-C Cable', 'USB-C cable, 2 metres', 12.50, 1 FROM dual
    UNION ALL SELECT 'SAF-GLOVE-001', 'Safety Gloves', 'Protective industrial work gloves', 8.90, 1 FROM dual
    UNION ALL SELECT 'TOOL-DRILL-001', 'Industrial Drill', 'Cordless industrial drill', 149.00, 1 FROM dual
    UNION ALL SELECT 'NET-SW-024', '24-Port Network Switch', 'Managed 24-port gigabit switch', 189.00, 1 FROM dual
    UNION ALL SELECT 'OFF-CHAIR-001', 'Ergonomic Office Chair', 'Adjustable ergonomic office chair', 179.00, 1 FROM dual
) source
ON (target.sku = source.sku)
WHEN MATCHED THEN UPDATE SET
    target.name = source.name,
    target.description = source.description,
    target.unit_price = source.unit_price,
    target.active = source.active
WHEN NOT MATCHED THEN INSERT (sku, name, description, unit_price, active)
    VALUES (source.sku, source.name, source.description, source.unit_price, source.active);

PROMPT Seeding purchase orders...

MERGE INTO purchase_orders target
USING (
    SELECT 'PO-2026-0001' order_number, 'purchasing@nordtech.example' supplier_email, 'SUBMITTED' status, DATE '2026-11-03' order_date FROM dual
    UNION ALL SELECT 'PO-2026-0002', 'orders@hanseatic-office.example', 'DRAFT', DATE '2026-11-10' FROM dual
    UNION ALL SELECT 'PO-2026-0003', 'procurement@global-parts.example', 'SUBMITTED', DATE '2026-11-12' FROM dual
    UNION ALL SELECT 'PO-2026-0004', 'orders@weser-electronics.example', 'SUBMITTED', DATE '2026-11-15' FROM dual
    UNION ALL SELECT 'PO-2026-0005', 'sales@elbe-tools.example', 'CANCELLED', DATE '2026-11-18' FROM dual
) source
ON (target.order_number = source.order_number)
WHEN MATCHED THEN UPDATE SET
    target.supplier_id = (SELECT s.id FROM suppliers s WHERE s.email = source.supplier_email),
    target.status = source.status,
    target.order_date = source.order_date
WHEN NOT MATCHED THEN INSERT (order_number, supplier_id, status, order_date)
    VALUES (
        source.order_number,
        (SELECT s.id FROM suppliers s WHERE s.email = source.supplier_email),
        source.status,
        source.order_date
    );

PROMPT Seeding purchase order lines...

MERGE INTO purchase_order_lines target
USING (
    SELECT po.id purchase_order_id, p.id product_id, lines.quantity, lines.unit_price
    FROM (
        SELECT 'PO-2026-0001' order_number, 'IT-DOCK-001' sku, 10 quantity, 129.90 unit_price FROM dual
        UNION ALL SELECT 'PO-2026-0001', 'IT-LAP-001', 5, 899.00 FROM dual
        UNION ALL SELECT 'PO-2026-0001', 'IT-MON-027', 8, 219.00 FROM dual
        UNION ALL SELECT 'PO-2026-0002', 'OFF-CHAIR-001', 4, 179.00 FROM dual
        UNION ALL SELECT 'PO-2026-0002', 'IT-CAB-USB-C', 25, 12.50 FROM dual
        UNION ALL SELECT 'PO-2026-0003', 'TOOL-DRILL-001', 6, 149.00 FROM dual
        UNION ALL SELECT 'PO-2026-0003', 'SAF-GLOVE-001', 100, 8.90 FROM dual
        UNION ALL SELECT 'PO-2026-0004', 'NET-SW-024', 12, 189.00 FROM dual
        UNION ALL SELECT 'PO-2026-0004', 'IT-CAB-USB-C', 40, 12.50 FROM dual
        UNION ALL SELECT 'PO-2026-0005', 'SAF-GLOVE-001', 50, 8.90 FROM dual
        UNION ALL SELECT 'PO-2026-0005', 'TOOL-DRILL-001', 2, 149.00 FROM dual
    ) lines
    JOIN purchase_orders po ON po.order_number = lines.order_number
    JOIN products p ON p.sku = lines.sku
) source
ON (
    target.purchase_order_id = source.purchase_order_id
    AND target.product_id = source.product_id
)
WHEN MATCHED THEN UPDATE SET
    target.quantity = source.quantity,
    target.unit_price = source.unit_price
WHEN NOT MATCHED THEN INSERT (purchase_order_id, product_id, quantity, unit_price)
    VALUES (source.purchase_order_id, source.product_id, source.quantity, source.unit_price);

COMMIT;

PROMPT Procurement demo data successfully seeded.

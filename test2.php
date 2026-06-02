<?php
require 'd:/xampp/htdocs/easy-outdoor-crm/config/db.php';
$stmtPO = $pdo->prepare("
        SELECT id, 'po' as type, po_date as date, po_number as ref, 
               po_amount as base_amt, (COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0) + COALESCE(igst_amount,0)) as tax_amount, 
               COALESCE(NULLIF(total_amount, 0), po_amount + COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0) + COALESCE(igst_amount,0)) as total_amt, 
               COALESCE(NULLIF(total_amount, 0), po_amount + COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0) + COALESCE(igst_amount,0)) as debit, 
               0 as credit, status
        FROM purchase_orders 
        WHERE vendor_id = 1
        
        UNION ALL
        
        SELECT MIN(r.id) as id, 'po' as type, DATE(MIN(r.created_at)) as date, COALESCE(r.po_number, CONCAT('RATE-', MIN(r.id))) as ref,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as base_amt,
               0 as tax_amount,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as total_amt,
               SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)) as debit,
               0 as credit, 'approved' as status
        FROM vendor_printing_rates r
        LEFT JOIN sites s ON r.site_id = s.id
        WHERE r.vendor_id = 1
        GROUP BY COALESCE(r.po_number, r.id)
");
$stmtPO->execute();
print_r($stmtPO->fetchAll(PDO::FETCH_ASSOC));

$stmtBilled = $pdo->prepare("
    SELECT 
        (SELECT COALESCE(SUM(COALESCE(NULLIF(total_amount, 0), po_amount + COALESCE(cgst_amount,0) + COALESCE(sgst_amount,0) + COALESCE(igst_amount,0))), 0) FROM purchase_orders WHERE vendor_id = 1)
        +
        (SELECT COALESCE(SUM(r.rate_per_sqft * COALESCE(s.width, 0) * COALESCE(s.height, 0)), 0) FROM vendor_printing_rates r LEFT JOIN sites s ON r.site_id = s.id WHERE r.vendor_id = 1)
");
$stmtBilled->execute();
print_r($stmtBilled->fetchColumn());

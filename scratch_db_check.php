<?php
include_once __DIR__ . '/config/db.php';
try {
    // This Month
    $invoiceWhere = "type = 'tax' AND approval_status = 'approved' AND created_at >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE())-1 DAY)";
    $paymentWhere = "type = 'receivable' AND approval_status = 'approved' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE())-1 DAY)";
    
    $stmtBilling = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE $invoiceWhere");
    $stmtBilling->execute();
    $thisMonthBilling = (float)$stmtBilling->fetchColumn();

    $stmtCollection = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE $paymentWhere");
    $stmtCollection->execute();
    $thisMonthCollection = (float)$stmtCollection->fetchColumn();

    echo "This Month Billing: $thisMonthBilling\n";
    echo "This Month Collection: $thisMonthCollection\n";
    
    // Check if there are any invoices or payments at all
    $allInvoices = $pdo->query("SELECT COUNT(*), SUM(total_amount) FROM invoices")->fetch();
    print_r($allInvoices);
    
    $allPayments = $pdo->query("SELECT COUNT(*), SUM(amount) FROM payments")->fetch();
    print_r($allPayments);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

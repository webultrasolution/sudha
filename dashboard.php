<?php
$activePage = 'dashboard';
$pageTitle = 'Business Intelligence Dashboard';
include_once __DIR__ . '/includes/header.php';

// Check Dashboard Specific Permission
if (!canView('dashboard')) {
    echo "<div class='card' style='padding: 4rem 2rem; text-align: center; border-radius: 16px; margin: 3rem auto; max-width: 600px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; background: white;'>
        <div style='background: #fee2e2; color: #ef4444; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 2rem;'>
            <i class='fas fa-lock'></i>
        </div>
        <h2 style='color: #0f172a; font-weight: 800; font-size: 1.75rem; margin: 0 0 0.5rem 0;'>Dashboard Locked</h2>
        <p style='color: #64748b; line-height: 1.6; margin: 0 0 2rem 0; font-size: 0.95rem;'>Your current user role does not have authorization to view the Business Intelligence Dashboard. Please use the sidebar to navigate to your assigned modules.</p>
        <div style='display: flex; justify-content: center; gap: 1rem;'>
            <a href='modules/inventory/sites.php' class='btn btn-secondary' style='font-weight: 700; border-radius: 8px; padding: 0.6rem 1.25rem; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;'><i class='fas fa-map-marked-alt'></i> View Sites</a>
            <a href='modules/proposals/proposals.php' class='btn btn-primary' style='font-weight: 700; border-radius: 8px; padding: 0.6rem 1.25rem; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;'><i class='fas fa-file-contract'></i> Proposals</a>
        </div>
    </div>";
    include_once __DIR__ . '/includes/footer.php';
    exit;
}

$canViewFinancials = canView('financials');
$canViewInventory = canView('inventory');
$canViewProposals = canView('proposals');
$canViewBookings = canView('bookings');

$revenue = 0;
$cost = 0;
$profit = 0;
$margin = 0;
$chartLabels = '[]';
$chartRevenue = '[]';
$chartProfit = '[]';

if ($canViewFinancials) {
    // Financial year select logic
    $selectedFY = $_GET['set_fy'] ?? getFinancialYear();
    list($selectedStartPart, $selectedEndPart) = explode('-', $selectedFY);
    $selectedStartYear = 2000 + intval($selectedStartPart);
    $selectedEndYear = 2000 + intval($selectedEndPart);

    $fyStartDateStr = "$selectedStartYear-04-01";
    $fyEndDateStr = "$selectedEndYear-03-31";

    // Financial Data
    $activeEntityId = $_SESSION['active_entity_id'] ?? null;
    $entityCondInv = $activeEntityId ? " AND i.entity_id = $activeEntityId" : "";
    $finStats = $pdo->query("
        SELECT 
            SUM(bi.amount) as revenue,
            SUM(bi.purchase_amount + bi.printing_amount) as cost,
            SUM(bi.amount - (bi.purchase_amount + bi.printing_amount)) as profit
        FROM invoices i
        JOIN booking_items bi ON i.booking_id = bi.booking_id
        WHERE i.type = 'tax' AND i.approval_status = 'approved' $entityCondInv 
          AND COALESCE(i.invoice_date, DATE(i.created_at)) >= '$fyStartDateStr' 
          AND COALESCE(i.invoice_date, DATE(i.created_at)) <= '$fyEndDateStr'
    ")->fetch();

    $revenue = $finStats['revenue'] ?: 0;
    $cost = $finStats['cost'] ?: 0;
    $profit = $finStats['profit'] ?: 0;
    $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

    // Monthly Data for Chart
    $monthlyData = $pdo->query("
        SELECT DATE_FORMAT(COALESCE(i.invoice_date, i.created_at), '%b %Y') as month, 
               SUM(bi.amount) as rev, 
               SUM(bi.amount - (bi.purchase_amount + bi.printing_amount)) as prof
        FROM invoices i
        JOIN booking_items bi ON i.booking_id = bi.booking_id
        WHERE i.type = 'tax' AND i.approval_status = 'approved' $entityCondInv 
          AND COALESCE(i.invoice_date, DATE(i.created_at)) >= '$fyStartDateStr' 
          AND COALESCE(i.invoice_date, DATE(i.created_at)) <= '$fyEndDateStr'
        GROUP BY month 
        ORDER BY MIN(COALESCE(i.invoice_date, i.created_at)) ASC 
        LIMIT 6
    ")->fetchAll();

    $chartLabels = json_encode(array_column($monthlyData, 'month'));
    $chartRevenue = json_encode(array_column($monthlyData, 'rev'));
    $chartProfit = json_encode(array_column($monthlyData, 'prof'));
}

$totalSites = 1;
$bookedSites = 0;
if ($canViewInventory) {
    $totalSites = $pdo->query("SELECT COUNT(*) FROM sites")->fetchColumn() ?: 1;
    $bookedSites = $pdo->query("SELECT COUNT(*) FROM sites WHERE status = 'booked'")->fetchColumn();
}

// New Dashboard Metrics & Stats calculations
$pendingCount = 0;
$pendingFeed = [];
if (hasRole('admin')) {
    $activeEntityId = $_SESSION['active_entity_id'] ?? null;
    $entityCondProp = $activeEntityId ? " AND entity_id = $activeEntityId" : "";
    $entityCondPO   = $activeEntityId ? " AND entity_id = $activeEntityId" : "";
    $entityCondBook = $activeEntityId ? " AND entity_id = $activeEntityId" : "";
    $entityCondInv  = $activeEntityId ? " AND entity_id = $activeEntityId" : "";
    $entityCondPay  = $activeEntityId ? " AND entity_id = $activeEntityId" : "";

    $entityCondPropAlias = $activeEntityId ? " AND p.entity_id = $activeEntityId" : "";
    $entityCondPOAlias   = $activeEntityId ? " AND po.entity_id = $activeEntityId" : "";
    $entityCondBookAlias = $activeEntityId ? " AND b.entity_id = $activeEntityId" : "";
    $entityCondInvAlias  = $activeEntityId ? " AND i.entity_id = $activeEntityId" : "";
    $entityCondPayAlias  = $activeEntityId ? " AND pay.entity_id = $activeEntityId" : "";

    $pendingProposals = $pdo->query("SELECT COUNT(*) FROM proposals WHERE approval_status = 'pending_approval' $entityCondProp")->fetchColumn();
    $pendingPOs       = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE approval_status = 'pending_approval' $entityCondPO")->fetchColumn();
    $pendingBookings  = $pdo->query("SELECT COUNT(*) FROM bookings WHERE approval_status = 'pending_approval' $entityCondBook")->fetchColumn();
    $pendingInvoices  = $pdo->query("SELECT COUNT(*) FROM invoices WHERE approval_status = 'pending_approval' $entityCondInv")->fetchColumn();
    $pendingClientPrintings = $pdo->query("SELECT COUNT(DISTINCT po_number) FROM client_printing_rates WHERE approval_status = 'pending_approval'")->fetchColumn();
    $pendingClientMountings = $pdo->query("SELECT COUNT(DISTINCT po_number) FROM client_mounting_rates WHERE approval_status = 'pending_approval'")->fetchColumn();
    $pendingPayments  = $pdo->query("SELECT COUNT(*) FROM payments WHERE approval_status = 'pending_approval' $entityCondPay")->fetchColumn();
    $pendingCount     = $pendingProposals + $pendingPOs + $pendingBookings + $pendingInvoices + $pendingClientPrintings + $pendingClientMountings + $pendingPayments;

    $pendingFeed = $pdo->query("
        SELECT CONVERT('Proposal' USING utf8mb4) COLLATE utf8mb4_general_ci as type, CONVERT(p.proposal_number USING utf8mb4) COLLATE utf8mb4_general_ci as ref_no, p.created_at, p.total_amount as amount, CONVERT('proposals' USING utf8mb4) COLLATE utf8mb4_general_ci as tab, CONVERT(e.name USING utf8mb4) COLLATE utf8mb4_general_ci as entity_name
        FROM proposals p LEFT JOIN entities e ON p.entity_id = e.id WHERE p.approval_status = 'pending_approval' $entityCondPropAlias
        UNION ALL
        SELECT CONVERT('PO' USING utf8mb4) COLLATE utf8mb4_general_ci as type, CONVERT(po.po_number USING utf8mb4) COLLATE utf8mb4_general_ci as ref_no, po.po_date as created_at, po.total_amount as amount, CONVERT('pos' USING utf8mb4) COLLATE utf8mb4_general_ci as tab, CONVERT(e.name USING utf8mb4) COLLATE utf8mb4_general_ci as entity_name
        FROM purchase_orders po LEFT JOIN entities e ON po.entity_id = e.id WHERE po.approval_status = 'pending_approval' $entityCondPOAlias
        UNION ALL
        SELECT CONVERT('Booking' USING utf8mb4) COLLATE utf8mb4_general_ci as type, CONVERT(COALESCE(b.booking_number, CONCAT('BK-', b.id)) USING utf8mb4) COLLATE utf8mb4_general_ci as ref_no, b.created_at, 0 as amount, CONVERT('bookings' USING utf8mb4) COLLATE utf8mb4_general_ci as tab, CONVERT(e.name USING utf8mb4) COLLATE utf8mb4_general_ci as entity_name
        FROM bookings b LEFT JOIN entities e ON b.entity_id = e.id WHERE b.approval_status = 'pending_approval' $entityCondBookAlias
        UNION ALL
        SELECT CONVERT('Invoice' USING utf8mb4) COLLATE utf8mb4_general_ci as type, CONVERT(i.invoice_number USING utf8mb4) COLLATE utf8mb4_general_ci as ref_no, i.created_at, i.total_amount as amount, CONVERT('invoices' USING utf8mb4) COLLATE utf8mb4_general_ci as tab, CONVERT(e.name USING utf8mb4) COLLATE utf8mb4_general_ci as entity_name
        FROM invoices i LEFT JOIN entities e ON i.entity_id = e.id WHERE i.approval_status = 'pending_approval' $entityCondInvAlias
        UNION ALL
        SELECT CONVERT('Payment' USING utf8mb4) COLLATE utf8mb4_general_ci as type, CONVERT(COALESCE(pay.transaction_id, CONCAT('ID: ', pay.id)) USING utf8mb4) COLLATE utf8mb4_general_ci as ref_no, pay.created_at, pay.amount, CONVERT('payments' USING utf8mb4) COLLATE utf8mb4_general_ci as tab, CONVERT(e.name USING utf8mb4) COLLATE utf8mb4_general_ci as entity_name
        FROM payments pay LEFT JOIN entities e ON pay.entity_id = e.id WHERE pay.approval_status = 'pending_approval' $entityCondPayAlias
        ORDER BY created_at ASC
        LIMIT 5
    ")->fetchAll();
}

$monthlyBilling = [];
$monthlyCollection = [];
$clientReceivables = [];
$vendorPayables = [];
$outstandingReceivables = 0;
$outstandingPayables = 0;
$chartMonths = '[]';
$chartBilling = '[]';
$chartCollection = '[]';
$currentMonthBilling = 0;
$currentMonthCollection = 0;
$selectedMonth = 'all';
$billingCardTitle = 'Billing';
$collectionCardTitle = 'Collection';
$monthsList = [];

if ($canViewFinancials) {
    // Period filter logic
    $periodFilter = $_GET['period'] ?? 'this_month';
    $fromDate = $_GET['from_date'] ?? '';
    $toDate = $_GET['to_date'] ?? '';

    // Dynamically calculate available Financial Years
    $currentFY = getFinancialYear();
    list($fyStartPart, $fyEndPart) = explode('-', $currentFY);
    $fyStartYearInt = 2000 + intval($fyStartPart);

    $availableFYs = [];
    for ($i = 0; $i < 4; $i++) {
        $sYear = $fyStartYearInt - $i;
        $eYear = $sYear + 1;
        $fyString = sprintf("%02d-%02d", $sYear % 100, $eYear % 100);
        $availableFYs[] = $fyString;
    }

    $dateCondInvoice = "1=1";
    $dateCondPayment = "1=1";
    $dateCondPO = "1=1";
    $dateCondProposal = "1=1";

    if ($periodFilter === 'this_month') {
        $today = date('Y-m-d');
        $currentMonthStart = date('Y-m-01');
        $currentMonthEnd = date('Y-m-t');
        if ($today >= $fyStartDateStr && $today <= $fyEndDateStr) {
            $dateCondInvoice = "COALESCE(invoice_date, DATE(created_at)) >= '$currentMonthStart' AND COALESCE(invoice_date, DATE(created_at)) <= '$currentMonthEnd'";
            $dateCondPayment = "payment_date >= '$currentMonthStart' AND payment_date <= '$currentMonthEnd'";
            $dateCondPO = "po_date >= '$currentMonthStart' AND po_date <= '$currentMonthEnd'";
            $dateCondProposal = "created_at >= '$currentMonthStart 00:00:00' AND created_at <= '$currentMonthEnd 23:59:59'";
            $billingCardTitle = "Billing (This Month)";
            $collectionCardTitle = "Collection (This Month)";
        } else {
            $aprilStart = "$selectedStartYear-04-01";
            $aprilEnd = "$selectedStartYear-04-30";
            $dateCondInvoice = "COALESCE(invoice_date, DATE(created_at)) >= '$aprilStart' AND COALESCE(invoice_date, DATE(created_at)) <= '$aprilEnd'";
            $dateCondPayment = "payment_date >= '$aprilStart' AND payment_date <= '$aprilEnd'";
            $dateCondPO = "po_date >= '$aprilStart' AND po_date <= '$aprilEnd'";
            $dateCondProposal = "created_at >= '$aprilStart 00:00:00' AND created_at <= '$aprilEnd 23:59:59'";
            $billingCardTitle = "Billing (April $selectedStartYear)";
            $collectionCardTitle = "Collection (April $selectedStartYear)";
        }
    } elseif ($periodFilter === 'last_month') {
        $today = date('Y-m-d');
        $lastMonthStart = date('Y-m-d', strtotime('first day of last month'));
        $lastMonthEnd = date('Y-m-d', strtotime('last day of last month'));
        if ($today >= $fyStartDateStr && $today <= $fyEndDateStr) {
            $dateCondInvoice = "COALESCE(invoice_date, DATE(created_at)) >= '$lastMonthStart' AND COALESCE(invoice_date, DATE(created_at)) <= '$lastMonthEnd'";
            $dateCondPayment = "payment_date >= '$lastMonthStart' AND payment_date <= '$lastMonthEnd'";
            $dateCondPO = "po_date >= '$lastMonthStart' AND po_date <= '$lastMonthEnd'";
            $dateCondProposal = "created_at >= '$lastMonthStart 00:00:00' AND created_at <= '$lastMonthEnd 23:59:59'";
            $billingCardTitle = "Billing (Last Month)";
            $collectionCardTitle = "Collection (Last Month)";
        } else {
            $mayStart = "$selectedStartYear-05-01";
            $mayEnd = "$selectedStartYear-05-31";
            $dateCondInvoice = "COALESCE(invoice_date, DATE(created_at)) >= '$mayStart' AND COALESCE(invoice_date, DATE(created_at)) <= '$mayEnd'";
            $dateCondPayment = "payment_date >= '$mayStart' AND payment_date <= '$mayEnd'";
            $dateCondPO = "po_date >= '$mayStart' AND po_date <= '$mayEnd'";
            $dateCondProposal = "created_at >= '$mayStart 00:00:00' AND created_at <= '$mayEnd 23:59:59'";
            $billingCardTitle = "Billing (May $selectedStartYear)";
            $collectionCardTitle = "Collection (May $selectedStartYear)";
        }
    } elseif ($periodFilter === 'this_quarter') {
        $today = date('Y-m-d');
        if ($today >= $fyStartDateStr && $today <= $fyEndDateStr) {
            $dateCondInvoice = "QUARTER(COALESCE(invoice_date, DATE(created_at))) = QUARTER(CURDATE()) AND YEAR(COALESCE(invoice_date, DATE(created_at))) = YEAR(CURDATE())";
            $dateCondPayment = "QUARTER(payment_date) = QUARTER(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
            $dateCondPO = "QUARTER(po_date) = QUARTER(CURDATE()) AND YEAR(po_date) = YEAR(CURDATE())";
            $dateCondProposal = "QUARTER(created_at) = QUARTER(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
            $billingCardTitle = "Billing (This Quarter)";
            $collectionCardTitle = "Collection (This Quarter)";
        } else {
            $dateCondInvoice = "COALESCE(invoice_date, DATE(created_at)) >= '$fyStartDateStr' AND COALESCE(invoice_date, DATE(created_at)) <= '$selectedStartYear-06-30'";
            $dateCondPayment = "payment_date >= '$fyStartDateStr' AND payment_date <= '$selectedStartYear-06-30'";
            $dateCondPO = "po_date >= '$fyStartDateStr' AND po_date <= '$selectedStartYear-06-30'";
            $dateCondProposal = "created_at >= '$fyStartDateStr 00:00:00' AND created_at <= '$selectedStartYear-06-30 23:59:59'";
            $billingCardTitle = "Billing (Q1 FY $selectedFY)";
            $collectionCardTitle = "Collection (Q1 FY $selectedFY)";
        }
    } elseif ($periodFilter === 'this_year') {
        $dateCondInvoice = "COALESCE(invoice_date, DATE(created_at)) >= '$fyStartDateStr' AND COALESCE(invoice_date, DATE(created_at)) <= '$fyEndDateStr'";
        $dateCondPayment = "payment_date >= '$fyStartDateStr' AND payment_date <= '$fyEndDateStr'";
        $dateCondPO = "po_date >= '$fyStartDateStr' AND po_date <= '$fyEndDateStr'";
        $dateCondProposal = "created_at >= '$fyStartDateStr 00:00:00' AND created_at <= '$fyEndDateStr 23:59:59'";
        $billingCardTitle = "Billing (FY 20$selectedStartPart-20$selectedEndPart)";
        $collectionCardTitle = "Collection (FY 20$selectedStartPart-20$selectedEndPart)";
    } elseif ($periodFilter === 'custom' && !empty($fromDate) && !empty($toDate)) {
        $dateCondInvoice = "COALESCE(invoice_date, DATE(created_at)) >= '$fromDate' AND COALESCE(invoice_date, DATE(created_at)) <= '$toDate'";
        $dateCondPayment = "payment_date >= '$fromDate' AND payment_date <= '$toDate'";
        $dateCondPO = "po_date >= '$fromDate' AND po_date <= '$toDate'";
        $dateCondProposal = "created_at >= '$fromDate 00:00:00' AND created_at <= '$toDate 23:59:59'";
        $billingCardTitle = "Billing (Custom)";
        $collectionCardTitle = "Collection (Custom)";
    } else {
        $periodFilter = 'all';
        $billingCardTitle = "Billing (All Time)";
        $collectionCardTitle = "Collection (All Time)";
    }

    // Month-wise Billing (Invoiced)
    $activeEntityId = $_SESSION['active_entity_id'] ?? null;
    $entityCondInv = $activeEntityId ? " AND entity_id = $activeEntityId" : "";
    $entityCondPay = $activeEntityId ? " AND entity_id = $activeEntityId" : "";
    $entityCondPO   = $activeEntityId ? " AND entity_id = $activeEntityId" : "";

    $monthlyBillingQuery = $pdo->query("
        SELECT DATE_FORMAT(COALESCE(invoice_date, DATE(created_at)), '%b %Y') as month, SUM(total_amount) as total_billing
        FROM invoices
        WHERE type = 'tax' AND approval_status = 'approved' AND $dateCondInvoice $entityCondInv
        GROUP BY month
        ORDER BY MIN(COALESCE(invoice_date, DATE(created_at))) ASC
    ")->fetchAll();

    // Month-wise Collection (Received)
    $monthlyCollectionQuery = $pdo->query("
        SELECT DATE_FORMAT(payment_date, '%b %Y') as month, SUM(amount) as total_collection
        FROM payments
        WHERE type = 'receivable' AND approval_status = 'approved' AND $dateCondPayment $entityCondPay
        GROUP BY month
        ORDER BY MIN(payment_date) ASC
    ")->fetchAll();

    // Align Billing and Collections
    $billingMap = [];
    foreach ($monthlyBillingQuery as $row) {
        $billingMap[$row['month']] = (float)$row['total_billing'];
    }
    $collectionMap = [];
    foreach ($monthlyCollectionQuery as $row) {
        $collectionMap[$row['month']] = (float)$row['total_collection'];
    }

    $allMonthsData = $pdo->query("
        SELECT month FROM (
            SELECT DATE_FORMAT(COALESCE(invoice_date, DATE(created_at)), '%b %Y') as month, MIN(COALESCE(invoice_date, DATE(created_at))) as min_date FROM invoices WHERE type = 'tax' AND approval_status = 'approved' AND $dateCondInvoice $entityCondInv GROUP BY month
            UNION DISTINCT
            SELECT DATE_FORMAT(payment_date, '%b %Y') as month, MIN(payment_date) as min_date FROM payments WHERE type = 'receivable' AND approval_status = 'approved' AND $dateCondPayment $entityCondPay GROUP BY month
        ) combined
        GROUP BY month
        ORDER BY MIN(min_date) ASC
    ")->fetchAll();

    $monthsList = [];
    $billingList = [];
    $collectionList = [];
    foreach ($allMonthsData as $row) {
        $m = $row['month'];
        $monthsList[] = $m;
        $billingList[] = $billingMap[$m] ?? 0;
        $collectionList[] = $collectionMap[$m] ?? 0;
    }
    $chartMonths = json_encode($monthsList);
    $chartBilling = json_encode($billingList);
    $chartCollection = json_encode($collectionList);

    // Outstanding A/R and A/P totals (all time)
    $totalInvoicedAmount = (float)$pdo->query("SELECT SUM(total_amount) FROM invoices WHERE type = 'tax' AND approval_status = 'approved' $entityCondInv")->fetchColumn() ?: 0;
    $totalCollectedAmount = (float)$pdo->query("SELECT SUM(amount) FROM payments WHERE type = 'receivable' AND approval_status = 'approved' $entityCondPay")->fetchColumn() ?: 0;
    $outstandingReceivables = max(0, $totalInvoicedAmount - $totalCollectedAmount);

    $totalPOAmount = (float)$pdo->query("SELECT SUM(total_amount) FROM purchase_orders WHERE approval_status = 'approved' $entityCondPO")->fetchColumn() ?: 0;
    $totalPaidVendor = (float)$pdo->query("SELECT SUM(amount) FROM payments WHERE type = 'payable' AND approval_status = 'approved' $entityCondPay")->fetchColumn() ?: 0;
    $outstandingPayables = max(0, $totalPOAmount - $totalPaidVendor);

    // Metric cards totals for the selected period
    $currentMonthBilling = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE type = 'tax' AND approval_status = 'approved' AND $dateCondInvoice $entityCondInv")->fetchColumn() ?: 0;
    $currentMonthCollection = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE type = 'receivable' AND approval_status = 'approved' AND $dateCondPayment $entityCondPay")->fetchColumn() ?: 0;

    // Client Receivables List (Top 5)
    $dateCondInvoiceWithAlias = str_replace(
        ['created_at', 'invoice_date'], 
        ['i.created_at', 'i.invoice_date'], 
        $dateCondInvoice
    );
    $dateCondPaymentWithAlias = str_replace('payment_date', 'pay.payment_date', $dateCondPayment);
    $dateCondPOWithAlias = str_replace('po_date', 'po.po_date', $dateCondPO);

    $clientReceivables = $pdo->query("
        SELECT p.name, 
               COALESCE(SUM(i.total_amount), 0) as total_invoiced,
               COALESCE(SUM(pay.amount), 0) as total_paid,
               (COALESCE(SUM(i.total_amount), 0) - COALESCE(SUM(pay.amount), 0)) as balance_due
         FROM partners p
         LEFT JOIN bookings b ON b.client_id = p.id " . ($activeEntityId ? "AND b.entity_id = $activeEntityId" : "") . "
         LEFT JOIN invoices i ON i.booking_id = b.id AND i.approval_status = 'approved' AND $dateCondInvoiceWithAlias " . ($activeEntityId ? "AND i.entity_id = $activeEntityId" : "") . "
         LEFT JOIN payments pay ON pay.invoice_id = i.id AND pay.approval_status = 'approved' AND pay.type = 'receivable' AND $dateCondPaymentWithAlias " . ($activeEntityId ? "AND pay.entity_id = $activeEntityId" : "") . "
         WHERE p.type = 'client'
         GROUP BY p.id, p.name
         HAVING balance_due > 0
         ORDER BY balance_due DESC LIMIT 5
     ")->fetchAll();

     // Vendor Payables List (Top 5)
     $vendorPayables = $pdo->query("
         SELECT p.name, 
                COALESCE(SUM(po.total_amount), 0) as total_po,
                COALESCE(SUM(pay.amount), 0) as total_paid,
                (COALESCE(SUM(po.total_amount), 0) - COALESCE(SUM(pay.amount), 0)) as balance_payable
         FROM partners p
         LEFT JOIN purchase_orders po ON po.vendor_id = p.id AND po.approval_status = 'approved' AND $dateCondPOWithAlias " . ($activeEntityId ? "AND po.entity_id = $activeEntityId" : "") . "
         LEFT JOIN payments pay ON pay.proposal_id = po.id AND pay.approval_status = 'approved' AND pay.type = 'payable' AND $dateCondPaymentWithAlias " . ($activeEntityId ? "AND pay.entity_id = $activeEntityId" : "") . "
         WHERE p.type = 'vendor'
         GROUP BY p.id, p.name
         HAVING balance_payable > 0
         ORDER BY balance_payable DESC LIMIT 5
     ")->fetchAll();
}

$upcomingMountings = [];
$expiringCampaigns = [];
if ($canViewBookings) {
    // Campaign Operations Radar
    $upcomingMountings = $pdo->query("
        SELECT b.id, b.start_date, p.proposal_number, pr.name as client_name,
               DATEDIFF(b.start_date, CURRENT_DATE()) as days_until
        FROM bookings b
        JOIN proposals p ON b.proposal_id = p.id
        JOIN partners pr ON p.client_id = pr.id
        WHERE b.status IN ('pending', 'mounting') AND b.start_date >= CURRENT_DATE()
        ORDER BY b.start_date ASC
        LIMIT 5
    ")->fetchAll();

    $expiringCampaigns = $pdo->query("
        SELECT b.id, b.end_date, p.proposal_number, pr.name as client_name,
               DATEDIFF(b.end_date, CURRENT_DATE()) as days_left
        FROM bookings b
        JOIN proposals p ON b.proposal_id = p.id
        JOIN partners pr ON p.client_id = pr.id
        WHERE b.status = 'active' AND b.end_date >= CURRENT_DATE()
        ORDER BY b.end_date ASC
        LIMIT 5
    ")->fetchAll();
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-container" style="padding: 15px; background: #f8fafc; min-height: 100vh;">
    <!-- Dashboard Header Filter Panel -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #1e293b;">Performance Overview</h3>
        <?php if ($canViewFinancials): ?>
        <form method="GET" id="filterForm" style="display: flex; align-items: center; gap: 8px; background: white; padding: 6px 12px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; flex-wrap: wrap;">
            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; display: inline-flex; align-items: center; gap: 5px;"><i class="fas fa-filter" style="color: #4f46e5;"></i> Period:</span>
            <select name="period" id="filter_period" onchange="toggleCustomDates(this.value)" style="font-size: 0.8rem; font-weight: 700; border: none; padding: 2px 24px 2px 8px; border-radius: 6px; cursor: pointer; background: transparent; color: #1e293b; outline: none; font-family: inherit;">
                <option value="this_month" <?php echo $periodFilter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                <option value="last_month" <?php echo $periodFilter === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                <option value="this_quarter" <?php echo $periodFilter === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                <option value="this_year" <?php echo $periodFilter === 'this_year' ? 'selected' : ''; ?>>This Financial Year</option>
                <option value="all" <?php echo $periodFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                <option value="custom" <?php echo $periodFilter === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
            </select>

            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; display: inline-flex; align-items: center; gap: 5px; margin-left: 8px; border-left: 1px solid #e2e8f0; padding-left: 8px;"><i class="fas fa-calendar-alt" style="color: #0d9488;"></i> FY:</span>
            <select name="set_fy" onchange="document.getElementById('filterForm').submit()" style="font-size: 0.8rem; font-weight: 700; border: none; padding: 2px 24px 2px 8px; border-radius: 6px; cursor: pointer; background: transparent; color: #1e293b; outline: none; font-family: inherit;">
                <?php foreach ($availableFYs as $fy): ?>
                    <option value="<?php echo $fy; ?>" <?php echo $selectedFY === $fy ? 'selected' : ''; ?>>
                        FY 20<?php echo explode('-', $fy)[0] . '-20' . explode('-', $fy)[1]; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <div id="custom_date_range" style="display: <?php echo $periodFilter === 'custom' ? 'flex' : 'none'; ?>; gap: 8px; align-items: center;">
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" style="font-size: 0.8rem; border: 1px solid #cbd5e1; border-radius: 6px; padding: 2px 6px; font-family: inherit;">
                <span style="font-size: 0.75rem; color: #64748b;">to</span>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" style="font-size: 0.8rem; border: 1px solid #cbd5e1; border-radius: 6px; padding: 2px 6px; font-family: inherit;">
                <button type="submit" class="btn btn-primary" style="padding: 2px 8px; font-size: 0.75rem; height: 24px; border-radius: 6px; background: #0d9488; border-color: #0d9488; font-weight: 700; line-height: 1; display: inline-flex; align-items: center; justify-content: center; box-shadow: none;">Go</button>
            </div>
        </form>
        <script>
        function toggleCustomDates(val) {
            document.getElementById('custom_date_range').style.display = val === 'custom' ? 'flex' : 'none';
            if (val !== 'custom') {
                document.getElementById('filterForm').submit();
            }
        }
        </script>
        <?php endif; ?>
    </div>

    <!-- Row 1: Metrics Row -->
    <div class="metrics-grid">
        <!-- Backlog widget for Admin -->
        <?php if (hasRole('admin')): ?>
        <a href="modules/admin/approvals.php" class="metric-card g-orange-red" style="text-decoration: none;">
            <div class="m-content">
                <i class="fas fa-tasks"></i>
                <div class="m-data">
                    <span>Pending Approvals</span>
                    <h3><?php echo $pendingCount; ?> Items</h3>
                </div>
            </div>
            <?php if ($pendingCount > 0): ?>
                <div class="m-mini-badge" style="background: #ef4444; border: 1px solid white;">Action Required</div>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if ($canViewFinancials): ?>
        <div class="metric-card g-blue">
            <div class="m-content">
                <i class="fas fa-chart-bar"></i>
                <div class="m-data">
                    <span><?php echo $billingCardTitle; ?></span>
                    <h3><?php echo formatCurrency($currentMonthBilling); ?></h3>
                </div>
            </div>
        </div>

        <div class="metric-card g-emerald">
            <div class="m-content">
                <i class="fas fa-hand-holding-usd"></i>
                <div class="m-data">
                    <span><?php echo $collectionCardTitle; ?></span>
                    <h3><?php echo formatCurrency($currentMonthCollection); ?></h3>
                </div>
            </div>
        </div>

        <div class="metric-card g-amber">
            <div class="m-content">
                <i class="fas fa-balance-scale"></i>
                <div class="m-data" style="width: 100%;">
                    <span>A/R vs A/P Outstanding</span>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                        <span style="font-size: 0.8rem; font-weight: 800; color: #fff;">Client (AR): <?php echo formatCurrency($outstandingReceivables); ?></span>
                        <span style="font-size: 0.8rem; font-weight: 800; color: #ffe4e6;">Vendor (AP): <?php echo formatCurrency($outstandingPayables); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($canViewInventory): ?>
        <div class="metric-card g-teal">
            <div class="m-content">
                <i class="fas fa-map-marker-alt"></i>
                <div class="m-data">
                    <span>Site Occupancy</span>
                    <h3><?php echo $bookedSites; ?> / <?php echo $totalSites; ?></h3>
                </div>
            </div>
            <div class="m-mini-badge" style="background: rgba(255,255,255,0.25);"><?php echo number_format(($bookedSites / $totalSites) * 100, 1); ?>%</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Row 2: Charts -->
    <div class="charts-row" style="<?php echo !$canViewFinancials ? 'grid-template-columns: 1fr;' : ''; ?>">
        <?php if ($canViewFinancials): ?>
        <div class="chart-box main-chart">
            <div class="box-header">
                <h4>Billing vs Collection Trends (Month-Wise)</h4>
            </div>
            <div class="chart-wrapper">
                <canvas id="cashflowChart"></canvas>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($canViewInventory): ?>
        <div class="chart-box mini-chart">
            <div class="box-header">
                <h4>Media Type Distribution</h4>
            </div>
            <div class="chart-wrapper">
                <canvas id="typeChart"></canvas>
            </div>
            <?php
            $typeData = $pdo->query("SELECT type, COUNT(*) as count FROM sites GROUP BY type")->fetchAll();
            $typeLabels = json_encode(array_column($typeData, 'type'));
            $typeCounts = json_encode(array_column($typeData, 'count'));
            ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Row 3: Client Receivables, Vendor Payables & Profitability (In Short) -->
    <?php if ($canViewFinancials): ?>
    <div class="details-row" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); margin-bottom: 1.5rem;">
        <!-- Client Receivables (In Short) -->
        <div class="table-box">
            <div class="box-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h4>Client Receivables (In Short)</h4>
                <span style="font-size: 0.65rem; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 50px; font-weight: 700;">Top Due</span>
            </div>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th style="text-align: right;">Invoiced</th>
                        <th style="text-align: right;">Paid</th>
                        <th style="text-align: right;">Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientReceivables)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">No outstanding client receivables.</td></tr>
                    <?php else: ?>
                        <?php foreach($clientReceivables as $cr): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(htmlspecialchars_decode($cr['name']), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td style="text-align: right; color: #64748b;"><?php echo formatCurrency($cr['total_invoiced']); ?></td>
                            <td style="text-align: right; color: #10b981;"><?php echo formatCurrency($cr['total_paid']); ?></td>
                            <td style="text-align: right; color: #ef4444; font-weight: 800;"><?php echo formatCurrency($cr['balance_due']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Vendor Payables (In Short) -->
        <div class="table-box">
            <div class="box-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h4>Vendor Payables (In Short)</h4>
                <span style="font-size: 0.65rem; background: #fef3c7; color: #b45309; padding: 2px 8px; border-radius: 50px; font-weight: 700;">Top Payable</span>
            </div>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th style="text-align: right;">PO Total</th>
                        <th style="text-align: right;">Disbursed</th>
                        <th style="text-align: right;">Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendorPayables)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">No outstanding vendor payables.</td></tr>
                    <?php else: ?>
                        <?php foreach($vendorPayables as $vp): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(htmlspecialchars_decode($vp['name']), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td style="text-align: right; color: #64748b;"><?php echo formatCurrency($vp['total_po']); ?></td>
                            <td style="text-align: right; color: #10b981;"><?php echo formatCurrency($vp['total_paid']); ?></td>
                            <td style="text-align: right; color: #f59e0b; font-weight: 800;"><?php echo formatCurrency($vp['balance_payable']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Vendor Site Profitability % (In Short) -->
        <div class="table-box">
            <div class="box-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h4>Vendor Site Profitability (In Short)</h4>
                <span style="font-size: 0.65rem; background: #ecfdf5; color: #047857; padding: 2px 8px; border-radius: 50px; font-weight: 700;">Top Margins</span>
            </div>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th style="text-align: right;">Sales</th>
                        <th style="text-align: right;">Profit</th>
                        <th style="text-align: right;">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $dateCondInvoiceWithAlias = str_replace(
                        ['created_at', 'invoice_date'], 
                        ['i.created_at', 'i.invoice_date'], 
                        $dateCondInvoice
                    );
                    $activeEntityId = $_SESSION['active_entity_id'] ?? null;
                    $entityCondInv = $activeEntityId ? " AND i.entity_id = $activeEntityId" : "";
                    
                    $vendorProfitability = $pdo->query("
                        SELECT 
                            p.name as vendor_name,
                            SUM(bi.amount) as total_revenue,
                            SUM(bi.amount - (bi.purchase_amount + bi.printing_amount)) as total_profit,
                            (SUM(bi.amount - (bi.purchase_amount + bi.printing_amount)) / SUM(bi.amount)) * 100 as profit_margin
                        FROM partners p
                        JOIN sites s ON s.vendor_id = p.id
                        JOIN booking_items bi ON bi.site_id = s.id
                        JOIN invoices i ON i.booking_id = bi.booking_id
                        WHERE p.type = 'vendor' 
                          AND i.type = 'tax' 
                          AND i.approval_status = 'approved'
                          $entityCondInv
                          AND $dateCondInvoiceWithAlias
                        GROUP BY p.id, p.name
                        ORDER BY total_profit DESC
                        LIMIT 5
                    ")->fetchAll();
                    
                    if (empty($vendorProfitability)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">No vendor site bookings recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach($vendorProfitability as $vp): 
                            $margin = (float)$vp['profit_margin'];
                            $badgeColor = $margin >= 20 ? '#10b981' : ($margin >= 10 ? '#f59e0b' : '#ef4444');
                            $bgColor = $margin >= 20 ? '#ecfdf5' : ($margin >= 10 ? '#fffbeb' : '#fef2f2');
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(htmlspecialchars_decode($vp['vendor_name']), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td style="text-align: right; color: #64748b;"><?php echo formatCurrency($vp['total_revenue']); ?></td>
                            <td style="text-align: right; color: #10b981; font-weight: 700;"><?php echo formatCurrency($vp['total_profit']); ?></td>
                            <td style="text-align: right;">
                                <span style="background: <?php echo $bgColor; ?>; color: <?php echo $badgeColor; ?>; padding: 2px 6px; border-radius: 6px; font-weight: 800; font-size: 0.7rem;">
                                    <?php echo number_format($margin, 1); ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Row 4: Pending Backlog Feed & Campaign Operations Radar -->
    <div class="details-row" style="grid-template-columns: <?php echo hasRole('admin') ? '1.2fr 1fr' : '1fr'; ?>; margin-bottom: 1.5rem;">
        <!-- Pending Approvals Feed -->
        <?php if (hasRole('admin')): ?>
        <div class="table-box">
            <div class="box-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h4>Pending Approvals Quick Feed</h4>
                <a href="modules/admin/approvals.php" style="font-size: 0.75rem; text-decoration: none; color: #4f46e5; font-weight: 700;">View Queue <i class="fas fa-arrow-right"></i></a>
            </div>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Ref Number</th>
                        <th>Requested Date</th>
                        <th style="text-align: right;">Amount</th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingFeed)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">No pending approvals in queue.</td></tr>
                    <?php else: ?>
                        <?php foreach($pendingFeed as $pf): ?>
                        <tr>
                            <td>
                                <span class="badge" style="background: <?php 
                                    echo $pf['type'] === 'Invoice' ? '#f3e8ff; color: #7e22ce;' : 
                                        ($pf['type'] === 'PO' ? '#fee2e2; color: #dc2626;' : 
                                        ($pf['type'] === 'Proposal' ? '#fef3c7; color: #d97706;' : 
                                        ($pf['type'] === 'Payment' ? '#ecfdf5; color: #059669;' : '#e0f2fe; color: #0284c7;')));
                                ?>; border-radius: 6px; padding: 2px 8px; font-weight: 700; font-size: 0.65rem;">
                                    <?php echo $pf['type']; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo $pf['ref_no']; ?></strong>
                                <?php if (!empty($pf['entity_name'])): ?>
                                    <div style="font-size: 0.65rem; color: #0d9488; font-weight: 700; margin-top: 2px;">
                                        <i class="fas fa-building" style="font-size: 0.6rem;"></i> <?php echo htmlspecialchars($pf['entity_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="color: #64748b;"><?php echo date('d M Y', strtotime($pf['created_at'])); ?></td>
                            <td style="text-align: right; font-weight: 700;"><?php echo $pf['amount'] > 0 ? formatCurrency($pf['amount']) : '-'; ?></td>
                            <td style="text-align: center;">
                                <a href="modules/admin/approvals.php?tab=<?php echo $pf['tab']; ?>" class="btn btn-primary" style="padding: 2px 10px; font-size: 0.65rem; border-radius: 6px; text-decoration: none;"><i class="fas fa-eye"></i> Review</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Campaign Operations Radar -->
        <?php if ($canViewBookings): ?>
        <div class="table-box">
            <div class="box-header">
                <h4>Campaign Operations Radar</h4>
            </div>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Section 1: Upcoming Starts -->
                <div>
                    <h5 style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><i class="fas fa-play-circle" style="color: #10b981;"></i> Upcoming Mountings (Starts)</h5>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <?php if (empty($upcomingMountings)): ?>
                            <div style="font-size: 0.7rem; color: #94a3b8; background: #f8fafc; padding: 8px; border-radius: 8px;">No campaigns starting in the next 7 days.</div>
                        <?php else: ?>
                            <?php foreach($upcomingMountings as $um): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 8px; border-radius: 8px;">
                                <div style="font-size: 0.7rem; color: #1e293b;">
                                    <strong><?php echo htmlspecialchars(htmlspecialchars_decode($um['client_name']), ENT_QUOTES, 'UTF-8'); ?></strong> 
                                    <span style="color: #64748b;">(<?php echo $um['proposal_number']; ?>)</span>
                                </div>
                                <span style="font-size: 0.65rem; background: #ecfdf5; color: #047857; padding: 2px 8px; border-radius: 40px; font-weight: 700;">
                                    <?php echo $um['days_until'] == 0 ? 'Starts Today' : 'In ' . $um['days_until'] . ' days'; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 2: Expirations -->
                <div>
                    <h5 style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><i class="fas fa-stop-circle" style="color: #ef4444;"></i> Expiring Campaigns (Ends)</h5>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <?php if (empty($expiringCampaigns)): ?>
                            <div style="font-size: 0.7rem; color: #94a3b8; background: #f8fafc; padding: 8px; border-radius: 8px;">No active campaigns ending in the next 15 days.</div>
                        <?php else: ?>
                            <?php foreach($expiringCampaigns as $ec): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 8px; border-radius: 8px;">
                                <div style="font-size: 0.7rem; color: #1e293b;">
                                    <strong><?php echo htmlspecialchars(htmlspecialchars_decode($ec['client_name']), ENT_QUOTES, 'UTF-8'); ?></strong> 
                                    <span style="color: #64748b;">(<?php echo $ec['proposal_number']; ?>)</span>
                                </div>
                                <span style="font-size: 0.65rem; background: #fef2f2; color: #b91c1c; padding: 2px 8px; border-radius: 40px; font-weight: 700;">
                                    <?php echo $ec['days_left'] == 0 ? 'Ends Today' : 'Ends in ' . $ec['days_left'] . ' days'; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Row 5: Footer Polished Row -->
    <div class="polished-footer" style="<?php 
        $footCols = 1; // Quick Links is always visible
        if ($canViewFinancials) $footCols += 2; // Top 10 Clients AND Top Proposals
        if (hasRole('admin')) $footCols++; // System Activity is admin only
        echo "grid-template-columns: repeat($footCols, 1fr);";
    ?>">
        <!-- Top 10 Clients -->
        <?php if ($canViewFinancials): ?>
        <div class="footer-card">
            <h5><i class="fas fa-medal" style="color: #ca8a04;"></i> Top 10 Clients</h5>
            <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                <?php
                $topClients = $pdo->query("
                    SELECT p.name, SUM(pr.grand_total) as total_spend 
                    FROM partners p JOIN proposals pr ON p.id = pr.client_id 
                    WHERE p.type='client' AND pr.status='confirmed'
                    GROUP BY p.id, p.name ORDER BY total_spend DESC LIMIT 10
                ")->fetchAll();
                $rank = 1;
                foreach($topClients as $c): ?>
                <div class="client-mini" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; border-bottom: 1px solid #f8fafc; padding-bottom: 6px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; width: 16px;">#<?php echo $rank++; ?></span>
                        <div class="c-icon" style="background: #e0f2fe; color: #0284c7; font-weight: 800; font-size: 0.75rem; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <?php echo substr(htmlspecialchars(htmlspecialchars_decode($c['name'])),0,1); ?>
                        </div>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #1e293b; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars(htmlspecialchars_decode($c['name']), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(htmlspecialchars_decode($c['name']), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <span style="font-size: 0.7rem; font-weight: 800; color: #10b981;"><?php echo formatCurrency($c['total_spend']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top Proposals -->
        <?php if ($canViewFinancials): ?>
        <div class="footer-card">
            <h5><i class="fas fa-crown" style="color: #ca8a04;"></i> Top Proposals</h5>
            <div class="client-list">
                <?php
                $topP = $pdo->query("
                    SELECT p.proposal_number, SUM(pi.amount) as rev, SUM((pi.sale_rate-pi.purchase_rate)*pi.days) as prof
                    FROM proposals p JOIN proposal_items pi ON p.id=pi.proposal_id
                    WHERE p.status != 'cancelled' GROUP BY p.id ORDER BY prof DESC LIMIT 4
                ")->fetchAll();
                foreach($topP as $tp): ?>
                <div class="client-mini">
                    <div class="c-icon" style="background: #eff6ff; color: #3b82f6;"><i class="fas fa-file-invoice"></i></div>
                    <div class="c-info">
                        <strong><?php echo $tp['proposal_number']; ?></strong>
                        <p style="color: #10b981; font-weight: 700; margin: 0; font-size: 0.7rem;">Profit: <?php echo formatCurrency($tp['prof']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (hasRole('admin')): ?>
        <div class="footer-card">
            <h5><i class="fas fa-history"></i> System Activity</h5>
            <div class="activity-list">
                <?php
                $activities = $pdo->query("SELECT a.*, u.username FROM activity_log a JOIN users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT 4")->fetchAll();
                foreach($activities as $a): ?>
                <div class="a-mini">
                    <strong><?php echo $a['username']; ?></strong> <?php echo $a['action']; ?>
                    <span><?php echo date('d M h:i A', strtotime($a['created_at'])); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="footer-card dark-card" style="background: #0f172a; color: white;">
            <h5 style="color: white;">Quick Links</h5>
            <div class="quick-links">
                <?php if (canAdd('proposals')): ?>
                <a href="modules/proposals/create.php"><i class="fas fa-plus"></i> New Proposal</a>
                <?php endif; ?>
                <?php if (canAdd('bookings')): ?>
                <a href="modules/operations/direct_booking.php"><i class="fas fa-plus-circle"></i> Direct Booking</a>
                <?php endif; ?>
                <?php if (canView('inventory')): ?>
                <a href="modules/inventory/sites.php"><i class="fas fa-th"></i> Inventory</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
.metric-card { 
    padding: 1.5rem 1.25rem; 
    border-radius: 20px; 
    color: white; 
    position: relative; 
    box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
    border: 1px solid rgba(255,255,255,0.15); 
    transition: transform 0.25s, box-shadow 0.25s; 
}
.metric-card:hover { 
    transform: translateY(-4px); 
    box-shadow: 0 15px 30px rgba(0,0,0,0.1); 
}
.m-content { display: flex; align-items: center; gap: 1.25rem; }
.m-content i { 
    font-size: 1.5rem; 
    background: rgba(255,255,255,0.2); 
    width: 50px; 
    height: 50px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    border-radius: 14px; 
}
.m-data span { font-size: 0.75rem; opacity: 0.85; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
.m-data h3 { font-size: 1.45rem; font-weight: 800; margin: 0; margin-top: 4px; }
.m-mini-badge { position: absolute; top: 12px; right: 12px; padding: 2px 8px; border-radius: 50px; font-size: 0.6rem; font-weight: 800; }

.g-orange-red { background: linear-gradient(135deg, #f43f5e, #e11d48); }
.g-blue { background: linear-gradient(135deg, #4f46e5, #3b82f6); }
.g-violet { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.g-emerald { background: linear-gradient(135deg, #10b981, #059669); }
.g-amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
.g-teal { background: linear-gradient(135deg, #0d9488, #0f766e); }

.charts-row { display: grid; grid-template-columns: 2fr 1.1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.chart-box { 
    background: white; 
    padding: 1.5rem; 
    border-radius: 24px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.03); 
    border: 1px solid rgba(226, 232, 240, 0.8);
}
.box-header h4 { font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0 0 1.25rem 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; }
.chart-wrapper { position: relative; width: 100%; height: 260px; }

.details-row { display: grid; gap: 1.5rem; }
.table-box { 
    background: white; 
    padding: 1.5rem; 
    border-radius: 24px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.03); 
    border: 1px solid rgba(226, 232, 240, 0.8);
}
.modern-table { width: 100%; border-collapse: collapse; }
.modern-table th { text-align: left; font-size: 0.65rem; color: #94a3b8; padding: 10px 8px; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; letter-spacing: 0.05em; }
.modern-table td { padding: 12px 8px; font-size: 0.75rem; border-bottom: 1px solid #f8fafc; color: #334155; }
.modern-table tr:hover td { background-color: #f8fafc; }

.polished-footer { display: grid; gap: 1.5rem; background: #f1f5f9; padding: 1.5rem; border-radius: 28px; }
.footer-card { background: white; padding: 1.5rem; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.footer-card h5 { font-size: 0.85rem; font-weight: 800; margin: 0 0 1.25rem 0; color: #0f172a; display: flex; align-items: center; gap: 8px; }
.client-mini { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
.c-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; }
.c-info strong { font-size: 0.75rem; display: block; color: #1e293b; }
.a-mini { font-size: 0.7rem; color: #475569; padding-bottom: 8px; border-bottom: 1px solid #f8fafc; margin-bottom: 8px; }
.a-mini span { display: block; font-size: 0.6rem; color: #94a3b8; margin-top: 2px; }
.quick-links a { display: block; background: rgba(255,255,255,0.08); color: white; padding: 12px; border-radius: 12px; text-decoration: none; margin-bottom: 8px; font-size: 0.75rem; font-weight: 700; transition: background 0.2s; }
.quick-links a:hover { background: rgba(255,255,255,0.15); }
@media (max-width: 1024px) {
    .charts-row { grid-template-columns: 1fr !important; }
    .details-row { grid-template-columns: 1fr !important; }
    .polished-footer { grid-template-columns: 1fr !important; }
}
</style>

<script>
// Combined Chart (Billing vs Collections trends)
<?php if ($canViewFinancials): ?>
if (document.getElementById('cashflowChart')) {
    new Chart(document.getElementById('cashflowChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo $chartMonths; ?>,
            datasets: [
                {
                    label: 'Total Billing',
                    data: <?php echo $chartBilling; ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.85)',
                    borderColor: '#6366f1',
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.6
                },
                {
                    label: 'Total Collection',
                    data: <?php echo $chartCollection; ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.6
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { boxWidth: 12, font: { size: 10, weight: 'bold' } }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 9 },
                        callback: function(value) { return '₹' + value.toLocaleString('en-IN'); }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 9 } }
                }
            }
        }
    });
}
<?php endif; ?>

// Media Type Distribution (Doughnut)
<?php if ($canViewInventory): ?>
if (document.getElementById('typeChart')) {
    new Chart(document.getElementById('typeChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $typeLabels; ?>,
            datasets: [{ 
                data: <?php echo $typeCounts; ?>, 
                backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#f43f5e', '#0d9488', '#8b5cf6'], 
                borderWidth: 0 
            }]
        },
        options: { 
            maintainAspectRatio: false, 
            cutout: '72%', 
            plugins: { 
                legend: { 
                    position: 'bottom', 
                    labels: { boxWidth: 8, font: { size: 9 } } 
                } 
            } 
        }
    });
}
<?php endif; ?>
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>


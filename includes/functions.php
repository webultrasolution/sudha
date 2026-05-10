<?php
// Global Helper Functions

/**
 * Sanitize input data
 */
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Calculate Square Footage
 */
function calculateSQFT($width, $height) {
    return round($width * $height, 2);
}

/**
 * Calculate GST Breakdown
 * Returns [cgst, sgst, igst, total_tax]
 */
function calculateGST($subtotal, $isInterState = false) {
    $tax = $subtotal * (GST_RATE / 100);
    if ($isInterState) {
        return [
            'cgst' => 0,
            'sgst' => 0,
            'igst' => $tax,
            'total' => $tax
        ];
    } else {
        return [
            'cgst' => $tax / 2,
            'sgst' => $tax / 2,
            'igst' => 0,
            'total' => $tax
        ];
    }
}

/**
 * Format Currency (INR)
 */
function formatCurrency($amount) {
    $amount = (float)($amount ?? 0);
    if (floor($amount) == $amount) {
        return '₹' . number_format($amount, 0);
    }
    return '₹' . number_format($amount, 2);
}

/**
 * Check User Role
 */
function hasRole($requiredRoles) {
    if (!isset($_SESSION['user_role'])) return false;
    if (is_array($requiredRoles)) {
        return in_array($_SESSION['user_role'], $requiredRoles);
    }
    return $_SESSION['user_role'] === $requiredRoles;
}

/**
 * Auth Middleware
 */
function checkAuth() {
    if (defined('BYPASS_AUTH') && BYPASS_AUTH === true) {
        return;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Send System Email (SMTP Placeholder)
 */
function sendSystemEmail($to, $subject, $message) {
    // In a real production environment, you would use PHPMailer or a similar library.
    // For now, we simulate the queuing logic.
    return true;
}

/**
 * Update Invoice Payment Status
 */
function updateInvoicePayment($invoiceId, $status) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE invoices SET payment_status = ? WHERE id = ?");
    return $stmt->execute([$status, $invoiceId]);
}
/**
 * Generate Pagination HTML
 */
function renderPagination($currentPage, $totalPages, $baseUrl, $paramName = 'page') {
    if ($totalPages <= 1) return '';
    
    $html = '<div class="pagination">';
    
    // Previous Link
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . '?' . $paramName . '=' . ($currentPage - 1) . '" class="page-link" title="Previous Page">&laquo;</a>';
    } else {
        $html .= '<span class="page-link disabled">&laquo;</span>';
    }
    
    // Page Numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $currentPage) ? 'active' : '';
        $html .= '<a href="' . $baseUrl . '?' . $paramName . '=' . $i . '" class="page-link ' . $activeClass . '">' . $i . '</a>';
    }
    
    // Next Link
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . '?' . $paramName . '=' . ($currentPage + 1) . '" class="page-link" title="Next Page">&raquo;</a>';
    } else {
        $html .= '<span class="page-link disabled">&raquo;</span>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Log System Activity
 */
function logActivity($action, $entityType = null, $entityId = null, $description = null) {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) return false;

    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $action, $entityType, $entityId, $description]);
}
/**
 * Convert number to words (Indian Style)
 */
function amountInWords($number) {
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(0 => '', 1 => 'one', 2 => 'two',
        3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
        7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve',
        13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
        16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
        19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
        40 => 'forty', 50 => 'fifty', 60 => 'sixty',
        70 => 'seventy', 80 => 'eighty', 90 => 'ninety');
    $digits = array('', 'hundred','thousand','lakh', 'crore');
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = implode('', array_reverse($str));
    $paise = ($decimal > 0) ? "." . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
    return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise;
}
/**
 * Get Setting Value from Database
 */
function getSetting($key, $default = '') {
    global $pdo;
    static $settings = [];
    
    if (empty($settings)) {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            return $default;
        }
    }
    
    return $settings[$key] ?? $default;
}
?>

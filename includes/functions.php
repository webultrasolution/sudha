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
 * Revert an entity's approval_status to pending_approval when a non-admin edits it.
 * Inserts a new approval_request if one doesn't already exist for this entity in pending state.
 * 
 * @param PDO    $pdo        Database connection
 * @param string $table      The table name (e.g. 'proposals', 'bookings')
 * @param int    $entityId   The row ID
 * @param string $entityType The entity type key (e.g. 'proposal', 'booking')
 * @param string $entityRef  Human-readable reference (e.g. proposal number)
 * @param int    $userId     The user making the change
 */
function revertToPendingOnEdit($pdo, $table, $entityId, $entityType, $entityRef, $userId) {
    // Only revert if it was previously approved (not already pending/rejected)
    $cur = $pdo->prepare("SELECT approval_status FROM $table WHERE id = ?");
    $cur->execute([$entityId]);
    $currentStatus = $cur->fetchColumn();

    if ($currentStatus === 'approved') {
        $pdo->prepare("UPDATE $table SET approval_status = 'pending_approval' WHERE id = ?")
            ->execute([$entityId]);

        // Delete old pending request if any, then insert new
        $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = ? AND entity_id = ? AND status = 'pending'")
            ->execute([$entityType, $entityId]);

        $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES (?, ?, ?, ?, 'pending')")
            ->execute([$entityType, $entityId, $entityRef . ' (Edited)', $userId]);
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
 * Generate a prefixed sequential reference using the next available table ID.
 * This is useful for invoices, mounting POs, printing POs, and similar documents.
 */
function generateSequentialReference($pdo, $table, $column, $prefix, $padding = 4) {
    $stmt = $pdo->prepare("SELECT MAX(id) FROM $table");
    $stmt->execute();
    $nextId = intval($stmt->fetchColumn()) + 1;
    return $prefix . str_pad($nextId, $padding, '0', STR_PAD_LEFT);
}

/**
 * Check User Role
 */
function hasRole($requiredRoles) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_role'])) return false;
    if (is_array($requiredRoles)) {
        return in_array($_SESSION['user_role'], $requiredRoles);
    }
    return $_SESSION['user_role'] === $requiredRoles;
}

/**
 * Check Granular Permissions (DB Backed)
 */
function getPerms($moduleKey) {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) session_start();
    $role = $_SESSION['user_role'] ?? '';
    
    if ($role === 'admin') {
        return ['can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1];
    }
    
    static $all_perms = null;
    if ($all_perms === null) {
        $stmt = $pdo->prepare("SELECT module_key, can_view, can_add, can_edit, can_delete FROM role_permissions WHERE role = ?");
        $stmt->execute([$role]);
        $all_perms = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }
    
    return $all_perms[$moduleKey] ?? ['can_view' => 0, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0];
}

function canView($module) { $p = getPerms($module); return $p['can_view'] == 1; }
function canAdd($module) { $p = getPerms($module); return $p['can_add'] == 1; }
function canEdit($module) { $p = getPerms($module); return $p['can_edit'] == 1; }
function canDelete($module) { $p = getPerms($module); return $p['can_delete'] == 1; }

// Backward Compatibility
function canAccess($module) { return canView($module); }

/**
 * Force a role check and redirect if not permitted
 */
function requireRole($roles) {
    if (!hasRole($roles)) {
        // You can redirect to a custom 403 page or just show an error
        echo "<div style='padding: 50px; text-align: center; font-family: sans-serif;'>
                <h1 style='color: #ef4444;'>Access Denied</h1>
                <p>Aapke paas is page ko dekhne ki permission nahi hai.</p>
                <a href='" . BASE_URL . "index.php' style='color: var(--primary);'>Dashboard par wapas jayein</a>
              </div>";
        exit;
    }
}

/**
 * Force a permission check and redirect if not permitted
 */
function requirePermission($module, $action = 'view') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') return; // Admin has all permissions
    
    $p = getPerms($module);
    $ok = false;
    if ($action === 'view') $ok = ($p['can_view'] == 1);
    elseif ($action === 'add') $ok = ($p['can_add'] == 1);
    elseif ($action === 'edit') $ok = ($p['can_edit'] == 1);
    elseif ($action === 'delete') $ok = ($p['can_delete'] == 1);
    
    if (!$ok) {
        echo "<div style='padding: 50px; text-align: center; font-family: Arial, sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8fafc;'>
                <div style='max-width: 500px; width: 100%; background: #fff; padding: 35px; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05); border-top: 4px solid #ef4444; box-sizing: border-box;'>
                    <i class='fas fa-shield-alt' style='font-size: 3.5rem; color: #ef4444; margin-bottom: 20px;'></i>
                    <h1 style='color: #0f172a; margin: 0 0 10px 0; font-size: 1.6rem; font-weight: 800; font-family: Outfit, sans-serif;'>Access Denied</h1>
                    <p style='color: #64748b; margin-bottom: 30px; font-size: 0.95rem; font-family: Outfit, sans-serif;'>Aapke paas is page ko dekhne ya action perform karne ki permission nahi hai.</p>
                    <a href='" . BASE_URL . "index.php' style='display: inline-block; padding: 12px 24px; background: #1cada9; color: white; text-decoration: none; border-radius: 8px; font-weight: 700; font-family: Outfit, sans-serif; transition: background 0.2s;'>Dashboard par wapas jayein</a>
                </div>
              </div>";
        exit;
    }
}


/**
 * Check if user is logged in
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
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
function renderPagination($currentPage, $totalPages, $baseUrl, $paramName = 'page', $extraParams = []) {
    if ($totalPages <= 1) return '';

    $queryString = '';
    if (!empty($extraParams)) {
        $queryString = http_build_query($extraParams) . '&';
    }

    $html = '<div class="pagination">';

    // Previous Link
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . '?' . $queryString . $paramName . '=' . ($currentPage - 1) . '" class="page-link" title="Previous Page">&laquo;</a>';
    } else {
        $html .= '<span class="page-link disabled">&laquo;</span>';
    }

    // Page Numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $currentPage) ? 'active' : '';
        $html .= '<a href="' . $baseUrl . '?' . $queryString . $paramName . '=' . $i . '" class="page-link ' . $activeClass . '">' . $i . '</a>';
    }

    // Next Link
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . '?' . $queryString . $paramName . '=' . ($currentPage + 1) . '" class="page-link" title="Next Page">&raquo;</a>';
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
    $decimal = (int)round(($number - floor($number)) * 100);
    $no = (int)floor($number);
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
        $curr_num = (int)floor($no % $divider);
        $no = (int)floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($curr_num) {
            $plural = (($counter = count($str)) && $curr_num > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($curr_num < 21) ? $words[$curr_num].' '. $digits[$counter]. $plural.' '.$hundred:$words[(int)floor($curr_num / 10) * 10].' '.$words[$curr_num % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = trim(implode('', array_reverse($str)));
    
    $paise = '';
    if ($decimal > 0) {
        if ($decimal < 21) {
            $paise = ' and ' . $words[$decimal] . ' Paise';
        } else {
            $paise = ' and ' . $words[(int)floor($decimal / 10) * 10] . ' ' . $words[$decimal % 10] . ' Paise';
        }
    }
    
    return ($Rupees ? ucwords($Rupees) . ' Rupees' : '') . ($paise ? ucwords($paise) . ' Only' : ' Only');
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

/**
 * Get active business entity / content
 */
function getActiveEntity() {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    // Handle switching
    if (isset($_GET['set_entity'])) {
        $_SESSION['active_entity_id'] = (int)$_GET['set_entity'];
        // Remove the parameter and redirect
        $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: " . $clean_url);
        exit;
    }

    if (!isset($_SESSION['active_entity_id'])) {
        $stmt = $pdo->query("SELECT id FROM entities LIMIT 1");
        $id = $stmt->fetchColumn();
        if ($id) {
            $_SESSION['active_entity_id'] = $id;
        }
    }

    if (isset($_SESSION['active_entity_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM entities WHERE id = ?");
        $stmt->execute([$_SESSION['active_entity_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return null;
}

/**
 * Resolve company/entity details for document generation.
 * Priority: $entity_id → $_SESSION['active_entity_id'] → getSetting() defaults.
 */
function resolveCompanyDetails($entity_id = null) {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) session_start();

    $data = [
        'name'         => getSetting('company_name',         'Sudha Creative & Advertising'),
        'gstin'        => getSetting('company_gstin',        '19AHRPT4740Q1Z6'),
        'pan'          => getSetting('company_pan',          'AHRPT4740Q'),
        'address'      => getSetting('company_address',      'Deshbandhu Para, P.O - Jhaljhalia, Dist - Malda - 732102, West Bengal'),
        'phone'        => getSetting('company_phone',        '8158854313'),
        'email'        => getSetting('company_email',        'sudhacreativemalda@gmail.com'),
        'logo'         => getSetting('company_logo',         ''),
        'letterhead'   => getSetting('company_letterhead',   ''),
        'signature'    => getSetting('company_signature',    'signature.png'),
        'bank_details'      => getSetting('company_bank_details', ''),
        'terms_conditions'  => getSetting('company_terms_conditions', ''),
        'msme_number'       => getSetting('company_msme_number', ''),
    ];

    $eid = $entity_id ?: ($_SESSION['active_entity_id'] ?? null);
    if ($eid) {
        $stmt = $pdo->prepare("SELECT * FROM entities WHERE id = ?");
        $stmt->execute([$eid]);
        $entity = $stmt->fetch();
        if ($entity) {
            $data['name'] = $entity['name'];
            if (!empty($entity['gstin']))             $data['gstin']            = $entity['gstin'];
            if (!empty($entity['pan']))               $data['pan']              = $entity['pan'];
            if (!empty($entity['address']))           $data['address']          = $entity['address'];
            if (!empty($entity['logo']))              $data['logo']             = $entity['logo'];
            if (!empty($entity['letterhead']))        $data['letterhead']       = $entity['letterhead'];
            if (!empty($entity['signature']))         $data['signature']        = $entity['signature'];
            if (!empty($entity['bank_details']))      $data['bank_details']     = $entity['bank_details'];
            if (!empty($entity['terms_conditions']))  $data['terms_conditions'] = $entity['terms_conditions'];
            if (!empty($entity['msme_number']))       $data['msme_number']      = $entity['msme_number'];
        }
    }

    return $data;
}

/**
 * Check if a partner / vendor has a valid GSTIN
 */
function vendorHasGST($gstin) {
    if (empty($gstin)) return false;
    $g = strtoupper(trim($gstin));
    if (in_array($g, ['', 'NA', 'N/A', 'N.A.', 'NONE', 'NO-GST', 'NO GST', '0'])) {
        return false;
    }
    // A valid GSTIN must be 15 characters long
    if (strlen($g) < 15) {
        return false;
    }
    return true;
}
?>

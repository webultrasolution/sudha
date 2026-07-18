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
    if ($table === 'bookings' || $entityType === 'booking') {
        return;
    }
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
 * Get the current Indian Financial Year (e.g. "26-27" for June 2026)
 */
function getFinancialYear($date = null, $systemOnly = false) {
    if ($date === null && !$systemOnly) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!empty($_SESSION['active_financial_year'])) {
            return $_SESSION['active_financial_year'];
        }
    }
    $timestamp = $date ? strtotime($date) : time();
    $month = intval(date('m', $timestamp));
    $year = intval(date('y', $timestamp));
    if ($month >= 4) {
        $startYear = $year;
        $endYear = $year + 1;
    } else {
        $startYear = $year - 1;
        $endYear = $year;
    }
    return sprintf("%02d-%02d", $startYear, $endYear);
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
 * Generate a sequence number based on a configuration stored in `document_sequences` table.
 * Resolves the `{FY}` placeholder with the current financial year and increments `next_value` atomically.
 */
function generateSequenceNumber($pdo, $moduleKey, $date = null, $entityId = null) {
    if ($moduleKey === 'invoice') {
        if ($entityId === null) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $entityId = $_SESSION['active_entity_id'] ?? null;
            if (!$entityId) {
                $stmt = $pdo->query("SELECT id FROM entities LIMIT 1");
                $entityId = $stmt->fetchColumn();
            }
        }
        $moduleKey = "invoice_" . $entityId;
    }

    $fy = getFinancialYear($date);
    
    // 1. Fetch sequence settings for this specific financial year
    $stmt = $pdo->prepare("SELECT * FROM `document_sequences` WHERE `module_key` = ? AND `financial_year` = ? FOR UPDATE");
    $stmt->execute([$moduleKey, $fy]);
    $seq = $stmt->fetch();
    
    if (!$seq) {
        // If not found, look up the template / any existing row for this module to copy settings
        $stmtTemplate = $pdo->prepare("SELECT prefix, padding FROM `document_sequences` WHERE `module_key` = ? LIMIT 1");
        $stmtTemplate->execute([$moduleKey]);
        $template = $stmtTemplate->fetch();
        
        $prefix = $template ? $template['prefix'] : '';
        $padding = $template ? intval($template['padding']) : 4;
        
        if (empty($prefix)) {
            $defaults = [
                'proposal' => ['prefix' => 'PR/{FY}/', 'padding' => 4],
                'booking' => ['prefix' => 'BK/{FY}/', 'padding' => 4],
                'vendor_printing_po' => ['prefix' => 'PPO/{FY}/', 'padding' => 4],
                'client_printing_po' => ['prefix' => 'SCRP/{FY}/', 'padding' => 4],
                'client_mounting_invoice' => ['prefix' => 'SCRM/{FY}/', 'padding' => 4],
                'vendor_booking_po' => ['prefix' => 'BPO/{FY}/', 'padding' => 4],
                'invoice' => ['prefix' => 'SCR/{FY}/', 'padding' => 4],
                'invoice_1' => ['prefix' => 'SCA/{FY}/', 'padding' => 3],
                'invoice_2' => ['prefix' => 'SCR/{FY}/', 'padding' => 3]
            ];
            $def = $defaults[$moduleKey] ?? ['prefix' => 'DOC/{FY}/', 'padding' => 4];
            if ($moduleKey !== 'invoice_1' && $moduleKey !== 'invoice_2' && strpos($moduleKey, 'invoice_') === 0) {
                $def = ['prefix' => 'SCR/{FY}/', 'padding' => 3];
            }
            $prefix = $def['prefix'];
            $padding = $def['padding'];
        }
        
        $nextValue = 1;
        if ($moduleKey === 'invoice_1') {
            $nextValue = 24;
        } elseif ($moduleKey === 'invoice_2') {
            $nextValue = 34;
        }
        
        // Insert new sequence entry for this new financial year starting at $nextValue
        $stmtIns = $pdo->prepare("INSERT INTO `document_sequences` (`module_key`, `financial_year`, `prefix`, `next_value`, `padding`) VALUES (?, ?, ?, ?, ?)");
        $stmtIns->execute([$moduleKey, $fy, $prefix, $nextValue, $padding]);
        
        // Refetch
        $stmt->execute([$moduleKey, $fy]);
        $seq = $stmt->fetch();
    }
    
    $nextValue = intval($seq['next_value']);
    $prefixTemplate = $seq['prefix'];
    $padding = intval($seq['padding']);
    
    $resolvedPrefix = str_replace('{FY}', $fy, $prefixTemplate);
    $formattedNumber = $resolvedPrefix . str_pad($nextValue, $padding, '0', STR_PAD_LEFT);
    
    $stmtUpd = $pdo->prepare("UPDATE `document_sequences` SET `next_value` = `next_value` + 1 WHERE `module_key` = ? AND `financial_year` = ?");
    $stmtUpd->execute([$moduleKey, $fy]);
    
    return $formattedNumber;
}

/**
 * Get next sequence number without incrementing the database value.
 * Useful for pre-filling input fields in forms.
 */
function getPreviewSequenceNumber($pdo, $moduleKey, $date = null, $entityId = null) {
    if ($moduleKey === 'invoice') {
        if ($entityId === null) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $entityId = $_SESSION['active_entity_id'] ?? null;
            if (!$entityId) {
                $stmt = $pdo->query("SELECT id FROM entities LIMIT 1");
                $entityId = $stmt->fetchColumn();
            }
        }
        $moduleKey = "invoice_" . $entityId;
    }

    $fy = getFinancialYear($date);
    $stmt = $pdo->prepare("SELECT * FROM `document_sequences` WHERE `module_key` = ? AND `financial_year` = ?");
    $stmt->execute([$moduleKey, $fy]);
    $seq = $stmt->fetch();
    
    if (!$seq) {
        // Find any existing entry to preview
        $stmtTemplate = $pdo->prepare("SELECT prefix, padding FROM `document_sequences` WHERE `module_key` = ? LIMIT 1");
        $stmtTemplate->execute([$moduleKey]);
        $template = $stmtTemplate->fetch();
        
        $prefixTemplate = $template ? $template['prefix'] : '';
        $padding = $template ? intval($template['padding']) : 4;
        
        if (empty($prefixTemplate)) {
            $defaults = [
                'proposal' => ['prefix' => 'PR/{FY}/', 'padding' => 4],
                'booking' => ['prefix' => 'BK/{FY}/', 'padding' => 4],
                'vendor_printing_po' => ['prefix' => 'PPO/{FY}/', 'padding' => 4],
                'client_printing_po' => ['prefix' => 'SCRP/{FY}/', 'padding' => 4],
                'client_mounting_invoice' => ['prefix' => 'SCRM/{FY}/', 'padding' => 4],
                'vendor_booking_po' => ['prefix' => 'BPO/{FY}/', 'padding' => 4],
                'invoice' => ['prefix' => 'SCR/{FY}/', 'padding' => 4],
                'invoice_1' => ['prefix' => 'SCA/{FY}/', 'padding' => 3],
                'invoice_2' => ['prefix' => 'SCR/{FY}/', 'padding' => 3]
            ];
            $def = $defaults[$moduleKey] ?? ['prefix' => 'DOC/{FY}/', 'padding' => 4];
            if ($moduleKey !== 'invoice_1' && $moduleKey !== 'invoice_2' && strpos($moduleKey, 'invoice_') === 0) {
                $def = ['prefix' => 'SCR/{FY}/', 'padding' => 3];
            }
            $prefixTemplate = $def['prefix'];
            $padding = $def['padding'];
        }
        
        $nextValue = 1;
        if ($moduleKey === 'invoice_1') {
            $nextValue = 24;
        } elseif ($moduleKey === 'invoice_2') {
            $nextValue = 34;
        }
    } else {
        $nextValue = intval($seq['next_value']);
        $prefixTemplate = $seq['prefix'];
        $padding = intval($seq['padding']);
    }
    
    $resolvedPrefix = str_replace('{FY}', $fy, $prefixTemplate);
    return $resolvedPrefix . str_pad($nextValue, $padding, '0', STR_PAD_LEFT);
}

/**
 * Get last generated sequence number without altering database.
 */
function getLastSequenceNumber($pdo, $moduleKey, $date = null, $entityId = null) {
    if ($moduleKey === 'invoice') {
        if ($entityId === null) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $entityId = $_SESSION['active_entity_id'] ?? null;
            if (!$entityId) {
                $stmt = $pdo->query("SELECT id FROM entities LIMIT 1");
                $entityId = $stmt->fetchColumn();
            }
        }
        $moduleKey = "invoice_" . $entityId;
    }

    $fy = getFinancialYear($date);
    $stmt = $pdo->prepare("SELECT * FROM `document_sequences` WHERE `module_key` = ? AND `financial_year` = ?");
    $stmt->execute([$moduleKey, $fy]);
    $seq = $stmt->fetch();
    
    if (!$seq) {
        $defaults = [
            'proposal' => ['prefix' => 'PR/{FY}/', 'padding' => 4, 'start' => 1],
            'booking' => ['prefix' => 'BK/{FY}/', 'padding' => 4, 'start' => 1],
            'vendor_printing_po' => ['prefix' => 'PPO/{FY}/', 'padding' => 4, 'start' => 1],
            'client_printing_po' => ['prefix' => 'SCRP/{FY}/', 'padding' => 4, 'start' => 1],
            'client_mounting_invoice' => ['prefix' => 'SCRM/{FY}/', 'padding' => 4, 'start' => 1],
            'vendor_booking_po' => ['prefix' => 'BPO/{FY}/', 'padding' => 4, 'start' => 1],
            'invoice' => ['prefix' => 'SCR/{FY}/', 'padding' => 4, 'start' => 1],
            'invoice_1' => ['prefix' => 'SCA/{FY}/', 'padding' => 3, 'start' => 24],
            'invoice_2' => ['prefix' => 'SCR/{FY}/', 'padding' => 3, 'start' => 34]
        ];
        $def = $defaults[$moduleKey] ?? ['prefix' => 'DOC/{FY}/', 'padding' => 4, 'start' => 1];
        if ($moduleKey !== 'invoice_1' && $moduleKey !== 'invoice_2' && strpos($moduleKey, 'invoice_') === 0) {
            $def = ['prefix' => 'SCR/{FY}/', 'padding' => 3, 'start' => 1];
        }
        $lastValue = $def['start'] - 1;
        $prefixTemplate = $def['prefix'];
        $padding = $def['padding'];
    } else {
        $lastValue = intval($seq['next_value']) - 1;
        $prefixTemplate = $seq['prefix'];
        $padding = intval($seq['padding']);
    }
    
    if ($lastValue <= 0) {
        return "None";
    }
    
    $resolvedPrefix = str_replace('{FY}', $fy, $prefixTemplate);
    return $resolvedPrefix . str_pad($lastValue, $padding, '0', STR_PAD_LEFT);
}

/**
 * Parse manual custom document reference and update sequences in database.
 */
function syncSequenceNextValue($pdo, $moduleKey, $customValue, $entityId = null) {
    if ($moduleKey === 'invoice') {
        if ($entityId === null) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $entityId = $_SESSION['active_entity_id'] ?? null;
        }
        $moduleKey = "invoice_" . $entityId;
    }
    
    $fy = getFinancialYear();
    
    // Extract trailing numbers (e.g. SCA/26-27/024 -> 24)
    if (preg_match('/(\d+)$/', trim($customValue), $matches)) {
        $numVal = intval($matches[1]);
        $nextVal = $numVal + 1;
        
        $stmt = $pdo->prepare("SELECT id, next_value FROM `document_sequences` WHERE `module_key` = ? AND `financial_year` = ?");
        $stmt->execute([$moduleKey, $fy]);
        $seq = $stmt->fetch();
        
        if ($seq) {
            if ($nextVal > intval($seq['next_value'])) {
                $stmtUp = $pdo->prepare("UPDATE `document_sequences` SET `next_value` = ? WHERE `id` = ?");
                $stmtUp->execute([$nextVal, $seq['id']]);
            }
        } else {
            $defaults = [
                'invoice_1' => ['prefix' => 'SCA/{FY}/', 'padding' => 3],
                'invoice_2' => ['prefix' => 'SCR/{FY}/', 'padding' => 3]
            ];
            $def = $defaults[$moduleKey] ?? ['prefix' => 'SCR/{FY}/', 'padding' => 3];
            $stmtIns = $pdo->prepare("INSERT INTO `document_sequences` (`module_key`, `financial_year`, `prefix`, `next_value`, `padding`) VALUES (?, ?, ?, ?, ?)");
            $stmtIns->execute([$moduleKey, $fy, $def['prefix'], $nextVal, $def['padding']]);
        }
    }
}

/**
 * Increment sequence next_value.
 */
function incrementSequenceValue($pdo, $moduleKey, $entityId = null) {
    if ($moduleKey === 'invoice') {
        if ($entityId === null) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $entityId = $_SESSION['active_entity_id'] ?? null;
            if (!$entityId) {
                $stmt = $pdo->query("SELECT id FROM entities LIMIT 1");
                $entityId = $stmt->fetchColumn();
            }
        }
        $moduleKey = "invoice_" . $entityId;
    }
    $fy = getFinancialYear();
    $stmt = $pdo->prepare("UPDATE `document_sequences` SET `next_value` = `next_value` + 1 WHERE `module_key` = ? AND `financial_year` = ?");
    $stmt->execute([$moduleKey, $fy]);
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
                <a href='" . BASE_URL . "dashboard.php' style='color: var(--primary);'>Dashboard par wapas jayein</a>
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
                    <a href='" . BASE_URL . "dashboard.php' style='display: inline-block; padding: 12px 24px; background: #1cada9; color: white; text-decoration: none; border-radius: 8px; font-weight: 700; font-family: Outfit, sans-serif; transition: background 0.2s;'>Dashboard par wapas jayein</a>
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
    
    // Check if user is active in DB
    global $pdo;
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $status = $stmt->fetchColumn();
    
    if ($status === 'inactive') {
        // Destroy session
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        
        // Redirect to login page
        header("Location: login.php?error=inactive");
        exit;
    }
}

function sendSystemEmail($to, $subject, $message) {
    $smtp_host = defined('SMTP_HOST') ? SMTP_HOST : '194.238.17.209';
    $smtp_port = defined('SMTP_PORT') ? SMTP_PORT : 25;
    $smtp_user = defined('SMTP_USER') ? SMTP_USER : 'info@sudhacreative.com';
    $smtp_pass = defined('SMTP_PASS') ? SMTP_PASS : 'M2Noida@278';

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $connection = @stream_socket_client("tcp://$smtp_host:$smtp_port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$connection) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }

    $response = fgets($connection, 515);

    fwrite($connection, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
    $response = fgets($connection, 515);
    while (substr($response, 3, 1) == "-") { $response = fgets($connection, 515); }

    fwrite($connection, "STARTTLS\r\n");
    $response = fgets($connection, 515);

    if (strpos($response, '220') !== false) {
        $crypto_res = @stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($crypto_res) {
            fwrite($connection, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
            $response = fgets($connection, 515);
            while (substr($response, 3, 1) == "-") { $response = fgets($connection, 515); }
        }
    }

    fwrite($connection, "AUTH LOGIN\r\n");
    $response = fgets($connection, 515);

    fwrite($connection, base64_encode($smtp_user) . "\r\n");
    $response = fgets($connection, 515);

    fwrite($connection, base64_encode($smtp_pass) . "\r\n");
    $response = fgets($connection, 515);

    if (strpos($response, '235') === false) {
        error_log("SMTP Auth Failed: " . $response);
        fclose($connection);
        return false;
    }

    fwrite($connection, "MAIL FROM: <$smtp_user>\r\n");
    $response = fgets($connection, 515);

    fwrite($connection, "RCPT TO: <$to>\r\n");
    $response = fgets($connection, 515);

    fwrite($connection, "DATA\r\n");
    $response = fgets($connection, 515);

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "From: <$smtp_user>\r\n";
    $headers .= "Subject: $subject\r\n";

    fwrite($connection, "$headers\r\n$message\r\n.\r\n");
    $response = fgets($connection, 515);

    fwrite($connection, "QUIT\r\n");
    fclose($connection);
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
        // Remove only set_entity parameter and redirect
        $queryParams = $_GET;
        unset($queryParams['set_entity']);
        $queryString = http_build_query($queryParams);
        $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
        if (!empty($queryString)) {
            $clean_url .= '?' . $queryString;
        }
        header("Location: " . $clean_url);
        exit;
    }

    // Handle switching financial year
    if (isset($_GET['set_fy'])) {
        $_SESSION['active_financial_year'] = $_GET['set_fy'];
        // Remove only set_fy parameter and redirect
        $queryParams = $_GET;
        unset($queryParams['set_fy']);
        $queryString = http_build_query($queryParams);
        $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
        if (!empty($queryString)) {
            $clean_url .= '?' . $queryString;
        }
        header("Location: " . $clean_url);
        exit;
    }

    if (!isset($_SESSION['active_financial_year'])) {
        // Default to the current system financial year
        $timestamp = time();
        $month = intval(date('m', $timestamp));
        $year = intval(date('y', $timestamp));
        if ($month >= 4) {
            $startYear = $year;
            $endYear = $year + 1;
        } else {
            $startYear = $year - 1;
            $endYear = $year;
        }
        $_SESSION['active_financial_year'] = sprintf("%02d-%02d", $startYear, $endYear);
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
        'terms_conditions'  => getSetting('po_terms', ''),
        'invoice_terms'     => getSetting('invoice_terms', ''),
        'msme_number'       => getSetting('company_msme_number', ''),
        'cin'               => getSetting('company_cin',          ''),
        'tan'               => getSetting('company_tan',          ''),
    ];

    $eid = $entity_id ?: ($_SESSION['active_entity_id'] ?? null);
    if ($eid) {
        $stmt = $pdo->prepare("SELECT * FROM entities WHERE id = ?");
        $stmt->execute([$eid]);
        $entity = $stmt->fetch();
        if ($entity) {
            $data['name']             = $entity['name'];
            $data['gstin']            = !empty($entity['gstin']) ? $entity['gstin'] : '';
            $data['pan']              = !empty($entity['pan']) ? $entity['pan'] : '';
            $data['address']          = !empty($entity['address']) ? $entity['address'] : '';
            $data['logo']             = !empty($entity['logo']) ? $entity['logo'] : '';
            $data['letterhead']       = !empty($entity['letterhead']) ? $entity['letterhead'] : '';
            $data['signature']        = !empty($entity['signature']) ? $entity['signature'] : '';
            $data['bank_details']     = !empty($entity['bank_details']) ? $entity['bank_details'] : '';
            $data['terms_conditions'] = !empty($entity['terms_conditions']) ? $entity['terms_conditions'] : '';
            $data['invoice_terms']    = !empty($entity['invoice_terms']) ? $entity['invoice_terms'] : '';
            $data['msme_number']      = !empty($entity['msme_number']) ? $entity['msme_number'] : '';
            $data['cin']              = (isset($entity['cin']) && !empty($entity['cin'])) ? $entity['cin'] : '';
            $data['tan']              = (isset($entity['tan']) && !empty($entity['tan'])) ? $entity['tan'] : '';

            // Fallback for letterhead to logo if letterhead is empty
            if (empty($data['letterhead']) && !empty($data['logo'])) {
                $data['letterhead'] = $data['logo'];
            }
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

/**
 * Recalculate and update the payment status of invoices or purchase orders.
 */
function updateDocumentPaymentStatus($pdo, $invoiceId, $poId) {
    if ($invoiceId) {
        $paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE invoice_id = ? AND approval_status = 'approved'");
        $paidStmt->execute([$invoiceId]);
        $totalPaid = floatval($paidStmt->fetchColumn());

        $invStmt = $pdo->prepare("SELECT total_amount FROM invoices WHERE id = ?");
        $invStmt->execute([$invoiceId]);
        $invTotal = floatval($invStmt->fetchColumn());

        $status = ($totalPaid >= $invTotal) ? 'paid' : (($totalPaid > 0) ? 'partially_paid' : 'unpaid');
        $upd = $pdo->prepare("UPDATE invoices SET payment_status = ? WHERE id = ?");
        $upd->execute([$status, $invoiceId]);
    }

    if ($poId) {
        $paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE proposal_id = ? AND approval_status = 'approved'");
        $paidStmt->execute([$poId]);
        $totalPaid = floatval($paidStmt->fetchColumn());

        $poStmt = $pdo->prepare("SELECT total_amount, approval_status FROM purchase_orders WHERE id = ?");
        $poStmt->execute([$poId]);
        $po = $poStmt->fetch();
        if ($po) {
            $poTotal = floatval($po['total_amount']);
            if ($po['approval_status'] === 'approved') {
                $status = ($totalPaid >= $poTotal) ? 'paid' : 'approved';
            } else {
                $status = ($po['approval_status'] === 'pending_approval') ? 'pending' : 'cancelled';
            }
            $upd = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
            $upd->execute([$status, $poId]);
        }
    }
}
?>

<?php
// Global Helper Functions

/**
 * Sanitize input data
 */
function clean($data) {
    if (is_array($data)) return array_map('clean', $data);
    return htmlspecialchars(strip_tags(trim((string)$data)));
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

        if (in_array($entityType, ['invoice', 'purchase_order', 'payment'])) {
            if (strpos($_SERVER['HTTP_HOST'] ?? '', 'sudhacreative.tech') !== false) {
                include_once __DIR__ . '/accounting_poster.php';
                deleteJournalEntry($pdo, $entityType, $entityId);
            }
        }
    }
}

/**
 * Automatically syncs and updates booking totals and any linked Tax Invoices with latest booking items.
 * Can be called whenever selling cost, period, units, print/mount rates, or items are updated.
 *
 * @param PDO $pdo
 * @param int $bookingId
 * @return array|false
 */
function syncBookingInvoiceFinancials($pdo, $bookingId) {
    $bookingId = intval($bookingId);
    if ($bookingId <= 0) return false;

    // 1. Calculate sum totals from booking_items joined with sites
    $stmtSums = $pdo->prepare("
        SELECT 
            COALESCE(SUM(bi.amount), 0) as rental_total,
            COALESCE(SUM(bi.client_print_rate * s.width * s.height * COALESCE(bi.unit, 1)), 0) as print_total,
            COALESCE(SUM(bi.client_mount_rate * s.width * s.height * COALESCE(bi.unit, 1)), 0) as mount_total
        FROM booking_items bi
        JOIN sites s ON bi.site_id = s.id
        WHERE bi.booking_id = ?
    ");
    $stmtSums->execute([$bookingId]);
    $sums = $stmtSums->fetch(PDO::FETCH_ASSOC);

    $rentalTotal = floatval($sums['rental_total'] ?? 0);
    $printTotal  = floatval($sums['print_total'] ?? 0);
    $mountTotal  = floatval($sums['mount_total'] ?? 0);
    $newSubtotal = $rentalTotal + $printTotal + $mountTotal;

    // 2. Fetch booking metadata (tax_type, client_state, etc.)
    $stmtBooking = $pdo->prepare("
        SELECT b.id, b.booking_number, b.client_id, b.tax_type, c.state as client_state 
        FROM bookings b 
        LEFT JOIN partners c ON b.client_id = c.id 
        WHERE b.id = ?
    ");
    $stmtBooking->execute([$bookingId]);
    $booking = $stmtBooking->fetch(PDO::FETCH_ASSOC);
    if (!$booking) return false;

    $tax_type = $booking['tax_type'] ?: 'igst';
    $isInterState = (strtolower(trim($booking['client_state'] ?? '')) !== 'west bengal');

    $taxAmount = 0;
    $cgst = 0;
    $sgst = 0;
    $igst = 0;

    if ($tax_type !== 'none') {
        if ($tax_type === 'cgst_sgst' || (!$isInterState && $tax_type !== 'igst')) {
            $taxAmount = $newSubtotal * 0.18;
            $cgst = $taxAmount / 2;
            $sgst = $taxAmount / 2;
            $igst = 0;
        } else {
            $taxAmount = $newSubtotal * 0.18;
            $cgst = 0;
            $sgst = 0;
            $igst = $taxAmount;
        }
    }
    $grandTotal = $newSubtotal + $taxAmount;

    // 3. Update bookings table
    $stmtUpdBooking = $pdo->prepare("
        UPDATE bookings 
        SET total_amount = ?, tax_amount = ?, grand_total = ? 
        WHERE id = ?
    ");
    $stmtUpdBooking->execute([$newSubtotal, $taxAmount, $grandTotal, $bookingId]);

    // 4. Update overall booking start/end dates if available
    $stmtDates = $pdo->prepare("SELECT MIN(start_date) as min_start, MAX(end_date) as max_end FROM booking_items WHERE booking_id = ?");
    $stmtDates->execute([$bookingId]);
    $dates = $stmtDates->fetch(PDO::FETCH_ASSOC);
    if ($dates && !empty($dates['min_start']) && !empty($dates['max_end'])) {
        $stmtUpdateDates = $pdo->prepare("UPDATE bookings SET start_date = ?, end_date = ? WHERE id = ?");
        $stmtUpdateDates->execute([$dates['min_start'], $dates['max_end'], $bookingId]);
    }

    // 5. Check if Tax Invoice exists for this booking and update it
    $stmtInv = $pdo->prepare("SELECT id, invoice_number, total_amount FROM invoices WHERE booking_id = ? AND type = 'tax'");
    $stmtInv->execute([$bookingId]);
    $existingInvoices = $stmtInv->fetchAll(PDO::FETCH_ASSOC);

    $invoiceUpdated = false;
    foreach ($existingInvoices as $inv) {
        $stmtUpdInv = $pdo->prepare("
            UPDATE invoices 
            SET sub_total = ?, cgst = ?, sgst = ?, igst = ?, total_amount = ? 
            WHERE id = ?
        ");
        $stmtUpdInv->execute([$newSubtotal, $cgst, $sgst, $igst, $grandTotal, $inv['id']]);
        $invoiceUpdated = true;

        if (function_exists('logActivity')) {
            logActivity('sync_invoice_with_booking', 'invoices', $inv['id'], "Invoice #{$inv['invoice_number']} synced with updated Booking #{$booking['booking_number']} (New Total: ₹" . number_format($grandTotal, 2) . ")");
        }
    }

    return [
        'rental_total'    => $rentalTotal,
        'print_total'     => $printTotal,
        'mount_total'     => $mountTotal,
        'subtotal'        => $newSubtotal,
        'tax_amount'      => $taxAmount,
        'grand_total'     => $grandTotal,
        'invoice_updated' => $invoiceUpdated
    ];
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
        header("Location: " . BASE_URL . "login.php");
        exit;
    }

    // Check if user is active in DB (a deleted user is treated as inactive)
    global $pdo;
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $status = $stmt->fetchColumn();
    
    if ($status === 'inactive' || $status === false) {
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
        header("Location: " . BASE_URL . "login.php?error=inactive");
        exit;
    }
}

function sendSystemEmail($to, $subject, $message, $attachments = []) {
    $smtp_host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
    $smtp_port = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $smtp_user = defined('SMTP_USER') ? SMTP_USER : 'sudhacreativemalda@gmail.com';
    $smtp_pass = defined('SMTP_PASS') ? SMTP_PASS : 'vzvz nlrg oube vwsd';

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

    $boundary = md5(uniqid(time()));
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "From: \"Sudha Creative\" <$smtp_user>\r\n";
    $headers .= "Reply-To: <$smtp_user>\r\n";
    $headers .= "Subject: $subject\r\n";
    
    if (empty($attachments)) {
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $body = $message;
    } else {
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        
        // HTML Body Part
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=utf-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $message . "\r\n\r\n";
        
        // Attachments Part
        foreach ($attachments as $att) {
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: " . ($att['type'] ?? 'application/octet-stream') . "; name=\"" . $att['name'] . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"" . $att['name'] . "\"\r\n\r\n";
            
            // Extract raw base64 data if it contains data URL prefix
            $content = $att['content'];
            if (strpos($content, ';base64,') !== false) {
                $content = explode(';base64,', $content)[1];
            }
            
            // Format to 76 characters line length
            $body .= chunk_split($content) . "\r\n\r\n";
        }
        $body .= "--$boundary--";
    }

    $payload = "$headers\r\n$body\r\n.\r\n";
    $totalLen = strlen($payload);
    $written = 0;
    while ($written < $totalLen) {
        $n = @fwrite($connection, substr($payload, $written, 65536));
        if ($n === false || $n <= 0) {
            break;
        }
        $written += $n;
    }

    $response = fgets($connection, 515);

    fwrite($connection, "QUIT\r\n");
    fclose($connection);
    return strpos($response, '250') !== false;
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
    // Work in whole paise so e.g. 99.996 becomes 100 rupees, not 99 rupees + "100" paise
    $totalPaise = (int)round(abs((float)$number) * 100);
    $rupees = intdiv($totalPaise, 100);
    $paise = $totalPaise % 100;

    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    $twoDigits = function ($n) use ($ones, $tens) {
        if ($n < 20) return $ones[$n];
        return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
    };

    // Indian grouping: crore, lakh, thousand, hundred, remainder
    $toWords = function ($n) use (&$toWords, $twoDigits) {
        $parts = [];
        if ($n >= 10000000) { $parts[] = $toWords(intdiv($n, 10000000)) . ' crore'; $n %= 10000000; }
        if ($n >= 100000)   { $parts[] = $twoDigits(intdiv($n, 100000)) . ' lakh'; $n %= 100000; }
        if ($n >= 1000)     { $parts[] = $twoDigits(intdiv($n, 1000)) . ' thousand'; $n %= 1000; }
        if ($n >= 100)      { $parts[] = $twoDigits(intdiv($n, 100)) . ' hundred'; $n %= 100; }
        if ($n > 0)         { $parts[] = ($parts ? 'and ' : '') . $twoDigits($n); }
        return implode(' ', $parts);
    };

    $result = ucwords($rupees > 0 ? $toWords($rupees) : 'zero') . ' Rupees';
    if ($paise > 0) {
        $result .= ' And ' . ucwords($twoDigits($paise)) . ' Paise';
    }
    return $result . ' Only';
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

/**
 * Check if a record creator's account is inactive.
 */
function isCreatorInactive($pdo, $creatorId) {
    if (!$creatorId) return false;
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$creatorId]);
    $status = $stmt->fetchColumn();
    return ($status === 'inactive');
}

/**
 * Check if the current logged-in user is allowed to modify a record.
 * Regular users cannot edit records created by an inactive user, only Admins can.
 */
function canModifyRecord($pdo, $creatorId) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $role = $_SESSION['user_role'] ?? '';
    
    // If the creator is not inactive, anyone with standard permissions can edit
    if (!isCreatorInactive($pdo, $creatorId)) {
        return true;
    }
    
    // If the creator IS inactive, only admins can modify/takeover the record
    return ($role === 'admin');
}

/**
 * Recalculate and update the totals, tax, and grand totals of a proposal in database.
 */
function recalculateProposalTotals($pdo, $proposalId) {
    // 1. Get sum of amount and total_sqft from items
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(pi.amount), 0) as total_display,
               COALESCE(SUM(s.width * s.height * COALESCE(pi.unit, 1)), 0) as total_sqft
        FROM proposal_items pi
        JOIN sites s ON pi.site_id = s.id
        WHERE pi.proposal_id = ?
    ");
    $stmt->execute([$proposalId]);
    $itemStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalDisplay = floatval($itemStats['total_display']);
    $totalSqft = floatval($itemStats['total_sqft']);

    // 2. Fetch proposal settings (discounting_pct, printing_cost, mounting_cost, tax_type)
    $stmtProp = $pdo->prepare("
        SELECT discounting_pct, printing_cost, mounting_cost, tax_type 
        FROM proposals 
        WHERE id = ?
    ");
    $stmtProp->execute([$proposalId]);
    $p = $stmtProp->fetch(PDO::FETCH_ASSOC);
    if (!$p) return false;

    $discountPct = floatval($p['discounting_pct']);
    $printRate = floatval($p['printing_cost']); // printing_cost stores the per-sqft rate
    $mountRate = floatval($p['mounting_cost']); // mounting_cost stores the per-sqft rate
    $taxType = $p['tax_type'] ?? 'igst';

    // 3. Compute Display Cost (after discount)
    $discountVal = $totalDisplay * ($discountPct / 100);
    $displayCost = $totalDisplay - $discountVal;

    // 4. Compute Printing Cost = total_sqft * printing_rate
    $totalPrintCost = $totalSqft * $printRate;

    // 5. Compute Installation (Mounting) Cost = total_sqft * mounting_rate
    $totalMountCost = $totalSqft * $mountRate;

    // 6. Compute Subtotal = Display Cost + Printing Cost + Installation Cost
    $subtotal = $displayCost + $totalPrintCost + $totalMountCost;

    // 6. Compute Tax (GST) based on tax_type
    $tax = ($taxType === 'no_tax') ? 0.00 : ($subtotal * 0.18);
    $grandTotal = $subtotal + $tax;

    // 7. Calculate start_date, end_date, total_days
    $stmtMinMax = $pdo->prepare("
        SELECT MIN(start_date) as min_start, MAX(end_date) as max_end 
        FROM proposal_items 
        WHERE proposal_id = ?
    ");
    $stmtMinMax->execute([$proposalId]);
    $minMax = $stmtMinMax->fetch(PDO::FETCH_ASSOC);
    $newStart = $minMax['min_start'] ?: null;
    $newEnd = $minMax['max_end'] ?: null;
    $newDays = null;
    if (!empty($newStart) && !empty($newEnd)) {
        $newDays = max(1, ceil((strtotime($newEnd) - strtotime($newStart)) / 86400) + 1);
    }

    // 8. Update proposals table
    $stmtUpdate = $pdo->prepare("
        UPDATE proposals 
        SET total_amount = ?, 
            total_sqft = ?, 
            display_cost = ?,
            tax_amount = ?, 
            grand_total = ?, 
            start_date = ?, 
            end_date = ?, 
            total_days = ? 
        WHERE id = ?
    ");
    $stmtUpdate->execute([
        $totalDisplay,
        $totalSqft,
        $displayCost,
        $tax,
        $grandTotal,
        $newStart,
        $newEnd,
        $newDays,
        $proposalId
    ]);

    return true;
}

/**
 * Parse any attachment string (raw path, JSON-encoded array, comma-separated) into clean relative web paths.
 * 
 * @param mixed $rawValue
 * @return array List of clean relative URLs (e.g. ['uploads/customer_pos/PO_150_1789214085.pdf'])
 */
function getAttachmentUrls($rawValue) {
    if (empty($rawValue)) {
        return [];
    }

    $files = [];

    if (is_array($rawValue)) {
        $rawList = $rawValue;
    } else {
        $str = trim((string)$rawValue);
        if ($str === '' || $str === '[]' || $str === 'null' || $str === '""') {
            return [];
        }

        if (substr($str, 0, 1) === '[' || substr($str, 0, 1) === '{' || substr($str, 0, 1) === '"') {
            $decoded = json_decode($str, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
                $rawList = is_array($decoded) ? $decoded : [$decoded];
            } else {
                $cleaned = trim($str, "[] \t\n\r\0\x0B");
                $parts = explode(',', $cleaned);
                $rawList = array_map(function($p) {
                    return trim(trim($p), '"\'');
                }, $parts);
            }
        } else {
            $rawList = strpos($str, ',') !== false ? explode(',', $str) : [$str];
        }
    }

    foreach ($rawList as $file) {
        $file = trim((string)$file);
        $file = trim($file, '"\'[]');
        $file = stripslashes($file);
        $file = preg_replace('#/+#', '/', $file);
        $file = ltrim($file, '/');

        if (!empty($file)) {
            $files[] = $file;
        }
    }

    return array_values(array_unique(array_filter($files)));
}

/**
 * Get the first attachment URL from any raw value, formatted with BASE_URL.
 * 
 * @param mixed $rawValue
 * @return string Full URL or empty string
 */
function getFirstAttachmentUrl($rawValue) {
    $urls = getAttachmentUrls($rawValue);
    if (empty($urls)) {
        return '';
    }
    $first = $urls[0];
    if (strpos($first, 'http://') === 0 || strpos($first, 'https://') === 0) {
        return $first;
    }
    return BASE_URL . $first;
}
?>

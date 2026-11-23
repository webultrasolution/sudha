<?php
/**
 * Flex Mounting Register cum Contractor Payment — business logic.
 *
 * One trip = one labour contractor visit on a date, covering one or more sites.
 *   labour per site   = site sqft x trip rate_per_sqft
 *   trip overheads    = food + travel + misc, split across the trip's sites in proportion to sqft
 *                       (equal split if every line has 0 sqft)
 *   site total cost   = labour + overhead share   -> counted in that site's P&L once the trip is approved
 * Rounding: every share is rounded to paise and the last site absorbs the remainder,
 * so SUM(site total_cost) == trip total_amount exactly.
 *
 * Tables: mounting_trips, mounting_trip_sites (see config/flex_mounting_migration.sql).
 * Permission module key: 'flex_mounting'. Approval entity_type: 'mounting_trip'.
 */

const MOUNTING_MODULE_KEY = 'flex_mounting';
const MOUNTING_PAYMENT_MODES = ['Cash', 'UPI', 'NEFT', 'IMPS', 'RTGS', 'Cheque', 'Other'];

function mountingMoney($v) {
    return round(max(0, floatval($v)), 2);
}

/**
 * Pure calculation. $sites = [['site_id'=>int, 'sqft'=>float, 'remarks'=>?string], ...]
 * Returns ['lines'=>[...with labour_amount, overhead_share, total_cost], 'total_sqft', 'labour_amount',
 *          'overhead', 'total_amount'].
 */
function calculateMountingTrip(array $sites, $ratePerSqft, $food, $travel, $misc) {
    $rate = mountingMoney($ratePerSqft);
    $overhead = round(mountingMoney($food) + mountingMoney($travel) + mountingMoney($misc), 2);

    $lines = [];
    $totalSqft = 0.0;
    foreach ($sites as $s) {
        $sqft = round(max(0, floatval($s['sqft'] ?? 0)), 2);
        $totalSqft += $sqft;
        $lines[] = [
            'site_id' => intval($s['site_id'] ?? 0),
            'sqft' => $sqft,
            'remarks' => isset($s['remarks']) ? trim((string)$s['remarks']) : null,
            'labour_amount' => round($sqft * $rate, 2),
        ];
    }
    $totalSqft = round($totalSqft, 2);
    $n = count($lines);

    $allocated = 0.0;
    foreach ($lines as $i => &$line) {
        if ($i === $n - 1) {
            $share = round($overhead - $allocated, 2);
        } elseif ($totalSqft > 0) {
            $share = round($overhead * $line['sqft'] / $totalSqft, 2);
        } else {
            $share = round($overhead / $n, 2);
        }
        $allocated += $share;
        $line['overhead_share'] = $share;
        $line['total_cost'] = round($line['labour_amount'] + $share, 2);
    }
    unset($line);

    $labour = round(array_sum(array_column($lines, 'labour_amount')), 2);
    return [
        'lines' => $lines,
        'total_sqft' => $totalSqft,
        'labour_amount' => $labour,
        'overhead' => $overhead,
        'total_amount' => round($labour + $overhead, 2),
    ];
}

/**
 * Validate input and return a clean payload or throw Exception with a user-facing message.
 * When a site line has no sqft, the site's master sqft is used.
 */
function validateMountingTripInput(PDO $pdo, array $in) {
    $date = trim($in['trip_date'] ?? '');
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) throw new Exception('Valid trip date is required.');
    if ($date > date('Y-m-d')) throw new Exception('Trip date cannot be in the future.');

    $name = trim($in['contractor_name'] ?? '');
    if ($name === '' || mb_strlen($name) > 150) throw new Exception('Labour contractor name is required (max 150 chars).');

    // Accept 9876543210, 09876543210, +91 98765 43210, 91-9876543210; store the plain 10 digits
    $mobile = preg_replace('/\D/', '', (string)($in['contractor_mobile'] ?? ''));
    if (strlen($mobile) === 12 && substr($mobile, 0, 2) === '91') $mobile = substr($mobile, 2);
    if (strlen($mobile) === 11 && $mobile[0] === '0') $mobile = substr($mobile, 1);
    if ($mobile !== '' && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) throw new Exception('Enter a valid 10-digit mobile number (e.g. 9876543210).');

    foreach (['rate_per_sqft', 'food_amount', 'travel_amount', 'misc_amount'] as $k) {
        if (isset($in[$k]) && $in[$k] !== '' && (!is_numeric($in[$k]) || floatval($in[$k]) < 0)) {
            throw new Exception("Invalid amount for $k.");
        }
    }

    $brand = trim((string)($in['brand_campaign'] ?? ''));
    if (mb_strlen($brand) > 150) throw new Exception('Brand / campaign is too long (max 150 characters).');

    $mountingDate = trim((string)($in['mounting_date'] ?? ''));
    if ($mountingDate !== '') {
        $md = DateTime::createFromFormat('Y-m-d', $mountingDate);
        if (!$md || $md->format('Y-m-d') !== $mountingDate) throw new Exception('Enter a valid mounting date.');
        if ($mountingDate > date('Y-m-d')) throw new Exception('Mounting date cannot be in the future.');
    }

    $rawSites = $in['sites'] ?? [];
    if (!is_array($rawSites) || !count($rawSites)) throw new Exception('Add at least one site.');

    $ids = [];
    foreach ($rawSites as $s) {
        $sid = intval($s['site_id'] ?? 0);
        if ($sid <= 0) throw new Exception('Every line needs a site from the master.');
        if (isset($ids[$sid])) throw new Exception('The same site is added twice.');
        $ids[$sid] = true;
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, site_code FROM sites WHERE id IN ($place)");
    $stmt->execute(array_keys($ids));
    $master = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    if (count($master) !== count($ids)) throw new Exception('One or more sites were not found in the master.');

    $sites = [];
    foreach ($rawSites as $s) {
        $sid = intval($s['site_id']);
        // Sqft is entered by hand for the work actually done (not taken from the site master)
        $sqft = isset($s['sqft']) && is_numeric($s['sqft']) ? floatval($s['sqft']) : 0;
        if ($sqft <= 0) throw new Exception('Enter the sqft mounted for site ' . $master[$sid]['site_code'] . '.');
        $sites[] = ['site_id' => $sid, 'sqft' => $sqft, 'remarks' => mb_substr(trim((string)($s['remarks'] ?? '')), 0, 255) ?: null];
    }

    return [
        'trip_date' => $date,
        'contractor_name' => $name,
        'contractor_mobile' => $mobile ?: null,
        'rate_per_sqft' => mountingMoney($in['rate_per_sqft'] ?? 0),
        'food_amount' => mountingMoney($in['food_amount'] ?? 0),
        'travel_amount' => mountingMoney($in['travel_amount'] ?? 0),
        'misc_amount' => mountingMoney($in['misc_amount'] ?? 0),
        'mounting_date' => $mountingDate !== '' ? $mountingDate : null,
        'brand_campaign' => $brand !== '' ? $brand : null,
        'description' => trim((string)($in['description'] ?? '')) ?: null,
        'sites' => $sites,
    ];
}

/**
 * Create (tripId = 0) or update a trip. Non-admin saves always go (back) to pending approval;
 * admin saves are approved directly. Approved/paid trips can only be edited by admin; paid trips are locked.
 * Returns ['id'=>int, 'trip_number'=>string, 'approval_status'=>string, 'total_amount'=>float].
 */
function saveMountingTrip(PDO $pdo, array $input, $tripId, $userId, $isAdmin, $entityId = null) {
    $d = validateMountingTripInput($pdo, $input);
    $calc = calculateMountingTrip($d['sites'], $d['rate_per_sqft'], $d['food_amount'], $d['travel_amount'], $d['misc_amount']);
    $status = $isAdmin ? 'approved' : 'pending_approval';

    $pdo->beginTransaction();
    try {
        $contractorId = mountingFindOrCreateContractor($pdo, $d['contractor_name'], $d['contractor_mobile'], $userId);
        if ($tripId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM mounting_trips WHERE id = ? FOR UPDATE");
            $stmt->execute([$tripId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new Exception('Trip not found.');
            if (mountingTripHasPayments($pdo, $tripId)) throw new Exception('This entry has payments. Revert or delete its payments before editing.');
            $tripNumber = $old['trip_number'];

            $pdo->prepare("UPDATE mounting_trips SET trip_date=?, contractor_id=?, contractor_name=?, contractor_mobile=?, brand_campaign=?, rate_per_sqft=?,
                    total_sqft=?, labour_amount=?, food_amount=?, travel_amount=?, misc_amount=?, mounting_date=?, total_amount=?, description=?,
                    approval_status=?, approved_by=?, approved_at=?, rejection_reason=NULL WHERE id=?")
                ->execute([$d['trip_date'], $contractorId, $d['contractor_name'], $d['contractor_mobile'], $d['brand_campaign'], $d['rate_per_sqft'],
                    $calc['total_sqft'], $calc['labour_amount'], $d['food_amount'], $d['travel_amount'], $d['misc_amount'], $d['mounting_date'],
                    $calc['total_amount'], $d['description'], $status, $isAdmin ? $userId : null, $isAdmin ? date('Y-m-d H:i:s') : null, $tripId]);
            $pdo->prepare("DELETE FROM mounting_trip_sites WHERE trip_id = ?")->execute([$tripId]);
        } else {
            $tripNumber = generateSequenceNumber($pdo, MOUNTING_MODULE_KEY, $d['trip_date']);
            $pdo->prepare("INSERT INTO mounting_trips (entity_id, trip_number, trip_date, contractor_id, contractor_name, contractor_mobile, brand_campaign, rate_per_sqft,
                    total_sqft, labour_amount, food_amount, travel_amount, misc_amount, mounting_date, total_amount, description,
                    approval_status, approved_by, approved_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$entityId, $tripNumber, $d['trip_date'], $contractorId, $d['contractor_name'], $d['contractor_mobile'], $d['brand_campaign'], $d['rate_per_sqft'],
                    $calc['total_sqft'], $calc['labour_amount'], $d['food_amount'], $d['travel_amount'], $d['misc_amount'], $d['mounting_date'],
                    $calc['total_amount'], $d['description'], $status, $isAdmin ? $userId : null, $isAdmin ? date('Y-m-d H:i:s') : null, $userId]);
            $tripId = intval($pdo->lastInsertId());
        }

        $ins = $pdo->prepare("INSERT INTO mounting_trip_sites (trip_id, site_id, sqft, labour_amount, overhead_share, total_cost, remarks) VALUES (?,?,?,?,?,?,?)");
        foreach ($calc['lines'] as $l) {
            $ins->execute([$tripId, $l['site_id'], $l['sqft'], $l['labour_amount'], $l['overhead_share'], $l['total_cost'], $l['remarks']]);
        }

        // Keep exactly one approval request per trip in the central queue
        $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'mounting_trip' AND entity_id = ? AND status = 'pending'")->execute([$tripId]);
        if (!$isAdmin) {
            $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('mounting_trip', ?, ?, ?, 'pending')")
                ->execute([$tripId, "Flex Mounting $tripNumber (" . $d['contractor_name'] . ")", $userId]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['id' => $tripId, 'trip_number' => $tripNumber, 'approval_status' => $status, 'total_amount' => $calc['total_amount']];
}

/** Called from ajax/approve_entity.php inside its transaction. */
function setMountingTripApproval(PDO $pdo, $tripId, $approve, $adminId, $reason = null) {
    $stmt = $pdo->prepare("UPDATE mounting_trips SET approval_status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ?
                           WHERE id = ?");
    if (!$approve && mountingTripHasPayments($pdo, $tripId)) throw new Exception('This entry has payments. Revert them before rejecting.');
    $stmt->execute([$approve ? 'approved' : 'rejected', $adminId, $approve ? null : ($reason ?: null), $tripId]);
    if (!$stmt->rowCount()) throw new Exception('Trip not found.');
    $ref = $pdo->prepare("SELECT trip_number FROM mounting_trips WHERE id = ?");
    $ref->execute([$tripId]);
    return $ref->fetchColumn();
}

/* ------------------------------------------------------------------------------------------------
 * Contractor payments (table mounting_payments, kind 'payment')
 *   Payments are made only against an approved mounting entry: full or partial, never more than its balance.
 *   No advance payments (business rule); old 'advance'/'adjustment' rows, if any, are ignored everywhere.
 * An entry's paid amount = its active payments; status pending / partial / paid.
 * Revert keeps the row for audit (status 'reversed'); delete removes a wrong entry (admin).
 * ---------------------------------------------------------------------------------------------- */

function mountingTripHasPayments(PDO $pdo, $tripId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mounting_payments WHERE trip_id = ? AND kind = 'payment' AND status = 'active' AND approval_status <> 'rejected'");
    $stmt->execute([$tripId]);
    return $stmt->fetchColumn() > 0;
}

function mountingValidDate($d, $label = 'Payment date') {
    $dt = DateTime::createFromFormat('Y-m-d', (string)$d);
    if (!$dt || $dt->format('Y-m-d') !== $d) throw new Exception("Valid $label is required.");
    if ($d > date('Y-m-d')) throw new Exception("$label cannot be in the future.");
    return $d;
}

function mountingValidAmount($v) {
    if (!is_numeric($v) || round(floatval($v), 2) <= 0) throw new Exception('Enter an amount greater than 0.');
    return round(floatval($v), 2);
}

function mountingRupees($v) {
    return "\u{20B9}" . number_format($v, 2);
}

/** Recalculate an entry's paid amount and payment status from its active payments + adjustments. */
function recomputeMountingTripPayment(PDO $pdo, $tripId) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN approval_status = 'approved' THEN amount ELSE 0 END), 0),
                                  MAX(CASE WHEN approval_status = 'approved' THEN paid_date END),
                                  COALESCE(SUM(CASE WHEN approval_status = 'pending_approval' THEN amount ELSE 0 END), 0)
                           FROM mounting_payments WHERE trip_id = ? AND kind = 'payment' AND status = 'active'");
    $stmt->execute([$tripId]);
    [$paid, $last, $pendingPay] = $stmt->fetch(PDO::FETCH_NUM);
    $paid = round(floatval($paid), 2);
    $pendingPay = round(floatval($pendingPay), 2);
    $t = $pdo->prepare("SELECT total_amount FROM mounting_trips WHERE id = ?");
    $t->execute([$tripId]);
    $total = round(floatval($t->fetchColumn()), 2);
    $status = $paid <= 0.009 ? 'pending' : ($paid + 0.009 >= $total ? 'paid' : 'partial');
    $pdo->prepare("UPDATE mounting_trips SET paid_amount = ?, payment_status = ?, paid_date = ? WHERE id = ?")
        ->execute([$paid, $status, $paid > 0 ? $last : null, $tripId]);
    // balance = what can still be paid (approved and awaiting-approval payments both count against it)
    return ['paid' => $paid, 'pending' => $pendingPay, 'total' => $total, 'balance' => round($total - $paid - $pendingPay, 2), 'status' => $status];
}

function mountingLockTrip(PDO $pdo, $tripId) {
    $stmt = $pdo->prepare("SELECT * FROM mounting_trips WHERE id = ? FOR UPDATE");
    $stmt->execute([$tripId]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) throw new Exception('Entry not found.');
    if ($t['approval_status'] !== 'approved') throw new Exception('Only approved entries can be paid.');
    return $t;
}

/** Cash payment against an entry (full or partial). */
function addMountingTripPayment(PDO $pdo, $tripId, $amount, $date, $mode, $ref, $notes, $userId, $attachment = null, $isAdmin = true) {
    $amount = mountingValidAmount($amount);
    mountingValidDate($date);
    if (!in_array($mode, MOUNTING_PAYMENT_MODES, true)) throw new Exception('Select a payment mode.');
    $pdo->beginTransaction();
    try {
        $t = mountingLockTrip($pdo, $tripId);
        $state = recomputeMountingTripPayment($pdo, $tripId);
        if ($amount > $state['balance'] + 0.009) throw new Exception('Amount is more than the balance of ' . mountingRupees($state['balance'])  . '.');
        $number = generateSequenceNumber($pdo, 'flex_mounting_payment', $date);
        $pdo->prepare("INSERT INTO mounting_payments (payment_number, entity_id, kind, trip_id, contractor_name, contractor_mobile, amount, paid_date, payment_mode, payment_ref, notes, attachment,
                                                      approval_status, approved_by, approved_at, created_by)
                       VALUES (?, ?, 'payment', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$number, $t['entity_id'], $tripId, $t['contractor_name'], $t['contractor_mobile'], $amount, $date, $mode,
                       mb_substr(trim((string)$ref), 0, 100) ?: null, mb_substr(trim((string)$notes), 0, 255) ?: null, $attachment ?: null,
                       $isAdmin ? 'approved' : 'pending_approval', $isAdmin ? $userId : null, $isAdmin ? date('Y-m-d H:i:s') : null, $userId]);
        if (!$isAdmin) mountingQueuePaymentApproval($pdo, intval($pdo->lastInsertId()), $number, $t['contractor_name'], $amount, $userId);
        $state = recomputeMountingTripPayment($pdo, $tripId);
        $pdo->commit();
        return ['payment_number' => $number, 'approval_status' => $isAdmin ? 'approved' : 'pending_approval'] + $state;
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

/**
 * One payment over several ticked entries of the same contractor (e.g. 10 days of work paid on day 11).
 * Up to the entries' total balance; a smaller amount fills the oldest entries first. Every entry gets its own
 * payment row, all sharing one payment number, date, mode, reference, notes and attachment.
 * Returns ['payment_number', 'paid' => total paid now, 'rows' => [trip_number => amount], 'balance' => left on the ticked entries].
 */
function addMountingBulkPayment(PDO $pdo, array $tripIds, $amount, $date, $mode, $ref, $notes, $userId, $attachment = null, $isAdmin = true) {
    $amount = mountingValidAmount($amount);
    mountingValidDate($date);
    if (!in_array($mode, MOUNTING_PAYMENT_MODES, true)) throw new Exception('Select a payment mode.');
    $tripIds = array_values(array_unique(array_filter(array_map('intval', $tripIds))));
    if (!$tripIds) throw new Exception('Tick the entries you are paying for.');
    $pdo->beginTransaction();
    try {
        $place = implode(',', array_fill(0, count($tripIds), '?'));
        $st = $pdo->prepare("SELECT * FROM mounting_trips WHERE id IN ($place) ORDER BY trip_date, id FOR UPDATE");
        $st->execute($tripIds);
        $trips = $st->fetchAll(PDO::FETCH_ASSOC);
        if (count($trips) !== count($tripIds)) throw new Exception('Some ticked entries were not found.');
        $contractor = mb_strtolower(trim($trips[0]['contractor_name']));
        $total = 0.0;
        foreach ($trips as &$t) {
            if ($t['approval_status'] !== 'approved') throw new Exception($t['trip_number'] . ' is not approved yet.');
            if (mb_strtolower(trim($t['contractor_name'])) !== $contractor) throw new Exception('Tick entries of one contractor only.');
            $t['balance'] = recomputeMountingTripPayment($pdo, $t['id'])['balance'];
            $total += max(0, $t['balance']);
        }
        unset($t);
        $total = round($total, 2);
        if ($total <= 0.009) throw new Exception('The ticked entries are already paid.');
        if ($amount > $total + 0.009) throw new Exception('Amount is more than the balance of the ticked entries (' . mountingRupees($total) . ').');

        $number = generateSequenceNumber($pdo, 'flex_mounting_payment', $date);
        $ins = $pdo->prepare("INSERT INTO mounting_payments (payment_number, entity_id, kind, trip_id, contractor_name, contractor_mobile, amount, paid_date, payment_mode, payment_ref, notes, attachment,
                                                             approval_status, approved_by, approved_at, created_by)
                              VALUES (?, ?, 'payment', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $firstId = 0;
        $left = $amount;
        $rows = [];
        foreach ($trips as $t) {
            if ($left <= 0.009) break;
            if ($t['balance'] <= 0.009) continue;
            $part = round(min($t['balance'], $left), 2);
            $ins->execute([$number, $t['entity_id'], $t['id'], $t['contractor_name'], $t['contractor_mobile'], $part, $date, $mode,
                           mb_substr(trim((string)$ref), 0, 100) ?: null, mb_substr(trim((string)$notes), 0, 255) ?: null, $attachment ?: null,
                           $isAdmin ? 'approved' : 'pending_approval', $isAdmin ? $userId : null, $isAdmin ? date('Y-m-d H:i:s') : null, $userId]);
            if (!$firstId) $firstId = intval($pdo->lastInsertId());
            recomputeMountingTripPayment($pdo, $t['id']);
            $rows[$t['trip_number']] = $part;
            $left = round($left - $part, 2);
        }
        if (!$isAdmin && $firstId) mountingQueuePaymentApproval($pdo, $firstId, $number, $trips[0]['contractor_name'], $amount, $userId);
        $pdo->commit();
        return ['payment_number' => $number, 'paid' => $amount, 'rows' => $rows, 'balance' => round($total - $amount, 2),
                'approval_status' => $isAdmin ? 'approved' : 'pending_approval'];
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

/** Put a staff payment in the central approval queue (one request per payment number; entity_id = its first row). */
function mountingQueuePaymentApproval(PDO $pdo, $firstRowId, $number, $contractor, $amount, $userId) {
    $pdo->prepare("INSERT INTO approval_requests (entity_type, entity_id, entity_ref, requested_by, status) VALUES ('mounting_payment', ?, ?, ?, 'pending')")
        ->execute([$firstRowId, mb_substr("Mounting payment $number (" . $contractor . ', Rs. ' . number_format($amount, 2) . ')', 0, 100), $userId]);
}

/**
 * Admin approves or rejects a staff payment: every entry row of that payment number together.
 * $paymentId may be any row of the payment. Runs inside the caller's transaction. Returns the payment number.
 */
function setMountingPaymentApproval(PDO $pdo, $paymentId, $approve, $adminId, $reason = null) {
    $st = $pdo->prepare("SELECT id, payment_number FROM mounting_payments WHERE id = ? AND kind = 'payment'");
    $st->execute([intval($paymentId)]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new Exception('Payment not found.');
    $where = $p['payment_number'] ? "payment_number = ?" : "id = ?";
    $key = $p['payment_number'] ?: $p['id'];
    $rows = $pdo->prepare("SELECT id, trip_id FROM mounting_payments WHERE $where AND approval_status = 'pending_approval' FOR UPDATE");
    $rows->execute([$key]);
    $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) throw new Exception('This payment is not waiting for approval.');
    $pdo->prepare("UPDATE mounting_payments SET approval_status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ?
                   WHERE $where AND approval_status = 'pending_approval'")
        ->execute([$approve ? 'approved' : 'rejected', $adminId, $approve ? null : (mb_substr(trim((string)$reason), 0, 255) ?: null), $key]);
    foreach (array_unique(array_column($rows, 'trip_id')) as $tid) recomputeMountingTripPayment($pdo, $tid);
    $ids = array_column($rows, 'id');
    $place = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("UPDATE approval_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), remarks = ?
                   WHERE entity_type = 'mounting_payment' AND entity_id IN ($place) AND status = 'pending'")
        ->execute(array_merge([$approve ? 'approved' : 'rejected', $adminId, $reason ?: null], $ids));
    return $p['payment_number'] ?: ('#' . $p['id']);
}

/** Delete a contractor that has no entries (otherwise mark it Inactive). */
function deleteMountingContractor(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM mounting_trips WHERE contractor_id = ?");
    $st->execute([intval($id)]);
    if ($st->fetchColumn() > 0) throw new Exception('This contractor has entries. Mark them Inactive instead of deleting.');
    $st = $pdo->prepare("DELETE FROM mounting_contractors WHERE id = ?");
    $st->execute([intval($id)]);
    if (!$st->rowCount()) throw new Exception('Contractor not found.');
}

const MOUNTING_UPLOAD_DIR = __DIR__ . '/../uploads/mounting_payments/';

/**
 * Save an uploaded receipt / screenshot for a payment. Returns the stored file name, or null when no file was sent.
 * Allowed: pdf, jpg, jpeg, png, doc(x), xls(x) up to 10 MB (UploadValidator); the name is generated, never the uploader's.
 */
function saveMountingAttachment($file) {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Attachment upload failed. Try a smaller file (max 10 MB).');
    require_once __DIR__ . '/upload_validator.php';
    $ext = UploadValidator::validate($file);
    UploadValidator::ensureUploadDir(MOUNTING_UPLOAD_DIR);
    $name = 'mpay_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], MOUNTING_UPLOAD_DIR . $name)) throw new Exception('Could not save the attachment.');
    return $name;
}

function removeMountingAttachment($name) {
    if ($name && preg_match('/^mpay_[0-9_]+_[a-f0-9]+\.[a-z0-9]+$/', $name) && is_file(MOUNTING_UPLOAD_DIR . $name)) @unlink(MOUNTING_UPLOAD_DIR . $name);
}

function mountingLoadPaymentForChange(PDO $pdo, $paymentId) {
    $stmt = $pdo->prepare("SELECT * FROM mounting_payments WHERE id = ? FOR UPDATE");
    $stmt->execute([$paymentId]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p || $p['kind'] !== 'payment') throw new Exception('Payment not found.');
    return $p;
}

/** Revert a payment: the row stays (status reversed) for audit; balances update. */
function reverseMountingPayment(PDO $pdo, $paymentId, $userId, $reason = null) {
    $pdo->beginTransaction();
    try {
        $p = mountingLoadPaymentForChange($pdo, $paymentId);
        if ($p['status'] === 'reversed') throw new Exception('Already reverted.');
        $pdo->prepare("UPDATE mounting_payments SET status = 'reversed', reversed_by = ?, reversed_at = NOW(), reverse_reason = ? WHERE id = ?")
            ->execute([$userId, mb_substr(trim((string)$reason), 0, 255) ?: null, $paymentId]);
        if ($p['trip_id']) recomputeMountingTripPayment($pdo, $p['trip_id']);
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

/** Permanently remove a wrong payment entry (admin). */
function deleteMountingPayment(PDO $pdo, $paymentId) {
    $pdo->beginTransaction();
    try {
        $p = mountingLoadPaymentForChange($pdo, $paymentId);
        $pdo->prepare("DELETE FROM mounting_payments WHERE id = ?")->execute([$paymentId]);
        if ($p['trip_id']) recomputeMountingTripPayment($pdo, $p['trip_id']);
        $still = 0;
        if (!empty($p['attachment'])) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM mounting_payments WHERE attachment = ?");
            $c->execute([$p['attachment']]);
            $still = (int)$c->fetchColumn();
        }
        $pdo->commit();
        if (!$still) removeMountingAttachment($p['attachment'] ?? null);
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

/**
 * Payment transactions, one line per payment number (a bulk payment over several entries is one line).
 * Filters: from, to (paid_date), contractor (LIKE), status ('active' | 'reversed'), entity_id.
 */
function listMountingPaymentGroups(PDO $pdo, array $f = []) {
    $where = ["p.kind = 'payment'"];
    $par = [];
    if (!empty($f['from'])) { $where[] = 'p.paid_date >= ?'; $par[] = $f['from']; }
    if (!empty($f['to'])) { $where[] = 'p.paid_date <= ?'; $par[] = $f['to']; }
    if (!empty($f['contractor'])) { $where[] = 'p.contractor_name LIKE ?'; $par[] = '%' . $f['contractor'] . '%'; }
    if (!empty($f['entity_id'])) { $where[] = '(t.entity_id = ? OR t.entity_id IS NULL)'; $par[] = intval($f['entity_id']); }
    $having = '';
    if (($f['status'] ?? '') === 'active') $having = " HAVING active_rows > 0";
    if (($f['status'] ?? '') === 'reversed') $having = " HAVING active_rows = 0";
    $st = $pdo->prepare("
        SELECT COALESCE(p.payment_number, CONCAT('#', p.id)) AS ref,
               MAX(p.payment_number) AS payment_number, MIN(p.id) AS first_id,
               MIN(p.paid_date) AS paid_date, MAX(p.contractor_name) AS contractor_name, MAX(t.contractor_id) AS contractor_id,
               MAX(p.payment_mode) AS payment_mode, MAX(p.payment_ref) AS payment_ref, MAX(p.notes) AS notes, MAX(p.attachment) AS attachment,
               COUNT(*) AS rows_count,
               SUM(p.status = 'active' AND p.approval_status <> 'rejected') AS active_rows,
               SUM(CASE WHEN p.status = 'active' AND p.approval_status <> 'rejected' THEN p.amount ELSE 0 END) AS amount,
               MAX(p.approval_status) AS approval_status, MAX(p.rejection_reason) AS rejection_reason,
               SUM(p.amount) AS original_amount,
               GROUP_CONCAT(CONCAT(t.trip_number, ': Rs.', p.amount, IF(p.status = 'reversed', ' (reverted)', '')) ORDER BY t.trip_date, t.id SEPARATOR '; ') AS entries,
               MAX(p.reverse_reason) AS reverse_reason
        FROM mounting_payments p JOIN mounting_trips t ON t.id = p.trip_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY COALESCE(p.payment_number, CONCAT('#', p.id))" . $having . "
        ORDER BY MIN(p.paid_date) DESC, MIN(p.id) DESC");
    $st->execute($par);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Revert a whole payment (every entry it paid). Rows stay as reversed; entry balances go back up. */
function reverseMountingPaymentGroup(PDO $pdo, $paymentNumber, $userId, $reason = null) {
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT id, trip_id FROM mounting_payments WHERE payment_number = ? AND kind = 'payment' AND status = 'active' FOR UPDATE");
        $st->execute([$paymentNumber]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) throw new Exception('Payment not found or already reverted.');
        $pdo->prepare("UPDATE mounting_payments SET status = 'reversed', reversed_by = ?, reversed_at = NOW(), reverse_reason = ? WHERE payment_number = ? AND status = 'active'")
            ->execute([$userId, mb_substr(trim((string)$reason), 0, 255) ?: null, $paymentNumber]);
        foreach (array_unique(array_column($rows, 'trip_id')) as $tid) recomputeMountingTripPayment($pdo, $tid);
        $pdo->commit();
        return count($rows);
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

/** Permanently remove a wrong payment and all its entry rows (admin). */
function deleteMountingPaymentGroup(PDO $pdo, $paymentNumber) {
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT id, trip_id, attachment FROM mounting_payments WHERE payment_number = ? AND kind = 'payment' FOR UPDATE");
        $st->execute([$paymentNumber]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) throw new Exception('Payment not found.');
        $pdo->prepare("DELETE FROM mounting_payments WHERE payment_number = ?")->execute([$paymentNumber]);
        foreach (array_unique(array_column($rows, 'trip_id')) as $tid) recomputeMountingTripPayment($pdo, $tid);
        $files = array_unique(array_filter(array_column($rows, 'attachment')));
        $left = [];
        foreach ($files as $f) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM mounting_payments WHERE attachment = ?");
            $c->execute([$f]);
            if (!$c->fetchColumn()) $left[] = $f;
        }
        $pdo->commit();
        foreach ($left as $f) removeMountingAttachment($f);
        return count($rows);
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

/** Active contractors for the entry form dropdown. */
function listActiveMountingContractors(PDO $pdo) {
    return $pdo->query("SELECT id, name, mobile FROM mounting_contractors WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

/** Payment history of one entry (including reverted). */
function listMountingTripPayments(PDO $pdo, $tripId) {
    $stmt = $pdo->prepare("SELECT p.*,
               (SELECT COUNT(*) FROM mounting_payments b WHERE b.payment_number = p.payment_number) AS bulk_count,
               (SELECT SUM(b.amount) FROM mounting_payments b WHERE b.payment_number = p.payment_number AND b.status = 'active') AS bulk_total,
               (SELECT GROUP_CONCAT(t.trip_number ORDER BY t.trip_date, t.id SEPARATOR ', ') FROM mounting_payments b JOIN mounting_trips t ON t.id = b.trip_id WHERE b.payment_number = p.payment_number) AS bulk_entries
        FROM mounting_payments p WHERE p.trip_id = ? AND p.kind = 'payment' ORDER BY p.paid_date, p.id");
    $stmt->execute([$tripId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Delete an entry with no active payments. Lines go with it (FK cascade); reverted payment rows and the approval request are removed. */
function deleteMountingTrip(PDO $pdo, $tripId) {
    $pdo->beginTransaction();
    try {
        if (mountingTripHasPayments($pdo, $tripId)) throw new Exception('This entry has payments. Revert or delete its payments first.');
        $pdo->prepare("DELETE FROM mounting_payments WHERE trip_id = ? AND (status = 'reversed' OR approval_status = 'rejected')")->execute([$tripId]);
        $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'mounting_payment' AND status = 'pending' AND entity_id NOT IN (SELECT id FROM mounting_payments)")->execute();
        $stmt = $pdo->prepare("DELETE FROM mounting_trips WHERE id = ?");
        $stmt->execute([$tripId]);
        if (!$stmt->rowCount()) throw new Exception('Entry not found.');
        $pdo->prepare("DELETE FROM approval_requests WHERE entity_type = 'mounting_trip' AND entity_id = ?")->execute([$tripId]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Trip header + lines (with site code/name/owner type) for view/edit. */
function getMountingTrip(PDO $pdo, $tripId) {
    $stmt = $pdo->prepare("SELECT * FROM mounting_trips WHERE id = ?");
    $stmt->execute([$tripId]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$trip) return null;
    $stmt = $pdo->prepare("SELECT mts.*, s.site_code, s.name AS site_name, s.location, s.owner_type
                           FROM mounting_trip_sites mts JOIN sites s ON s.id = mts.site_id
                           WHERE mts.trip_id = ? ORDER BY mts.id");
    $stmt->execute([$tripId]);
    $trip['sites'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $trip;
}

/**
 * Register list with filters: from, to (trip_date), contractor (LIKE), site_id, approval_status, payment_status, entity_id.
 */
function listMountingTrips(PDO $pdo, array $f = []) {
    $where = ['1=1'];
    $p = [];
    if (!empty($f['from'])) { $where[] = 't.trip_date >= ?'; $p[] = $f['from']; }
    if (!empty($f['to'])) { $where[] = 't.trip_date <= ?'; $p[] = $f['to']; }
    if (!empty($f['contractor'])) { $where[] = '(t.contractor_name LIKE ? OR t.brand_campaign LIKE ?)'; $p[] = '%' . $f['contractor'] . '%'; $p[] = '%' . $f['contractor'] . '%'; }
    if (!empty($f['approval_status'])) { $where[] = 't.approval_status = ?'; $p[] = $f['approval_status']; }
    if (!empty($f['payment_status'])) { $where[] = 't.payment_status = ?'; $p[] = $f['payment_status']; }
    if (!empty($f['entity_id'])) { $where[] = '(t.entity_id = ? OR t.entity_id IS NULL)'; $p[] = intval($f['entity_id']); }
    if (!empty($f['site_id'])) { $where[] = 'EXISTS (SELECT 1 FROM mounting_trip_sites x WHERE x.trip_id = t.id AND x.site_id = ?)'; $p[] = intval($f['site_id']); }

    $stmt = $pdo->prepare("SELECT t.*,
               (SELECT GROUP_CONCAT(s.site_code ORDER BY mts.id SEPARATOR ', ') FROM mounting_trip_sites mts JOIN sites s ON s.id = mts.site_id WHERE mts.trip_id = t.id) AS site_codes,
               (SELECT COUNT(*) FROM mounting_trip_sites mts WHERE mts.trip_id = t.id) AS site_count,
               (SELECT COALESCE(SUM(mp.amount), 0) FROM mounting_payments mp WHERE mp.trip_id = t.id AND mp.kind = 'payment' AND mp.status = 'active' AND mp.approval_status = 'pending_approval') AS pending_pay
        FROM mounting_trips t WHERE " . implode(' AND ', $where) . " ORDER BY t.trip_date DESC, t.id DESC");
    $stmt->execute($p);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Auto-suggest: active contractors from the master matching $q (name or mobile). */
function suggestMountingContractors(PDO $pdo, $q, $limit = 10) {
    $like = '%' . trim((string)$q) . '%';
    $stmt = $pdo->prepare("SELECT id, name AS contractor_name, mobile AS contractor_mobile FROM mounting_contractors
                           WHERE is_active = 1 AND (name LIKE ? OR mobile LIKE ?) ORDER BY name LIMIT " . intval($limit));
    $stmt->execute([$like, $like]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ------------------------------------------------------------------------------------------------
 * Labour contractor master (mounting_contractors). Entries keep a copy of the name/mobile and link by contractor_id.
 * ---------------------------------------------------------------------------------------------- */

function mountingCleanMobile($mobile) {
    $m = preg_replace('/\D/', '', (string)$mobile);
    if (strlen($m) === 12 && substr($m, 0, 2) === '91') $m = substr($m, 2);
    if (strlen($m) === 11 && $m[0] === '0') $m = substr($m, 1);
    if ($m !== '' && !preg_match('/^[6-9][0-9]{9}$/', $m)) throw new Exception('Enter a valid 10-digit mobile number (e.g. 9876543210).');
    return $m !== '' ? $m : null;
}

/** Contractor id for a name; adds the contractor to the master when new, fills a missing mobile. */
function mountingFindOrCreateContractor(PDO $pdo, $name, $mobile = null, $userId = null) {
    $name = trim((string)$name);
    $st = $pdo->prepare("SELECT id, mobile FROM mounting_contractors WHERE name = ?");
    $st->execute([$name]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if ($c) {
        if (!$c['mobile'] && $mobile) $pdo->prepare("UPDATE mounting_contractors SET mobile = ? WHERE id = ?")->execute([$mobile, $c['id']]);
        return intval($c['id']);
    }
    $pdo->prepare("INSERT INTO mounting_contractors (name, mobile, created_by) VALUES (?, ?, ?)")->execute([$name, $mobile ?: null, $userId]);
    return intval($pdo->lastInsertId());
}

/** Add (id = 0) or edit a contractor. A rename is copied to that contractor's entries and payments. */
function saveMountingContractor(PDO $pdo, $id, array $in, $userId) {
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 150) throw new Exception('Contractor name is required (max 150 characters).');
    $mobile = mountingCleanMobile($in['mobile'] ?? '');
    $address = mb_substr(trim((string)($in['address'] ?? '')), 0, 255) ?: null;
    $notes = mb_substr(trim((string)($in['notes'] ?? '')), 0, 255) ?: null;
    $active = !isset($in['is_active']) || !empty($in['is_active']) ? 1 : 0;
    $dup = $pdo->prepare("SELECT id FROM mounting_contractors WHERE name = ? AND id <> ?");
    $dup->execute([$name, intval($id)]);
    if ($dup->fetchColumn()) throw new Exception('A contractor with this name already exists.');
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $st = $pdo->prepare("UPDATE mounting_contractors SET name = ?, mobile = ?, address = ?, notes = ?, is_active = ? WHERE id = ?");
            $st->execute([$name, $mobile, $address, $notes, $active, $id]);
            $pdo->prepare("UPDATE mounting_trips SET contractor_name = ?, contractor_mobile = COALESCE(?, contractor_mobile) WHERE contractor_id = ?")->execute([$name, $mobile, $id]);
            $pdo->prepare("UPDATE mounting_payments p JOIN mounting_trips t ON t.id = p.trip_id SET p.contractor_name = ? WHERE t.contractor_id = ?")->execute([$name, $id]);
        } else {
            $pdo->prepare("INSERT INTO mounting_contractors (name, mobile, address, notes, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$name, $mobile, $address, $notes, $active, $userId]);
            $id = intval($pdo->lastInsertId());
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
    return intval($id);
}

/** Contractors with their approved entries total, paid and due. Filter by name / mobile with $q. */
function listMountingContractors(PDO $pdo, $q = '', $entityId = null) {
    $ent = $entityId ? " AND (t.entity_id = " . intval($entityId) . " OR t.entity_id IS NULL)" : "";
    $like = '%' . trim((string)$q) . '%';
    $st = $pdo->prepare("
        SELECT c.*,
               COUNT(t.id) AS entries,
               COALESCE(SUM(t.total_amount), 0) AS total,
               COALESCE(SUM(t.paid_amount), 0) AS paid,
               COALESCE(SUM(t.total_amount - t.paid_amount), 0) AS due,
               MAX(t.trip_date) AS last_entry
        FROM mounting_contractors c
        LEFT JOIN mounting_trips t ON t.contractor_id = c.id AND t.approval_status = 'approved' $ent
        WHERE c.name LIKE ? OR c.mobile LIKE ?
        GROUP BY c.id ORDER BY c.is_active DESC, due DESC, c.name");
    $st->execute([$like, $like]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** One contractor: details, all entries (any status) and all payments, with approved totals. */
function getMountingContractorLedger(PDO $pdo, $id, $entityId = null) {
    $st = $pdo->prepare("SELECT * FROM mounting_contractors WHERE id = ?");
    $st->execute([intval($id)]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) return null;
    $ent = $entityId ? " AND (t.entity_id = " . intval($entityId) . " OR t.entity_id IS NULL)" : "";
    $st = $pdo->prepare("SELECT t.*,
               (SELECT GROUP_CONCAT(s.site_code ORDER BY mts.id SEPARATOR ', ') FROM mounting_trip_sites mts JOIN sites s ON s.id = mts.site_id WHERE mts.trip_id = t.id) AS site_codes
        FROM mounting_trips t WHERE t.contractor_id = ? $ent ORDER BY t.trip_date DESC, t.id DESC");
    $st->execute([$c['id']]);
    $entries = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT p.*, t.trip_number, t.brand_campaign FROM mounting_payments p JOIN mounting_trips t ON t.id = p.trip_id
                         WHERE t.contractor_id = ? AND p.kind = 'payment' $ent ORDER BY p.paid_date DESC, p.id DESC");
    $st->execute([$c['id']]);
    $payments = $st->fetchAll(PDO::FETCH_ASSOC);
    $total = $paid = 0.0;
    foreach ($entries as $e) if ($e['approval_status'] === 'approved') { $total += $e['total_amount']; $paid += $e['paid_amount']; }
    return ['contractor' => $c, 'entries' => $entries, 'payments' => $payments,
            'totals' => ['total' => round($total, 2), 'paid' => round($paid, 2), 'due' => round($total - $paid, 2)]];
}

/** Brand / campaign suggestions: earlier entries and bookings. */
function suggestMountingBrands(PDO $pdo, $q, $limit = 15) {
    $like = '%' . trim((string)$q) . '%';
    $st = $pdo->prepare("SELECT v FROM (
            SELECT brand_campaign AS v FROM mounting_trips WHERE brand_campaign LIKE ?
            UNION SELECT CONVERT(campaign_name USING utf8mb4) COLLATE utf8mb4_general_ci FROM bookings WHERE campaign_name LIKE ?
            UNION SELECT CONVERT(brand_name USING utf8mb4) COLLATE utf8mb4_general_ci FROM bookings WHERE brand_name LIKE ?
        ) x WHERE v IS NOT NULL AND v <> '' ORDER BY v LIMIT " . intval($limit));
    $st->execute([$like, $like, $like]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}


/**
 * Site P&L feed: approved mounting cost lines for one site, optionally within a date range.
 * Each row: trip_id, trip_number, trip_date, contractor_name, sqft, labour_amount, overhead_share, total_cost, payment_status.
 */
function getSiteMountingExpenses(PDO $pdo, $siteId, $from = null, $to = null) {
    $sql = "SELECT t.id AS trip_id, t.trip_number, t.trip_date, t.contractor_name, t.payment_status,
                   mts.sqft, mts.labour_amount, mts.overhead_share, mts.total_cost
            FROM mounting_trip_sites mts JOIN mounting_trips t ON t.id = mts.trip_id
            WHERE mts.site_id = ? AND t.approval_status = 'approved'";
    $p = [intval($siteId)];
    if ($from) { $sql .= " AND t.trip_date >= ?"; $p[] = $from; }
    if ($to) { $sql .= " AND t.trip_date <= ?"; $p[] = $to; }
    $stmt = $pdo->prepare($sql . " ORDER BY t.trip_date, t.id");
    $stmt->execute($p);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Total approved mounting cost per site (for site-wise P&L reports): [site_id => amount]. */
function getMountingCostBySite(PDO $pdo, $from = null, $to = null) {
    $sql = "SELECT mts.site_id, SUM(mts.total_cost) AS cost FROM mounting_trip_sites mts JOIN mounting_trips t ON t.id = mts.trip_id
            WHERE t.approval_status = 'approved'";
    $p = [];
    if ($from) { $sql .= " AND t.trip_date >= ?"; $p[] = $from; }
    if ($to) { $sql .= " AND t.trip_date <= ?"; $p[] = $to; }
    $stmt = $pdo->prepare($sql . " GROUP BY mts.site_id");
    $stmt->execute($p);
    return array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
}

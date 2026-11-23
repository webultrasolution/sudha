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
        if ($tripId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM mounting_trips WHERE id = ? FOR UPDATE");
            $stmt->execute([$tripId]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new Exception('Trip not found.');
            if (mountingTripHasPayments($pdo, $tripId)) throw new Exception('This entry has payments. Revert or delete its payments before editing.');
            if ($old['approval_status'] === 'approved' && !$isAdmin) throw new Exception('Approved trips can only be edited by admin.');
            $tripNumber = $old['trip_number'];

            $pdo->prepare("UPDATE mounting_trips SET trip_date=?, contractor_name=?, contractor_mobile=?, rate_per_sqft=?,
                    total_sqft=?, labour_amount=?, food_amount=?, travel_amount=?, misc_amount=?, total_amount=?, description=?,
                    approval_status=?, approved_by=?, approved_at=?, rejection_reason=NULL WHERE id=?")
                ->execute([$d['trip_date'], $d['contractor_name'], $d['contractor_mobile'], $d['rate_per_sqft'],
                    $calc['total_sqft'], $calc['labour_amount'], $d['food_amount'], $d['travel_amount'], $d['misc_amount'],
                    $calc['total_amount'], $d['description'], $status, $isAdmin ? $userId : null, $isAdmin ? date('Y-m-d H:i:s') : null, $tripId]);
            $pdo->prepare("DELETE FROM mounting_trip_sites WHERE trip_id = ?")->execute([$tripId]);
        } else {
            $tripNumber = generateSequenceNumber($pdo, MOUNTING_MODULE_KEY, $d['trip_date']);
            $pdo->prepare("INSERT INTO mounting_trips (entity_id, trip_number, trip_date, contractor_name, contractor_mobile, rate_per_sqft,
                    total_sqft, labour_amount, food_amount, travel_amount, misc_amount, total_amount, description,
                    approval_status, approved_by, approved_at, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$entityId, $tripNumber, $d['trip_date'], $d['contractor_name'], $d['contractor_mobile'], $d['rate_per_sqft'],
                    $calc['total_sqft'], $calc['labour_amount'], $d['food_amount'], $d['travel_amount'], $d['misc_amount'],
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
 * Contractor payments (table mounting_payments)
 *   payment    = cash paid against one entry (partial allowed, never more than the entry's balance)
 *   advance    = cash given to a contractor before/without an entry
 *   adjustment = part of an advance applied to an entry (no new cash)
 * An entry's paid amount = its active payments + adjustments; status pending / partial / paid.
 * Revert keeps the row for audit (status 'reversed'); delete removes a wrong entry (admin).
 * ---------------------------------------------------------------------------------------------- */

function mountingTripHasPayments(PDO $pdo, $tripId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mounting_payments WHERE trip_id = ? AND status = 'active'");
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
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0), MAX(paid_date) FROM mounting_payments WHERE trip_id = ? AND status = 'active'");
    $stmt->execute([$tripId]);
    [$paid, $last] = $stmt->fetch(PDO::FETCH_NUM);
    $paid = round(floatval($paid), 2);
    $t = $pdo->prepare("SELECT total_amount FROM mounting_trips WHERE id = ?");
    $t->execute([$tripId]);
    $total = round(floatval($t->fetchColumn()), 2);
    $status = $paid <= 0.009 ? 'pending' : ($paid + 0.009 >= $total ? 'paid' : 'partial');
    $pdo->prepare("UPDATE mounting_trips SET paid_amount = ?, payment_status = ?, paid_date = ? WHERE id = ?")
        ->execute([$paid, $status, $paid > 0 ? $last : null, $tripId]);
    return ['paid' => $paid, 'total' => $total, 'balance' => round($total - $paid, 2), 'status' => $status];
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
function addMountingTripPayment(PDO $pdo, $tripId, $amount, $date, $mode, $ref, $notes, $userId) {
    $amount = mountingValidAmount($amount);
    mountingValidDate($date);
    if (!in_array($mode, MOUNTING_PAYMENT_MODES, true)) throw new Exception('Select a payment mode.');
    $pdo->beginTransaction();
    try {
        $t = mountingLockTrip($pdo, $tripId);
        $state = recomputeMountingTripPayment($pdo, $tripId);
        if ($amount > $state['balance'] + 0.009) throw new Exception('Amount is more than the balance of ' . mountingRupees($state['balance']) . '. Use "Give Advance" for extra money.');
        $pdo->prepare("INSERT INTO mounting_payments (entity_id, kind, trip_id, contractor_name, contractor_mobile, amount, paid_date, payment_mode, payment_ref, notes, created_by)
                       VALUES (?, 'payment', ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$t['entity_id'], $tripId, $t['contractor_name'], $t['contractor_mobile'], $amount, $date, $mode,
                       mb_substr(trim((string)$ref), 0, 100) ?: null, mb_substr(trim((string)$notes), 0, 255) ?: null, $userId]);
        $state = recomputeMountingTripPayment($pdo, $tripId);
        $pdo->commit();
        return $state;
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

/** Advance to a contractor, not tied to any entry yet. */
function addMountingAdvance(PDO $pdo, $contractorName, $mobile, $amount, $date, $mode, $ref, $notes, $userId, $entityId = null) {
    $name = trim((string)$contractorName);
    if ($name === '' || mb_strlen($name) > 150) throw new Exception('Labour contractor name is required.');
    $amount = mountingValidAmount($amount);
    mountingValidDate($date);
    if (!in_array($mode, MOUNTING_PAYMENT_MODES, true)) throw new Exception('Select a payment mode.');
    $m = preg_replace('/\D/', '', (string)$mobile);
    if (strlen($m) === 12 && substr($m, 0, 2) === '91') $m = substr($m, 2);
    if (strlen($m) === 11 && $m[0] === '0') $m = substr($m, 1);
    if ($m !== '' && !preg_match('/^[6-9][0-9]{9}$/', $m)) throw new Exception('Enter a valid 10-digit mobile number.');
    $pdo->prepare("INSERT INTO mounting_payments (entity_id, kind, contractor_name, contractor_mobile, amount, paid_date, payment_mode, payment_ref, notes, created_by)
                   VALUES (?, 'advance', ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$entityId, $name, $m ?: null, $amount, $date, $mode, mb_substr(trim((string)$ref), 0, 100) ?: null, mb_substr(trim((string)$notes), 0, 255) ?: null, $userId]);
    return intval($pdo->lastInsertId());
}

/** Unused part of an advance (advance minus its active adjustments); null if not an active advance. */
function mountingAdvanceRemaining(PDO $pdo, $advanceId) {
    $stmt = $pdo->prepare("SELECT a.amount - COALESCE((SELECT SUM(x.amount) FROM mounting_payments x WHERE x.advance_id = a.id AND x.status = 'active'), 0)
                           FROM mounting_payments a WHERE a.id = ? AND a.kind = 'advance' AND a.status = 'active'");
    $stmt->execute([$advanceId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : round(floatval($v), 2);
}

/** Open advances of a contractor (remaining > 0), oldest first. */
function listMountingOpenAdvances(PDO $pdo, $contractorName) {
    $stmt = $pdo->prepare("SELECT a.id, a.amount, a.paid_date, a.payment_mode, a.payment_ref,
               a.amount - COALESCE((SELECT SUM(x.amount) FROM mounting_payments x WHERE x.advance_id = a.id AND x.status = 'active'), 0) AS remaining
        FROM mounting_payments a WHERE a.kind = 'advance' AND a.status = 'active' AND a.contractor_name = ?
        HAVING remaining > 0.009 ORDER BY a.paid_date, a.id");
    $stmt->execute([trim((string)$contractorName)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Apply part of an advance to an entry of the same contractor. No new cash moves. */
function adjustMountingAdvance(PDO $pdo, $advanceId, $tripId, $amount, $userId) {
    $amount = mountingValidAmount($amount);
    $pdo->beginTransaction();
    try {
        $t = mountingLockTrip($pdo, $tripId);
        $stmt = $pdo->prepare("SELECT * FROM mounting_payments WHERE id = ? AND kind = 'advance' AND status = 'active' FOR UPDATE");
        $stmt->execute([$advanceId]);
        $adv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$adv) throw new Exception('Advance not found or reverted.');
        if (mb_strtolower(trim($adv['contractor_name'])) !== mb_strtolower(trim($t['contractor_name']))) throw new Exception('This advance belongs to a different contractor.');
        $remaining = mountingAdvanceRemaining($pdo, $advanceId);
        if ($amount > $remaining + 0.009) throw new Exception('Only ' . mountingRupees($remaining) . ' of this advance is left.');
        $state = recomputeMountingTripPayment($pdo, $tripId);
        if ($amount > $state['balance'] + 0.009) throw new Exception('Amount is more than the entry balance of ' . mountingRupees($state['balance']) . '.');
        $pdo->prepare("INSERT INTO mounting_payments (entity_id, kind, trip_id, advance_id, contractor_name, contractor_mobile, amount, paid_date, payment_mode, payment_ref, notes, created_by)
                       VALUES (?, 'adjustment', ?, ?, ?, ?, ?, ?, 'Advance', ?, ?, ?)")
            ->execute([$t['entity_id'], $tripId, $advanceId, $t['contractor_name'], $t['contractor_mobile'], $amount, date('Y-m-d'),
                       'ADV #' . $advanceId, 'Adjusted from advance of ' . date('d M Y', strtotime($adv['paid_date'])), $userId]);
        $state = recomputeMountingTripPayment($pdo, $tripId);
        $pdo->commit();
        return $state;
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

function mountingLoadPaymentForChange(PDO $pdo, $paymentId) {
    $stmt = $pdo->prepare("SELECT * FROM mounting_payments WHERE id = ? FOR UPDATE");
    $stmt->execute([$paymentId]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new Exception('Payment not found.');
    if ($p['kind'] === 'advance') {
        $c = $pdo->prepare("SELECT COUNT(*) FROM mounting_payments WHERE advance_id = ? AND status = 'active'");
        $c->execute([$paymentId]);
        if ($c->fetchColumn() > 0) throw new Exception('Part of this advance is adjusted against entries. Revert those adjustments first.');
    }
    return $p;
}

/** Revert a payment / advance / adjustment: the row stays (status reversed) for audit; balances update. */
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
        if ($p['kind'] === 'advance') {
            $c = $pdo->prepare("SELECT COUNT(*) FROM mounting_payments WHERE advance_id = ?");
            $c->execute([$paymentId]);
            if ($c->fetchColumn() > 0) throw new Exception('This advance has adjustment history. Revert it instead of deleting.');
        }
        $pdo->prepare("DELETE FROM mounting_payments WHERE id = ?")->execute([$paymentId]);
        if ($p['trip_id']) recomputeMountingTripPayment($pdo, $p['trip_id']);
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

/** Payment history of one entry (payments + adjustments, including reverted). */
function listMountingTripPayments(PDO $pdo, $tripId) {
    $stmt = $pdo->prepare("SELECT * FROM mounting_payments WHERE trip_id = ? ORDER BY paid_date, id");
    $stmt->execute([$tripId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Advances of one contractor (or all), with remaining amount, including reverted. */
function listMountingAdvances(PDO $pdo, $contractorName = null) {
    $sql = "SELECT a.*, a.amount - COALESCE((SELECT SUM(x.amount) FROM mounting_payments x WHERE x.advance_id = a.id AND x.status = 'active'), 0) AS remaining
            FROM mounting_payments a WHERE a.kind = 'advance'";
    $p = [];
    if ($contractorName !== null && $contractorName !== '') { $sql .= " AND a.contractor_name = ?"; $p[] = trim($contractorName); }
    $stmt = $pdo->prepare($sql . " ORDER BY a.paid_date DESC, a.id DESC");
    $stmt->execute($p);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Delete an entry with no active payments. Lines go with it (FK cascade); reverted payment rows and the approval request are removed. */
function deleteMountingTrip(PDO $pdo, $tripId) {
    $pdo->beginTransaction();
    try {
        if (mountingTripHasPayments($pdo, $tripId)) throw new Exception('This entry has payments. Revert or delete its payments first.');
        $pdo->prepare("DELETE FROM mounting_payments WHERE trip_id = ? AND status = 'reversed'")->execute([$tripId]);
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
    if (!empty($f['contractor'])) { $where[] = 't.contractor_name LIKE ?'; $p[] = '%' . $f['contractor'] . '%'; }
    if (!empty($f['approval_status'])) { $where[] = 't.approval_status = ?'; $p[] = $f['approval_status']; }
    if (!empty($f['payment_status'])) { $where[] = 't.payment_status = ?'; $p[] = $f['payment_status']; }
    if (!empty($f['entity_id'])) { $where[] = '(t.entity_id = ? OR t.entity_id IS NULL)'; $p[] = intval($f['entity_id']); }
    if (!empty($f['site_id'])) { $where[] = 'EXISTS (SELECT 1 FROM mounting_trip_sites x WHERE x.trip_id = t.id AND x.site_id = ?)'; $p[] = intval($f['site_id']); }

    $stmt = $pdo->prepare("SELECT t.*,
               (SELECT GROUP_CONCAT(s.site_code ORDER BY mts.id SEPARATOR ', ') FROM mounting_trip_sites mts JOIN sites s ON s.id = mts.site_id WHERE mts.trip_id = t.id) AS site_codes,
               (SELECT COUNT(*) FROM mounting_trip_sites mts WHERE mts.trip_id = t.id) AS site_count
        FROM mounting_trips t WHERE " . implode(' AND ', $where) . " ORDER BY t.trip_date DESC, t.id DESC");
    $stmt->execute($p);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Contractor-wise payable summary (approved trips only). */
function getMountingContractorSummary(PDO $pdo, $entityId = null) {
    // total = approved entries; paid = active payments + adjustments on them; pending = total - paid;
    // advance_left = active advances not yet adjusted; net_due = pending - advance_left (negative = advance with contractor)
    $ent = $entityId ? " AND (entity_id = " . intval($entityId) . " OR entity_id IS NULL)" : "";
    $rows = $pdo->query("
        SELECT c.contractor_name,
               COALESCE((SELECT x.contractor_mobile FROM mounting_trips x WHERE x.contractor_name = c.contractor_name AND x.contractor_mobile IS NOT NULL ORDER BY x.trip_date DESC LIMIT 1),
                        (SELECT y.contractor_mobile FROM mounting_payments y WHERE y.contractor_name = c.contractor_name AND y.contractor_mobile IS NOT NULL ORDER BY y.id DESC LIMIT 1)) AS contractor_mobile,
               (SELECT COUNT(*) FROM mounting_trips t WHERE t.contractor_name = c.contractor_name AND t.approval_status = 'approved' $ent) AS trips,
               (SELECT COALESCE(SUM(t.total_amount), 0) FROM mounting_trips t WHERE t.contractor_name = c.contractor_name AND t.approval_status = 'approved' $ent) AS total,
               (SELECT COALESCE(SUM(t.paid_amount), 0) FROM mounting_trips t WHERE t.contractor_name = c.contractor_name AND t.approval_status = 'approved' $ent) AS paid,
               (SELECT COALESCE(SUM(a.amount), 0) FROM mounting_payments a WHERE a.kind = 'advance' AND a.status = 'active' AND a.contractor_name = c.contractor_name $ent) AS advance_given,
               (SELECT COALESCE(SUM(j.amount), 0) FROM mounting_payments j WHERE j.kind = 'adjustment' AND j.status = 'active' AND j.contractor_name = c.contractor_name $ent) AS advance_used
        FROM (SELECT contractor_name FROM mounting_trips WHERE approval_status = 'approved' $ent
              UNION SELECT contractor_name FROM mounting_payments WHERE kind = 'advance' AND status = 'active' $ent) c
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['pending'] = round($r['total'] - $r['paid'], 2);
        $r['advance_left'] = round($r['advance_given'] - $r['advance_used'], 2);
        $r['net_due'] = round($r['pending'] - $r['advance_left'], 2);
    }
    unset($r);
    usort($rows, fn($a, $b) => ($b['net_due'] <=> $a['net_due']) ?: strcmp($a['contractor_name'], $b['contractor_name']));
    return $rows;
}

/** Auto-suggest: previously used contractors matching $q, with their latest mobile. */
function suggestMountingContractors(PDO $pdo, $q, $limit = 10) {
    $stmt = $pdo->prepare("SELECT t.contractor_name,
               (SELECT t2.contractor_mobile FROM mounting_trips t2 WHERE t2.contractor_name = t.contractor_name AND t2.contractor_mobile IS NOT NULL ORDER BY t2.trip_date DESC, t2.id DESC LIMIT 1) AS contractor_mobile
        FROM mounting_trips t WHERE t.contractor_name LIKE ? GROUP BY t.contractor_name ORDER BY MAX(t.trip_date) DESC LIMIT " . intval($limit));
    $stmt->execute(['%' . trim((string)$q) . '%']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

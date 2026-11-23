<?php
/**
 * Flex Mounting Register API (JSON). All logic lives in includes/mounting_functions.php.
 *
 * GET  ?action=list&from=&to=&contractor=&site_id=&approval_status=&payment_status=   -> {success, trips}
 * GET  ?action=get&id=                                                                 -> {success, trip}
 * GET  ?action=contractors&q=                                                     -> {success, contractors}  (master with total/paid/due)
 * GET  ?action=contractor_ledger&id=                                               -> {success, contractor, entries, payments, totals}
 * GET  ?action=brands&q=                                                           -> {success, brands}
 * POST {action:'contractor_save', id?, name, mobile, address, notes, is_active}
 * GET  ?action=contractor_options                                                -> {success, contractors}  (active, for the entry form)
 * GET  ?action=payment_list&from=&to=&contractor=&status=                          -> {success, payments}  (one line per payment number)
 * POST {action:'revert_group' | 'delete_group', payment_number, reason}            whole bulk payment (delete: admin)
 * GET  ?action=suggest&q=                                                              -> {success, contractors}
 * GET  ?action=sites&q=                                                                -> {success, sites}  (site master search)
 * POST {action:'save', id?, trip_date, contractor_name, contractor_mobile, rate_per_sqft,
 *       food_amount, travel_amount, misc_amount, description, sites:[{site_id, sqft, remarks}]}
 * POST {action:'preview', ...same as save}                                             -> calculated split, nothing saved
 * GET  ?action=payments&id=            -> {success, trip, payments}   (entry payment history)
 * POST {action:'pay', id, amount, paid_date, payment_mode, payment_ref, notes}       full or partial payment against an entry
 *      (may be sent as multipart/form-data with an optional file field 'attachment'). No advance payments.
 * POST {action:'bulk_pay', trip_ids (comma list), amount, paid_date, payment_mode, payment_ref, notes}  one payment over several
 *      entries of one contractor (multipart with optional 'attachment'); smaller amount fills oldest entries first
 * POST {action:'revert', payment_id, reason}                                        keeps the row, marks it reversed
 * POST {action:'delete_payment', payment_id}   (admin only)
 * POST {action:'delete', id}
 * Approve / reject goes through ajax/approve_entity.php with entity_type 'mounting_trip'.
 */
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ajax_auth.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/mounting_functions.php';

header('Content-Type: application/json');

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$userId = intval($_SESSION['user_id']);
$entityId = $_SESSION['active_entity_id'] ?? null;

function mountingDeny($msg = 'Access denied.') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function mountingDateParam($v) {
    return ($v && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) ? $v : null;
}

$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = $_POST;
}
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($body['action'] ?? '') : ($_GET['action'] ?? '');

try {
    switch ($action) {
        case 'list':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            $f = [
                'from' => $_GET['from'] ?? null, 'to' => $_GET['to'] ?? null,
                'contractor' => trim($_GET['contractor'] ?? ''), 'site_id' => intval($_GET['site_id'] ?? 0),
                'approval_status' => in_array($_GET['approval_status'] ?? '', ['pending_approval', 'approved', 'rejected'], true) ? $_GET['approval_status'] : null,
                'payment_status' => in_array($_GET['payment_status'] ?? '', ['pending', 'partial', 'paid'], true) ? $_GET['payment_status'] : null,
                'entity_id' => $entityId,
            ];
            foreach (['from', 'to'] as $k) {
                if ($f[$k] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) $f[$k] = null;
            }
            echo json_encode(['success' => true, 'trips' => listMountingTrips($pdo, $f)]);
            break;

        case 'get':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            $trip = getMountingTrip($pdo, intval($_GET['id'] ?? 0));
            if (!$trip) throw new Exception('Trip not found.');
            echo json_encode(['success' => true, 'trip' => $trip]);
            break;

        case 'suggest':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            echo json_encode(['success' => true, 'contractors' => suggestMountingContractors($pdo, $_GET['q'] ?? '')]);
            break;

        case 'contractors':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            echo json_encode(['success' => true, 'contractors' => listMountingContractors($pdo, $_GET['q'] ?? '', $entityId)]);
            break;

        case 'contractor_options':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            echo json_encode(['success' => true, 'contractors' => listActiveMountingContractors($pdo)]);
            break;

        case 'payment_list':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            echo json_encode(['success' => true, 'payments' => listMountingPaymentGroups($pdo, [
                'from' => mountingDateParam($_GET['from'] ?? null), 'to' => mountingDateParam($_GET['to'] ?? null),
                'contractor' => trim($_GET['contractor'] ?? ''), 'entity_id' => $entityId,
                'status' => in_array($_GET['status'] ?? '', ['active', 'reversed'], true) ? $_GET['status'] : null,
            ])]);
            break;

        case 'contractor_ledger':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            $l = getMountingContractorLedger($pdo, intval($_GET['id'] ?? 0), $entityId);
            if (!$l) throw new Exception('Contractor not found.');
            echo json_encode(['success' => true] + $l);
            break;

        case 'contractor_save':
            if (!canEdit(MOUNTING_MODULE_KEY)) mountingDeny();
            $cid = saveMountingContractor($pdo, intval($body['id'] ?? 0), $body, $userId);
            echo json_encode(['success' => true, 'id' => $cid, 'message' => 'Contractor saved.']);
            break;

        case 'brands':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            echo json_encode(['success' => true, 'brands' => suggestMountingBrands($pdo, $_GET['q'] ?? '')]);
            break;

        case 'sites':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            $q = '%' . trim($_GET['q'] ?? '') . '%';
            $stmt = $pdo->prepare("SELECT id, site_code, name, location, owner_type, sqft, width, height FROM sites
                                   WHERE site_code LIKE ? OR name LIKE ? OR location LIKE ? ORDER BY site_code LIMIT 20");
            $stmt->execute([$q, $q, $q]);
            echo json_encode(['success' => true, 'sites' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'preview':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            $d = validateMountingTripInput($pdo, $body);
            echo json_encode(['success' => true] + calculateMountingTrip($d['sites'], $d['rate_per_sqft'], $d['food_amount'], $d['travel_amount'], $d['misc_amount']));
            break;

        case 'save':
            $id = intval($body['id'] ?? 0);
            if ($id > 0 ? !canEdit(MOUNTING_MODULE_KEY) : !canAdd(MOUNTING_MODULE_KEY)) mountingDeny();
            $res = saveMountingTrip($pdo, $body, $id, $userId, $isAdmin, $entityId);
            $res['message'] = $res['approval_status'] === 'approved' ? 'Trip saved.' : 'Trip saved and sent for admin approval.';
            echo json_encode(['success' => true] + $res);
            break;

        case 'payments':
            if (!canView(MOUNTING_MODULE_KEY)) mountingDeny();
            $trip = getMountingTrip($pdo, intval($_GET['id'] ?? 0));
            if (!$trip) throw new Exception('Entry not found.');
            echo json_encode(['success' => true, 'trip' => $trip, 'payments' => listMountingTripPayments($pdo, $trip['id'])]);
            break;

        case 'pay':
            if (!canEdit(MOUNTING_MODULE_KEY)) mountingDeny();
            $file = saveMountingAttachment($_FILES['attachment'] ?? null);
            try {
                $st = addMountingTripPayment($pdo, intval($body['id'] ?? 0), $body['amount'] ?? 0, $body['paid_date'] ?? '', $body['payment_mode'] ?? '',
                                             $body['payment_ref'] ?? '', $body['notes'] ?? '', $userId, $file);
            } catch (Exception $e) { removeMountingAttachment($file); throw $e; }
            echo json_encode(['success' => true, 'message' => $st['status'] === 'paid' ? 'Payment saved. Entry fully paid.' : 'Payment saved. Balance ' . mountingRupees($st['balance']) . '.'] + $st);
            break;

        case 'bulk_pay':
            if (!canEdit(MOUNTING_MODULE_KEY)) mountingDeny();
            $ids = $body['trip_ids'] ?? [];
            if (!is_array($ids)) $ids = explode(',', (string)$ids);
            $file = saveMountingAttachment($_FILES['attachment'] ?? null);
            try {
                $r = addMountingBulkPayment($pdo, $ids, $body['amount'] ?? 0, $body['paid_date'] ?? '', $body['payment_mode'] ?? '',
                                            $body['payment_ref'] ?? '', $body['notes'] ?? '', $userId, $file);
            } catch (Exception $e) { removeMountingAttachment($file); throw $e; }
            echo json_encode(['success' => true, 'message' => 'Payment ' . $r['payment_number'] . ' saved for ' . count($r['rows']) . ' entr' . (count($r['rows']) === 1 ? 'y' : 'ies')
                              . ($r['balance'] > 0.009 ? '. Still due on the ticked entries: ' . mountingRupees($r['balance']) . '.' : '. All ticked entries are paid.')] + $r);
            break;

        case 'revert':
            if (!canEdit(MOUNTING_MODULE_KEY)) mountingDeny();
            reverseMountingPayment($pdo, intval($body['payment_id'] ?? 0), $userId, $body['reason'] ?? '');
            echo json_encode(['success' => true, 'message' => 'Payment reverted.']);
            break;

        case 'revert_group':
            if (!canEdit(MOUNTING_MODULE_KEY)) mountingDeny();
            $n = reverseMountingPaymentGroup($pdo, trim((string)($body['payment_number'] ?? '')), $userId, $body['reason'] ?? '');
            echo json_encode(['success' => true, 'message' => 'Payment reverted on ' . $n . ' entr' . ($n === 1 ? 'y' : 'ies') . '.']);
            break;

        case 'delete_group':
            if (!$isAdmin) mountingDeny('Only admin can delete a payment. Use Revert instead.');
            $n = deleteMountingPaymentGroup($pdo, trim((string)($body['payment_number'] ?? '')));
            echo json_encode(['success' => true, 'message' => 'Payment deleted (' . $n . ' entr' . ($n === 1 ? 'y' : 'ies') . ').']);
            break;

        case 'delete_payment':
            if (!$isAdmin) mountingDeny('Only admin can delete a payment. Use Revert instead.');
            deleteMountingPayment($pdo, intval($body['payment_id'] ?? 0));
            echo json_encode(['success' => true, 'message' => 'Payment deleted.']);
            break;

        case 'delete':
            if (!canDelete(MOUNTING_MODULE_KEY)) mountingDeny();
            deleteMountingTrip($pdo, intval($body['id'] ?? 0));
            echo json_encode(['success' => true, 'message' => 'Trip deleted.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

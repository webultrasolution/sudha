<?php
include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $propId = intval($data['proposal_id'] ?? 0);
    $sitesData = $data['sites'] ?? [];

    if (!$propId || empty($sitesData)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtProp = $pdo->prepare("SELECT start_date, end_date FROM proposals WHERE id = ?");
        $stmtProp->execute([$propId]);
        $proposal = $stmtProp->fetch();

        $days = 30;
        if ($proposal && !empty($proposal['start_date']) && !empty($proposal['end_date'])) {
            $d1 = new DateTime($proposal['start_date']);
            $d2 = new DateTime($proposal['end_date']);
            $days = $d1->diff($d2)->days + 1;
        }

        $stmtSite = $pdo->prepare("SELECT card_rate, purchase_rate FROM sites WHERE id = ?");
        $stmtItem = $pdo->prepare("INSERT INTO proposal_items (proposal_id, site_id, sale_rate, purchase_rate, margin_pct, amount, days, selected_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($sitesData as $s) {
            $sid = intval($s['id']);
            $img = isset($s['image']) && $s['image'] ? $s['image'] : null;

            // Check if already in proposal
            $chk = $pdo->prepare("SELECT id FROM proposal_items WHERE proposal_id = ? AND site_id = ?");
            $chk->execute([$propId, $sid]);
            if ($chk->fetch()) continue; // Skip existing

            $stmtSite->execute([$sid]);
            $site = $stmtSite->fetch();
            if ($site) {
                $pRate = floatval($site['purchase_rate']);
                $sRate = floatval($site['card_rate']); // Default to card rate
                $margin = $pRate > 0 ? (($sRate - $pRate) / $pRate * 100) : 0;
                $stmtItem->execute([$propId, $sid, $sRate, $pRate, $margin, $sRate, $days, $img]);
            }
        }

        // Recalculate totals
        $newTotal = $pdo->query("SELECT SUM(amount) FROM proposal_items WHERE proposal_id = $propId")->fetchColumn() ?: 0;
        $tax = $newTotal * 0.18;
        $p = $pdo->query("SELECT printing_cost, mounting_cost, discounting_pct FROM proposals WHERE id = $propId")->fetch();
        $base = $newTotal - ($newTotal * ($p['discounting_pct'] / 100));
        $newGrand = $base + $tax + $p['printing_cost'] + $p['mounting_cost'];

        $pdo->prepare("UPDATE proposals SET total_amount = ?, tax_amount = ?, grand_total = ? WHERE id = ?")
            ->execute([$newTotal, $tax, $newGrand, $propId]);

        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        if (!$isAdmin) {
            $propRef = $pdo->query("SELECT proposal_number FROM proposals WHERE id = $propId")->fetchColumn();
            revertToPendingOnEdit($pdo, 'proposals', $propId, 'proposal', $propRef, $_SESSION['user_id'] ?? 0);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>

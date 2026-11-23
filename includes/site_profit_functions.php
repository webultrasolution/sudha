<?php
/**
 * Site contribution (profit) for every site in one go, using exactly the rules of the per-site P&L
 * (modules/inventory/site_financials.php), so the ranking and a site's own P&L page show the same numbers:
 *   revenue   = booking line amounts on the site (booking created date in range)
 *   costs     = vendor PO lines on the site (PO created date) + vendor printing POs (rate x site sqft, created date)
 *             + landlord rent of own (HA) sites, pro rata for the days in range
 *             + Flex Mounting cost on the site (approved entries, entry date)
 *   profit    = revenue - costs; margin = profit / revenue
 * Dates are 'Y-m-d' or null (null = all time; rent then runs up to today, as on the site page).
 */
include_once __DIR__ . '/landlord_functions.php';

function getSiteContributions(PDO $pdo, $from = null, $to = null, $ownerType = null, $q = '') {
    $range = ($from && $to);
    $sites = $pdo->query("SELECT s.id, s.site_code, s.name, s.location, s.city, s.type, s.owner_type, s.width, s.height, s.sqft, p.name AS vendor_name
                          FROM sites s LEFT JOIN partners p ON p.id = s.vendor_id")->fetchAll(PDO::FETCH_ASSOC);
    $rows = [];
    foreach ($sites as $s) {
        $rows[$s['id']] = $s + ['revenue' => 0.0, 'bookings' => 0, 'po_cost' => 0.0, 'printing_cost' => 0.0, 'rent' => 0.0, 'mounting_cost' => 0.0];
    }

    $grab = function ($sql, $dateCol) use ($pdo, $range, $from, $to) {
        $st = $pdo->prepare($sql . ($range ? " AND DATE($dateCol) BETWEEN ? AND ?" : "") . " GROUP BY 1");
        $st->execute($range ? [$from, $to] : []);
        return $st->fetchAll(PDO::FETCH_NUM);
    };
    foreach ($grab("SELECT bi.site_id, COALESCE(SUM(bi.amount), 0), COUNT(DISTINCT b.id) FROM booking_items bi JOIN bookings b ON b.id = bi.booking_id JOIN partners c ON c.id = b.client_id WHERE 1=1", 'b.created_at') as [$sid, $amt, $n]) {
        if (isset($rows[$sid])) { $rows[$sid]['revenue'] = floatval($amt); $rows[$sid]['bookings'] = intval($n); }
    }
    foreach ($grab("SELECT pi.site_id, COALESCE(SUM(pi.cost), 0) FROM po_items pi JOIN purchase_orders po ON po.id = pi.po_id JOIN partners v ON v.id = po.vendor_id WHERE 1=1", 'po.created_at') as [$sid, $amt]) {
        if (isset($rows[$sid])) $rows[$sid]['po_cost'] = floatval($amt);
    }
    foreach ($grab("SELECT vpr.site_id, COALESCE(SUM(vpr.rate_per_sqft), 0) FROM vendor_printing_rates vpr JOIN partners p ON p.id = vpr.vendor_id WHERE 1=1", 'vpr.created_at') as [$sid, $rate]) {
        if (isset($rows[$sid])) $rows[$sid]['printing_cost'] = floatval($rate) * floatval($rows[$sid]['width']) * floatval($rows[$sid]['height']);
    }
    foreach (landlordRentForPeriod($pdo, $from ?: null, $to ?: null)['rows'] as $r) {
        $sid = $r['site_id'];
        if (isset($rows[$sid]) && $rows[$sid]['owner_type'] === 'HA') $rows[$sid]['rent'] += floatval($r['share']);
    }
    try {
        include_once __DIR__ . '/mounting_functions.php';
        foreach (getMountingCostBySite($pdo, $from ?: null, $to ?: null) as $sid => $amt) {
            if (isset($rows[$sid])) $rows[$sid]['mounting_cost'] = floatval($amt);
        }
    } catch (PDOException $e) {
        // Flex Mounting tables not migrated on this database
    }

    $out = [];
    $q = mb_strtolower(trim((string)$q));
    foreach ($rows as $r) {
        if ($ownerType && $r['owner_type'] !== $ownerType) continue;
        if ($q !== '' && strpos(mb_strtolower($r['site_code'] . ' ' . $r['name'] . ' ' . $r['location'] . ' ' . $r['city']), $q) === false) continue;
        $r['cost'] = round($r['po_cost'] + $r['printing_cost'] + $r['rent'] + $r['mounting_cost'], 2);
        $r['revenue'] = round($r['revenue'], 2);
        $r['profit'] = round($r['revenue'] - $r['cost'], 2);
        $r['margin'] = $r['revenue'] > 0 ? round($r['profit'] / $r['revenue'] * 100, 1) : null;
        $r['active'] = ($r['revenue'] > 0 || $r['cost'] > 0);
        $out[] = $r;
    }
    // Highest contribution first; ties by revenue, then code
    usort($out, fn($a, $b) => ($b['profit'] <=> $a['profit']) ?: ($b['revenue'] <=> $a['revenue']) ?: strcmp($a['site_code'], $b['site_code']));
    return $out;
}

/** Top N sites of one ownership type (HA = own, TA = traded) that had any revenue or cost in the range. */
function getTopSites(PDO $pdo, $ownerType, $limit = 10, $from = null, $to = null) {
    $rows = array_values(array_filter(getSiteContributions($pdo, $from, $to, $ownerType), fn($r) => $r['active']));
    return array_slice($rows, 0, $limit);
}

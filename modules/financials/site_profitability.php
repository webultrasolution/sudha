<?php
$activePage = 'site_profitability';
include_once __DIR__ . '/../../config/db.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/site_profit_functions.php';

requirePermission('financials', 'view');

// Period: financial year (default the active FY), or a custom range
$fy = getFinancialYear();
[$fyA, $fyB] = array_map('intval', explode('-', $fy));
$fyFrom = sprintf('20%02d-04-01', $fyA);
$fyTo = sprintf('20%02d-03-31', $fyB);
$validDate = fn($d) => ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) ? $d : null;
$period = $_GET['period'] ?? 'fy';
$from = $fyFrom; $to = $fyTo;
if ($period === 'all') { $from = $to = null; }
elseif ($period === 'custom') { $from = $validDate($_GET['from'] ?? '') ?: $fyFrom; $to = $validDate($_GET['to'] ?? '') ?: $fyTo; }
elseif ($period === 'this_month') { $from = date('Y-m-01'); $to = date('Y-m-t'); }
elseif ($period === 'last_month') { $from = date('Y-m-01', strtotime('first day of last month')); $to = date('Y-m-t', strtotime('last day of last month')); }
else { $period = 'fy'; }
$owner = in_array($_GET['owner'] ?? '', ['HA', 'TA'], true) ? $_GET['owner'] : '';
$q = trim($_GET['q'] ?? '');
$showIdle = !empty($_GET['idle']);

$rows = getSiteContributions($pdo, $from, $to, $owner ?: null, $q);
if (!$showIdle) $rows = array_values(array_filter($rows, fn($r) => $r['active']));

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="site_profitability_' . ($from ?: 'all') . '_' . ($to ?: 'all') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Rank', 'Site Code', 'Site', 'Type', 'Location', 'City', 'Bookings', 'Revenue', 'Vendor PO', 'Printing', 'Landlord Rent', 'Flex Mounting', 'Total Cost', 'Contribution', 'Margin %']);
    foreach ($rows as $i => $r) {
        fputcsv($o, [$i + 1, $r['site_code'], $r['name'], $r['owner_type'] === 'HA' ? 'Own (HA)' : 'Traded (TA)', $r['location'], $r['city'], $r['bookings'],
            $r['revenue'], round($r['po_cost'], 2), round($r['printing_cost'], 2), round($r['rent'], 2), round($r['mounting_cost'], 2), $r['cost'], $r['profit'], $r['margin']]);
    }
    exit;
}

$pageTitle = 'Site Profitability';
include_once __DIR__ . '/../../includes/header.php';

$tot = ['revenue' => 0, 'cost' => 0, 'profit' => 0];
foreach ($rows as $r) { $tot['revenue'] += $r['revenue']; $tot['cost'] += $r['cost']; $tot['profit'] += $r['profit']; }
$maxAbs = max(1, max(array_map(fn($r) => abs($r['profit']), $rows ?: [['profit' => 1]])));
$inr = fn($v) => '₹' . number_format($v, 2);
$qs = fn($extra = []) => '?' . http_build_query(array_merge(['period' => $period, 'from' => $from, 'to' => $to, 'owner' => $owner, 'q' => $q, 'idle' => $showIdle ? 1 : ''], $extra));
?>
<style>
.sp-wrap { padding: 15px; }
.sp-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 14px; }
.sp-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; }
.sp-card .lbl { font-size: .68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
.sp-card .val { font-size: 1.3rem; font-weight: 800; color: #0f172a; margin-top: 4px; font-variant-numeric: tabular-nums; }
.sp-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; margin-bottom: 14px; }
.sp-bar input, .sp-bar select { padding: .45rem .6rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: .82rem; background: #f8fafc; }
.sp-seg { display: inline-flex; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; }
.sp-seg a { padding: .42rem .8rem; font-size: .78rem; font-weight: 700; color: #334155; text-decoration: none; background: #fff; }
.sp-seg a.on { background: #0f766e; color: #fff; }
.sp-btn { border: none; border-radius: 8px; padding: .45rem .85rem; font-weight: 700; font-size: .78rem; cursor: pointer; background: #0f766e; color: #fff; text-decoration: none; display: inline-flex; gap: 6px; align-items: center; }
.sp-btn.ghost { background: #f1f5f9; color: #334155; }
.sp-table-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow-x: auto; }
.sp-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.sp-table th { text-align: left; font-size: .66rem; text-transform: uppercase; color: #64748b; font-weight: 800; padding: .65rem .6rem; border-bottom: 1px solid #e2e8f0; white-space: nowrap; position: sticky; top: 0; background: #fff; }
.sp-table td { padding: .55rem .6rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.sp-table td.num, .sp-table th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.sp-rank { display: inline-flex; width: 28px; height: 28px; border-radius: 50%; align-items: center; justify-content: center; font-weight: 800; font-size: .78rem; background: #f1f5f9; color: #334155; }
.sp-rank.r1 { background: #fef3c7; color: #92400e; } .sp-rank.r2 { background: #e5e7eb; color: #374151; } .sp-rank.r3 { background: #fde2cf; color: #9a3412; }
.sp-pill { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .66rem; font-weight: 800; }
.sp-pill.HA { background: #dcfce7; color: #166534; } .sp-pill.TA { background: #dbeafe; color: #1e40af; }
.sp-track { width: 120px; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }
.sp-fill { height: 100%; border-radius: 4px; }
</style>

<div class="sp-wrap">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:10px;margin-bottom:12px">
        <div>
            <h2 style="margin:0;font-size:1.25rem;font-weight:800;color:#0f172a">Site Profitability</h2>
            <div style="font-size:.78rem;color:#64748b">Sites ranked by contribution (revenue minus vendor PO, printing, landlord rent and flex mounting cost), highest first. Same rules as each site's P&amp;L.</div>
        </div>
        <a class="sp-btn ghost" href="<?php echo htmlspecialchars($qs(['export' => 'csv'])); ?>"><i class="fas fa-file-csv"></i> Export CSV</a>
    </div>

    <form class="sp-bar" method="get">
        <div class="sp-seg">
            <?php foreach (['' => 'All sites', 'HA' => 'Own (HA)', 'TA' => 'Traded (TA)'] as $k => $lbl): ?>
                <a class="<?php echo $owner === $k ? 'on' : ''; ?>" href="<?php echo htmlspecialchars($qs(['owner' => $k])); ?>"><?php echo $lbl; ?></a>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="owner" value="<?php echo htmlspecialchars($owner); ?>">
        <select name="period" id="spPeriod" onchange="document.getElementById('spCustom').style.display = this.value === 'custom' ? 'inline-flex' : 'none'">
            <?php foreach (['fy' => 'FY ' . $fy, 'this_month' => 'This month', 'last_month' => 'Last month', 'custom' => 'Custom range', 'all' => 'All time'] as $k => $lbl): ?>
                <option value="<?php echo $k; ?>" <?php echo $period === $k ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
            <?php endforeach; ?>
        </select>
        <span id="spCustom" style="display:<?php echo $period === 'custom' ? 'inline-flex' : 'none'; ?>;gap:6px;align-items:center">
            <input type="date" name="from" value="<?php echo htmlspecialchars($from ?? ''); ?>"> <span style="color:#94a3b8">to</span> <input type="date" name="to" value="<?php echo htmlspecialchars($to ?? ''); ?>">
        </span>
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Site code, name, city">
        <label style="font-size:.78rem;color:#475569;display:inline-flex;gap:5px;align-items:center"><input type="checkbox" name="idle" value="1" <?php echo $showIdle ? 'checked' : ''; ?>> Show sites with no activity</label>
        <button class="sp-btn" type="submit"><i class="fas fa-search"></i> Apply</button>
        <a class="sp-btn ghost" href="?">Reset</a>
    </form>

    <div class="sp-cards">
        <div class="sp-card"><div class="lbl">Sites ranked</div><div class="val"><?php echo count($rows); ?></div></div>
        <div class="sp-card"><div class="lbl">Revenue</div><div class="val"><?php echo $inr($tot['revenue']); ?></div></div>
        <div class="sp-card"><div class="lbl">Cost</div><div class="val" style="color:#dc2626"><?php echo $inr($tot['cost']); ?></div></div>
        <div class="sp-card"><div class="lbl">Contribution</div><div class="val" style="color:<?php echo $tot['profit'] >= 0 ? '#15803d' : '#dc2626'; ?>"><?php echo $inr($tot['profit']); ?></div></div>
        <div class="sp-card"><div class="lbl">Margin</div><div class="val"><?php echo $tot['revenue'] > 0 ? number_format($tot['profit'] / $tot['revenue'] * 100, 1) . '%' : '-'; ?></div></div>
    </div>

    <div style="font-size:.75rem;color:#64748b;margin-bottom:8px"><?php echo $from ? date('d M Y', strtotime($from)) . ' to ' . date('d M Y', strtotime($to)) : 'All time'; ?><?php echo $owner ? ' · ' . ($owner === 'HA' ? 'Own (HA) sites' : 'Traded (TA) sites') : ' · HA and TA'; ?></div>

    <div class="sp-table-wrap">
        <table class="sp-table">
            <thead><tr>
                <th>#</th><th>Site</th><th>Type</th><th class="num">Bookings</th><th class="num">Revenue</th>
                <th class="num">Vendor PO</th><th class="num">Printing</th><th class="num">Rent</th><th class="num">Flex Mounting</th>
                <th class="num">Total Cost</th><th class="num">Contribution</th><th class="num">Margin</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): $rank = $i + 1; $pos = $r['profit'] >= 0; ?>
                <tr>
                    <td><span class="sp-rank <?php echo $rank <= 3 && $pos ? 'r' . $rank : ''; ?>"><?php echo $rank; ?></span></td>
                    <td><a href="../inventory/site_financials.php?id=<?php echo (int)$r['id']; ?><?php echo $from ? '&from_date=' . urlencode($from) . '&to_date=' . urlencode($to) : ''; ?>" style="font-weight:700;color:#0f766e;text-decoration:none"><?php echo htmlspecialchars($r['site_code']); ?></a>
                        <?php echo htmlspecialchars($r['name']); ?>
                        <div style="font-size:.7rem;color:#64748b"><?php echo htmlspecialchars(trim(($r['location'] ?? '') . ($r['city'] ? ', ' . $r['city'] : ''), ', ')); ?></div></td>
                    <td><span class="sp-pill <?php echo htmlspecialchars($r['owner_type']); ?>"><?php echo $r['owner_type'] === 'HA' ? 'Own' : 'Traded'; ?></span></td>
                    <td class="num"><?php echo (int)$r['bookings']; ?></td>
                    <td class="num"><?php echo $inr($r['revenue']); ?></td>
                    <td class="num"><?php echo $r['po_cost'] ? $inr($r['po_cost']) : '-'; ?></td>
                    <td class="num"><?php echo $r['printing_cost'] ? $inr($r['printing_cost']) : '-'; ?></td>
                    <td class="num"><?php echo $r['rent'] ? $inr($r['rent']) : '-'; ?></td>
                    <td class="num"><?php echo $r['mounting_cost'] ? $inr($r['mounting_cost']) : '-'; ?></td>
                    <td class="num" style="color:#dc2626"><?php echo $inr($r['cost']); ?></td>
                    <td class="num" style="font-weight:800;color:<?php echo $pos ? '#15803d' : '#dc2626'; ?>"><?php echo $inr($r['profit']); ?></td>
                    <td class="num"><?php echo $r['margin'] === null ? '-' : $r['margin'] . '%'; ?></td>
                    <td><div class="sp-track" title="Share of the largest contribution"><div class="sp-fill" style="width:<?php echo round(abs($r['profit']) / $maxAbs * 100); ?>%;background:<?php echo $pos ? '#10b981' : '#ef4444'; ?>"></div></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="13" style="text-align:center;padding:2rem;color:#94a3b8">No site had revenue or cost in this period.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

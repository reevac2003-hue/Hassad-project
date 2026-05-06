<?php
/**
 * ملاحظات توثيقية للصفحة: manager-dashboard.php
 * الغرض: لوحة المدير لمتابعة الصناديق والعمليات والطلبات.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - all_participation_approvals(): جلب طلبات الموافقة على المشاركة. القيمة الراجعة: array.
 * - all_students(): جلب قائمة الطلاب. القيمة الراجعة: array.
 * - all_transactions(): جلب جميع العمليات المالية للنظام. القيمة الراجعة: array.
 * - all_withdrawal_requests(): جلب طلبات السحب على مستوى النظام/المدير. القيمة الراجعة: array.
 * - current_manager(): جلب بيانات المدير الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - manager_funds(): جلب صناديق مدير محدد فقط. القيمة الراجعة: array.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_footer(): إظهار تذييل الصفحة عند الحاجة. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - repo_fetch_all(): تنفيذ استعلام DB وإرجاع عدة صفوف. القيمة الراجعة: array.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 * - subscribers_for_fund(): جلب المشتركين في صندوق معين. القيمة الراجعة: array.
 */
if (!is_logged_in_as_manager()) {
    set_flash('error', 'Access denied. Manager login required.');
    redirect_to('login', ['role' => 'manager']);
}

$manager   = current_manager();
$managerId = (string) ($manager['FundManagerNumberofLicense'] ?? $manager['FundManagerID'] ?? '');
$mgrName   = trim(($manager['FundManagerNameFirst'] ?? '') . ' ' . ($manager['FundManagerNameLast'] ?? ''));
$managerNumericId = (int) ($manager['FundManagerNumberofLicense'] ?? $manager['FundManagerID'] ?? 0);

$funds = array_values(manager_funds($managerNumericId));
$fundIdMap = [];
foreach ($funds as $f) {
    $fundIdMap[(int) ($f['FundID'] ?? 0)] = true;
}

$transactions = array_values(array_filter(
    all_transactions(),
    fn($t) => isset($fundIdMap[(int) ($t['FundID'] ?? 0)])
));

$withdrawals = array_values(array_filter(
    all_withdrawal_requests(),
    fn($w) => ($fid = (int) ($w['FundID'] ?? 0)) === 0 || isset($fundIdMap[$fid])
));
$pendingW     = array_values(array_filter($withdrawals, fn($w) => strtolower((string)($w['Status'] ?? '')) === 'pending'));
$approvals    = array_values(array_filter(
    all_participation_approvals(),
    fn($a) => isset($fundIdMap[(int) ($a['FundID'] ?? 0)])
));
$pendingA     = array_values(array_filter($approvals, fn($a) => strtolower((string)($a['ApprovalStatus'] ?? '')) === 'pending'));

$dbNotifications = repo_fetch_all('SELECT * FROM Notification ORDER BY NotificationID DESC LIMIT 100');
$rawNotifications = $dbNotifications;

$studentsById = [];
foreach (all_students() as $studentRow) {
    $studentsById[(int) ($studentRow['StudentID'] ?? 0)] = $studentRow;
}

$managerFundNotifications = array_values(array_filter($rawNotifications, function ($n) use ($fundIdMap) {
    $fid = (int) ($n['FundID'] ?? 0);
    if ($fid <= 0 || !isset($fundIdMap[$fid])) {
        return false;
    }
    $text = strtolower((string) ($n['NotificationDescription'] ?? ''));
    return str_contains($text, 'new fund created') || str_contains($text, 'draft');
}));

usort($managerFundNotifications, static function (array $a, array $b): int {
    return (int) ($b['NotificationID'] ?? 0) <=> (int) ($a['NotificationID'] ?? 0);
});

$recentManagerFundNotifications = array_slice($managerFundNotifications, 0, 4);

/* Totals */
$totalFunds      = count($funds);
$publishedFunds  = count(array_filter($funds, fn($f) => strtolower((string)($f['FundAccountStatus'] ?? '')) === 'published'));
$totalSubscribers= array_sum(array_map(fn($f) => (int)($f['CurrentSubscribers'] ?? 0), $funds));

/* Build a fund lookup map: FundID → ExpectedReturnPercentage */
$fundReturnMap = [];
foreach ($funds as $f) {
    $fid = (int)($f['FundID'] ?? 0);
    $fundReturnMap[$fid] = (float)($f['ExpectedReturnPercentage'] ?? 0) / 100;
}

/* AUM and Profit computed from real Investment transactions (not TotalFundAmount) */
$totalAUM    = 0.0;
$totalProfit = 0.0;
foreach ($transactions as $t) {
    if ((string)($t['TransactionType'] ?? '') !== 'Investment') continue;
    $capital = (float)($t['FundCapital'] ?? 0);
    $fid     = (int)($t['FundID'] ?? 0);
    $retPct  = $fundReturnMap[$fid] ?? 0.0;
    $totalAUM    += $capital;
    $totalProfit += $capital * $retPct;
}
$managerCommission = $totalProfit * 0.04;

/* Monthly profit / AUM from investment transactions */
$monthlyData = [];
foreach ($transactions as $t) {
    if ((string)($t['TransactionType'] ?? '') !== 'Investment') continue;
    $dateRaw = $t['TransactionDate'] ?? '';
    if (empty($dateRaw)) continue;
    $monthKey = date('Y-m', strtotime((string)$dateRaw));
    $capital  = (float)($t['FundCapital'] ?? 0);
    $fid      = (int)($t['FundID'] ?? 0);
    $retPct   = $fundReturnMap[$fid] ?? 0.0;
    if (!isset($monthlyData[$monthKey])) {
        $monthlyData[$monthKey] = ['profit' => 0.0, 'aum' => 0.0];
    }
    $monthlyData[$monthKey]['profit'] += $capital * $retPct;
    $monthlyData[$monthKey]['aum']    += $capital;
}
krsort($monthlyData);
/* Last 12 months list for the dropdown */
$monthOptions = [];
for ($i = 0; $i < 12; $i++) {
    $key   = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $monthOptions[$key] = $label;
}

$expectedReturns = array_map(fn($f) => (float) ($f['ExpectedReturnPercentage'] ?? 0), $funds);
$avgExpectedReturn = !empty($expectedReturns) ? array_sum($expectedReturns) / count($expectedReturns) : 0.0;
$minExpectedReturn = !empty($expectedReturns) ? min($expectedReturns) : 0.0;
$maxExpectedReturn = !empty($expectedReturns) ? max($expectedReturns) : 0.0;

$holdingPeriods = array_map(fn($f) => (int) ($f['HoldingPeriod'] ?? 0), $funds);
$minHoldingPeriod = !empty($holdingPeriods) ? min($holdingPeriods) : 0;
$maxHoldingPeriod = !empty($holdingPeriods) ? max($holdingPeriods) : 0;

$currentFunds = count(array_filter($funds, function ($f) {
    $status = strtolower((string) ($f['FundAccountStatus'] ?? ''));
    return in_array($status, ['published', 'draft', 'active', 'pendingapproval'], true);
}));
$previousFunds = max(0, $totalFunds - $currentFunds);

$dates = array_values(array_filter(array_map(
    fn($t) => !empty($t['TransactionDate']) ? strtotime((string) $t['TransactionDate']) : null,
    $transactions
)));
$periodFrom = !empty($dates) ? date('M j, Y', (int) min($dates)) : 'N/A';
$periodTo   = !empty($dates) ? date('M j, Y', (int) max($dates)) : 'N/A';

$activeInvestorMap = [];
foreach ($funds as $fund) {
    foreach (subscribers_for_fund((int) ($fund['FundID'] ?? 0)) as $sub) {
        $sid = (int) ($sub['StudentID'] ?? $sub['student']['StudentID'] ?? 0);
        $first = (string) ($sub['student']['StudentNameFirst'] ?? '');
        $last  = (string) ($sub['student']['StudentNameLast'] ?? '');
        $name = trim($first . ' ' . $last);
        if ($sid > 0 && $name !== '') {
            $activeInvestorMap[$sid] = $name;
        }
    }
}
$activeInvestorNames = array_values($activeInvestorMap);
$activeInvestorCount = count($activeInvestorNames);

$riskMix = ['low' => 0, 'medium' => 0, 'high' => 0];
$riskWeights = ['low' => 1, 'medium' => 2, 'high' => 3];
$riskWeightedSum = 0.0;
$riskWeightedBase = 0.0;
foreach ($funds as $f) {
    $risk = strtolower((string) ($f['RiskLevel'] ?? 'medium'));
    if (!isset($riskMix[$risk])) {
        $risk = 'medium';
    }
    $riskMix[$risk]++;
    $fundWeight = (float) ($f['TotalFundAmount'] ?? 0);
    if ($fundWeight <= 0) {
        $fundWeight = 1;
    }
    $riskWeightedBase += $fundWeight;
    $riskWeightedSum += $riskWeights[$risk] * $fundWeight;
}
$riskScore = $riskWeightedBase > 0 ? $riskWeightedSum / $riskWeightedBase : 2.0;
$dominantRisk = 'medium';
if (!empty($riskMix)) {
    $maxRiskCount = max($riskMix);
    foreach ($riskMix as $level => $count) {
        if ($count === $maxRiskCount) {
            $dominantRisk = $level;
            break;
        }
    }
}
$riskAdvice = 'Portfolio balance is healthy. Keep current diversification and monitor monthly.';
if ($riskScore >= 2.4) {
    $riskAdvice = 'High risk concentration detected. Add 1-2 medium/low risk funds to reduce drawdown impact.';
} elseif ($riskScore <= 1.6) {
    $riskAdvice = 'Conservative profile detected. Add selective medium risk exposure to improve expected yield.';
}

/* Per-fund investment aggregate */
$fundInvested = [];
foreach ($transactions as $t) {
    if ((string)($t['TransactionType'] ?? '') !== 'Investment') continue;
    $fid = (int)($t['FundID'] ?? 0);
    $fundInvested[$fid] = ($fundInvested[$fid] ?? 0.0) + (float)($t['FundCapital'] ?? 0);
}

/* Bar chart max */
$chartFunds  = array_slice($funds, 0, 5);
$maxFundAUM  = max(1, ...array_map(fn($f) => (float)($f['TotalFundAmount'] ?? 0), array_merge($chartFunds, [['TotalFundAmount' => 1]])));

/* Recent activities */
$recentTx = array_slice(array_reverse($transactions), 0, 5);

render_head('Manager Dashboard');
render_responsive_shell_start('');
?>

<div class="md-page">

        <!-- Welcome banner -->
    <section class="md-welcome">
        <div class="md-welcome-avatar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="12" cy="7" r="4"/>
                <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>
            </svg>
        </div>
        <div class="md-welcome-copy">
            <h1>WELCOME BACK!</h1>
            <p><?= e($mgrName !== '' ? $mgrName . ' - ' : '') ?>Here's an overview of your funds and investment performance.</p>
        </div>
        <div class="md-welcome-right">
            <div class="md-bell-wrap">
                <svg class="md-bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <?php if (count($managerFundNotifications) > 0): ?>
                <span class="md-bell-badge"><?= count($managerFundNotifications) ?></span>
                <?php endif; ?>
            </div>
            <span class="md-id-chip">License: <?= e($managerId) ?></span>
        </div>
    </section>

        <!-- Month filter dropdown -->
    <div class="md-period-bar">
        <span class="md-period-label">Filter Period:</span>
        <div class="md-period-select-wrap">
            <select id="mdPeriodSelect" class="md-period-select" onchange="onPeriodSelect(this.value)">
                <option value="all">All Time</option>
                <?php foreach ($monthOptions as $mKey => $mLabel): ?>
                <option value="<?= e($mKey) ?>"><?= e($mLabel) ?></option>
                <?php endforeach; ?>
                <option value="custom">Custom Range…</option>
            </select>
        </div>
        <!-- Custom range inputs (hidden by default) -->
        <div class="md-custom-range" id="mdCustomRange" style="display:none">
            <label class="md-range-label">From</label>
            <input type="date" id="mdRangeFrom" class="md-range-input" onchange="applyCustomRange()">
            <label class="md-range-label">To</label>
            <input type="date" id="mdRangeTo"   class="md-range-input" onchange="applyCustomRange()">
        </div>
        <span class="md-period-badge" id="mdPeriodBadge">All Time</span>
    </div>

        <!-- Stats cards -->
    <section class="md-stats-grid">
        <article class="md-stat-card">
            <div class="md-stat-icon-wrap md-icon-teal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <span class="md-stat-title">Total Profit</span>
            <strong class="md-stat-value" id="mdCardProfit"><?= number_format($totalProfit, 0, '.', ',') ?> <small>SAR</small></strong>
            <span class="md-stat-trend up" id="mdCardProfitSub">All time</span>
        </article>

        <article class="md-stat-card">
            <div class="md-stat-icon-wrap md-icon-green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                    <path d="M16 7V5a2 2 0 0 0-4 0v2"/>
                    <line x1="12" y1="12" x2="12" y2="16"/>
                </svg>
            </div>
            <span class="md-stat-title">Total AUM</span>
            <strong class="md-stat-value" id="mdCardAUM"><?= number_format($totalAUM, 0, '.', ',') ?> <small>SAR</small></strong>
            <span class="md-stat-trend up" id="mdCardAUMSub">Assets under management</span>
        </article>

        <article class="md-stat-card">
            <div class="md-stat-icon-wrap" style="background:rgba(180,140,60,.15)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#b48c3c" stroke-width="1.8">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 6v6l4 2"/>
                </svg>
            </div>
            <span class="md-stat-title">Manager Commission (4%)</span>
            <strong class="md-stat-value" id="mdCardCommission" style="color:#b48c3c"><?= number_format($managerCommission, 0, '.', ',') ?> <small>SAR</small></strong>
            <span class="md-stat-trend up" id="mdCardCommissionSub">4% of total profit</span>
        </article>

        <article class="md-stat-card">
            <div class="md-stat-icon-wrap md-icon-olive">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <span class="md-stat-title">Active Investors (Now)</span>
            <strong class="md-stat-value"><?= number_format($activeInvestorCount, 0) ?></strong>
            <span class="md-stat-trend up">Current investor count across your funds</span>
        </article>

        <article class="md-stat-card">
            <div class="md-stat-icon-wrap md-icon-dark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
            </div>
            <span class="md-stat-title">Current / Previous Funds</span>
            <strong class="md-stat-value"><?= $currentFunds ?> <small>/ <?= $previousFunds ?></small></strong>
            <span class="md-stat-trend up">Current status split</span>
        </article>

        <article class="md-stat-card">
            <div class="md-stat-icon-wrap md-icon-gold">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <span class="md-stat-title">Pending Requests</span>
            <strong class="md-stat-value"><?= count($pendingA) + count($pendingW) ?></strong>
            <span class="md-stat-trend up"><?= count($pendingA) ?> approvals and <?= count($pendingW) ?> withdrawals</span>
        </article>
    </section>

        <!-- Fund overview -->
    <section class="md-panel md-panel-dark">
        <div class="md-panel-head">
            <h2>Fund Overview</h2>
            <div class="md-panel-head-right">
                <button type="button" class="md-nav-btn md-nav-pill is-active" onclick="mdFilterStatus('all')" title="All">All</button>
                <button type="button" class="md-nav-btn md-nav-pill" onclick="mdFilterStatus('published')" title="Published">Published</button>
                <button type="button" class="md-nav-btn md-nav-pill" onclick="mdFilterStatus('draft')" title="Draft">Draft</button>
                <a href="<?= e(route('create-fund')) ?>">+ New Fund</a>
                <button class="md-nav-btn md-nav-arrow" onclick="mdSlide(-1)">&#8249;</button>
                <button class="md-nav-btn md-nav-arrow" onclick="mdSlide(1)">&#8250;</button>
            </div>
        </div>
        <div class="md-fund-carousel" id="mdFundCarousel">
            <?php foreach ($funds as $fund):
                $fid         = (int)($fund['FundID'] ?? 0);
                $status      = strtolower((string)($fund['FundAccountStatus'] ?? 'draft'));
                $isPublished      = $status === 'published';
                $isPendingApproval = $status === 'pendingapproval';
                $subs        = (int)($fund['CurrentSubscribers'] ?? 0);
                $maxSubs     = (int)($fund['MaxSubscribers'] ?? 1);
                $subsPct     = $maxSubs > 0 ? min(100, ($subs / $maxSubs) * 100) : 0;
                $est         = (float)($fund['ExpectedReturnPercentage'] ?? 0);
                $pillClass   = $isPublished ? 'md-pill-done' : ($isPendingApproval ? 'md-pill-amber' : 'md-pill-pending');
                $pillLabel   = $isPublished ? 'Published' : ($isPendingApproval ? 'Pending Approval' : 'Draft');
            ?>
            <article class="md-fund-card" data-fund-status="<?= e($status) ?>">
                <div class="md-fund-top">
                    <div class="md-fund-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M3 3v18h18"/><polyline points="18 9 13 14 9 10 3 16"/>
                        </svg>
                    </div>
                    <div class="md-fund-head">
                        <h3><?= e($fund['FundTitle']) ?></h3>
                        <span class="md-pill <?= $pillClass ?>"><?= $pillLabel ?></span>
                    </div>
                </div>
                <p class="md-fund-duration">Holding Window: <?= e((int)($fund['HoldingPeriod'] ?? 0)) ?> Months</p>
                <div class="md-fund-metrics">
                    <div>
                        <span>Expected Return</span>
                        <strong><?= number_format($est, 1) ?>%</strong>
                    </div>
                    <div>
                        <span>Subscribers</span>
                        <strong><?= $subs ?> / <?= $maxSubs ?></strong>
                    </div>
                </div>
                <div class="md-progress">
                    <span style="width:<?= number_format($subsPct, 1) ?>%"></span>
                </div>
                <div class="md-progress-meta">
                    <small>Filled <b><?= number_format($subsPct, 0) ?>%</b></small>
                    <small>Risk: <b><?= e(ucfirst($fund['RiskLevel'] ?? 'N/A')) ?></b></small>
                </div>
                <div class="md-fund-actions">
                    <a href="<?= e(route('fund', ['slug' => $fund['FundSlug'] ?? $fund['slug'] ?? ''])) ?>" class="md-fund-btn md-fund-btn-outline">View</a>
                    <a href="<?= e(route('edit-fund', ['id' => $fid])) ?>" class="md-fund-btn md-fund-btn-outline">Edit</a>
                    <?php if ($isPublished): ?>
                    <a href="<?= e(route('manager-reports')) ?>" class="md-fund-btn md-fund-btn-gold">Report</a>
                    <?php elseif ($isPendingApproval): ?>
                    <span class="md-fund-btn" style="opacity:.6;cursor:default;">Awaiting Admin</span>
                    <?php else: ?>
                    <a href="<?= e(route('publish-fund', ['slug' => $fund['FundSlug'] ?? $fund['slug'] ?? ''])) ?>" class="md-fund-btn md-fund-btn-gold">Publish</a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
            <?php if (empty($funds)): ?>
            <div class="md-empty-carousel">
                <p>No funds created yet.</p>
                <a href="<?= e(route('create-fund')) ?>" class="md-fund-btn md-fund-btn-gold">+ Create Fund</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="md-middle-grid">

        <!-- Withdrawal Requests -->
        <article class="md-panel">
            <div class="md-panel-head">
                <h2>Withdrawal Requests</h2>
                <div class="md-panel-head-right">
                    <span class="md-badge-count"><?= count($pendingW) ?> pending</span>
                    <button class="md-nav-btn-sm">&#8249;</button>
                    <button class="md-nav-btn-sm">&#8250;</button>
                </div>
            </div>
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $displayW = array_slice(array_reverse($withdrawals), 0, 6);
                        foreach ($displayW as $wr):
                            $wStatus     = (string)($wr['Status'] ?? 'Pending');
                            $wStudent    = $studentsById[(int)($wr['StudentID'] ?? 0)] ?? null;
                            $studentName = $wStudent
                                ? e($wStudent['StudentNameFirst'] . ' ' . $wStudent['StudentNameLast'])
                                : '#' . e((string)($wr['StudentID'] ?? '-'));
                        ?>
                        <tr>
                            <td><?= $studentName ?></td>
                            <td><?= number_format((float)($wr['Amount'] ?? 0), 0, '.', ',') ?> SAR</td>
                            <td><?= e((string)($wr['WithdrawalType'] ?? 'Profit')) ?></td>
                            <td><?= e(!empty($wr['RequestDate']) ? date('M j, Y', strtotime((string)$wr['RequestDate'])) : '-') ?></td>
                            <td>
                                <span class="md-badge md-badge-<?= strtolower($wStatus) ?>"><?= e($wStatus) ?></span>
                            </td>
                            <td>
                                <?php if ($wStatus === 'Pending'): ?>
                                <div class="md-action-pair">
                                    <form action="<?= e(action_url('process-withdrawal')) ?>" method="POST">
                                        <input type="hidden" name="withdrawal_id" value="<?= e((string)($wr['WithdrawalID'] ?? '')) ?>">
                                        <input type="hidden" name="status" value="Approved">
                                        <button type="submit" class="md-action-btn md-action-approve">Approve</button>
                                    </form>
                                    <form action="<?= e(action_url('process-withdrawal')) ?>" method="POST">
                                        <input type="hidden" name="withdrawal_id" value="<?= e((string)($wr['WithdrawalID'] ?? '')) ?>">
                                        <input type="hidden" name="status" value="Rejected">
                                        <button type="submit" class="md-action-btn md-action-reject" onclick="return confirm('Reject?')">Reject</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <span class="md-action-dash">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($withdrawals)): ?>
                        <tr><td colspan="6" class="md-empty-row">No withdrawal requests.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <!-- Notifications -->
        <article class="md-panel md-notif-panel">
            <div class="md-panel-head">
                <h2>
                    <svg class="md-notif-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    Notifications
                </h2>
                <a href="<?= e(route('send-notification')) ?>">View All</a>
            </div>
            <ul class="md-notif-list">
                <?php foreach ($recentManagerFundNotifications as $n): ?>
                <?php
                    $txt = (string) ($n['NotificationDescription'] ?? 'Fund update');
                    $isDraft = stripos($txt, 'draft') !== false;
                    $sentTs = strtotime((string) ($n['SentDate'] ?? '')) ?: time();
                ?>
                <li class="md-notif-item">
                    <span class="md-notif-dot <?= $isDraft ? 'dot-amber' : 'dot-green' ?>"></span>
                    <div class="md-notif-body">
                        <strong><?= $isDraft ? 'Fund Saved as Draft' : 'New Fund Created' ?></strong>
                        <p><?= e($txt) ?></p>
                        <time><?= e(date('M j, Y', $sentTs)) ?> | <?= e(date('g:i A', $sentTs)) ?></time>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php if (empty($recentManagerFundNotifications)): ?>
                <li class="md-notif-item">
                    <span class="md-notif-dot dot-green"></span>
                    <div class="md-notif-body">
                        <strong>No New Fund Alerts</strong>
                        <p>Any new fund creation or draft save will appear here.</p>
                        <time><?= e(date('M j, Y')) ?> | <?= e(date('g:i A')) ?></time>
                    </div>
                </li>
                <?php endif; ?>
            </ul>
        </article>
    </section>

    <section class="md-bottom-grid">

        <!-- Portfolio Summary -->
        <article class="md-panel md-summary">
            <div class="md-summary-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                    <path d="M16 7V5a2 2 0 0 0-4 0v2"/>
                </svg>
            </div>
            <h3>Portfolio Summary</h3>
            <div class="md-sum-line"><span>Total Funds</span><strong><?= $totalFunds ?></strong></div>
            <div class="md-sum-line"><span>Published</span><strong><?= $publishedFunds ?></strong></div>
            <div class="md-sum-line"><span>Total AUM</span><strong><?= number_format($totalAUM, 0, '.', ',') ?> SAR</strong></div>
            <div class="md-sum-line"><span>Total Investors</span><strong><?= number_format($totalSubscribers, 0) ?></strong></div>
            <div class="md-sum-line"><span>Holding Periods</span><strong><?= $minHoldingPeriod ?>-<?= $maxHoldingPeriod ?> Months</strong></div>
            <div class="md-sum-line"><span>Expected Returns</span><strong><?= number_format($minExpectedReturn, 1) ?>-<?= number_format($maxExpectedReturn, 1) ?>%</strong></div>
            <div class="md-sum-line md-sum-highlight"><span>Expected Profit</span><strong><?= number_format($totalProfit, 0, '.', ',') ?> SAR</strong></div>
        </article>

        <!-- Published vs Draft donut -->
        <?php
        $pubPct = $totalFunds > 0 ? ($publishedFunds / $totalFunds) * 100 : 0;
        ?>
        <article class="md-panel md-bottom-donut-card">
            <div class="md-bottom-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
            </div>
            <h3>Published vs Draft</h3>
            <div class="md-donut-legend-wrap">
                <div class="md-ring-wrap">
                    <div class="md-ring" style="--pct:<?= number_format($pubPct, 2, '.', '') ?>">
                        <span><?= number_format($pubPct, 1) ?>%<small>Published</small></span>
                    </div>
                </div>
                <div class="md-ring-legend">
                    <div class="md-ring-item">
                        <span class="md-ring-dot u-inline-76"></span>
                        <div><b>Published</b><br><small><?= $publishedFunds ?> funds</small></div>
                    </div>
                    <div class="md-ring-item">
                        <span class="md-ring-dot u-inline-77"></span>
                        <div><b>Draft</b><br><small><?= $totalFunds - $publishedFunds ?> funds</small></div>
                    </div>
                </div>
            </div>
        </article>

        <!-- AUM by Fund bars -->
        <article class="md-panel md-bottom-bars-card">
            <div class="md-bottom-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
            </div>
            <h3>AUM by Fund</h3>
            <div class="md-bars-chart">
                <?php foreach ($chartFunds as $cf):
                    $aum = (float)($cf['TotalFundAmount'] ?? 0);
                    $h   = max(10, (int)round(($aum / $maxFundAUM) * 100));
                    $lbl = substr((string)($cf['FundTitle'] ?? 'Fund'), 0, 10);
                ?>
                <div class="md-bar-group">
                    <div class="md-bar-set">
                        <div class="md-bar-item" style="height:<?= $h ?>px;background:#45644A"></div>
                    </div>
                    <span><?= e($lbl) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($chartFunds)): ?>
                <p class="u-inline-79">No fund data.</p>
                <?php endif; ?>
            </div>
        </article>

        <!-- Recent Activity -->
        <article class="md-panel md-activity-card">
            <div class="md-bottom-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
            </div>
            <h3>Recent Activity</h3>
            <ul class="md-activity-list">
                <?php foreach ($recentTx as $tx):
                    $txType = (string)($tx['TransactionType'] ?? 'Transaction');
                    $txAmt  = (float)($tx['FundCapital'] ?? $tx['Amount'] ?? 0);
                    $txDate = !empty($tx['TransactionDate']) ? date('M j, Y', strtotime((string)$tx['TransactionDate'])) : '-';
                    $dotClass = $txType === 'Investment' ? 'dot-green' : ($txType === 'Return' ? 'dot-amber' : 'dot-red');
                ?>
                <li class="md-notif-item">
                    <span class="md-notif-dot <?= $dotClass ?>"></span>
                    <div class="md-notif-body">
                        <strong><?= e($txType) ?></strong>
                        <p><?= number_format($txAmt, 0, '.', ',') ?> SAR - Fund #<?= e((string)($tx['FundID'] ?? '-')) ?></p>
                        <time><?= e($txDate) ?></time>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php if (empty($recentTx)): ?>
                <li class="md-empty-row u-inline-78">No recent activity.</li>
                <?php endif; ?>
            </ul>
        </article>
    </section>

    <section class="md-panel md-risk-helper">
        <div class="md-panel-head">
            <h2>Risk Level Assistant</h2>
        </div>
        <div class="md-risk-grid">
            <div class="md-risk-score">
                <span>Portfolio Risk Score</span>
                <strong><?= number_format($riskScore, 2) ?> / 3.00</strong>
                <small>Dominant level: <?= e(ucfirst($dominantRisk)) ?></small>
            </div>
            <div class="md-risk-mix">
                <div><b><?= (int) $riskMix['low'] ?></b><span>Low</span></div>
                <div><b><?= (int) $riskMix['medium'] ?></b><span>Medium</span></div>
                <div><b><?= (int) $riskMix['high'] ?></b><span>High</span></div>
            </div>
            <p class="md-risk-advice"><?= e($riskAdvice) ?></p>
        </div>
    </section>
</div>

<script>
(function(){
    var c = document.getElementById('mdFundCarousel');
    if (!c) return;
    var pos = 0;
    var cw  = function(){ var card = c.querySelector('.md-fund-card'); return card ? card.offsetWidth + 16 : 270; };
    window.mdSlide = function(dir) {
        var max = c.scrollWidth - c.clientWidth;
        pos = Math.max(0, Math.min(max, pos + dir * cw()));
        c.scrollTo({left: pos, behavior: 'smooth'});
    };

    window.mdFilterStatus = function(status) {
        var cards = c.querySelectorAll('.md-fund-card');
        cards.forEach(function(card){
            var matches = status === 'all' || card.getAttribute('data-fund-status') === status;
            card.style.display = matches ? '' : 'none';
        });
        pos = 0;
        c.scrollTo({left: 0, behavior: 'smooth'});
    };
})();

/* ── Period filter for Profit / AUM / Commission cards ── */
(function () {
    var totalProfit     = <?= json_encode($totalProfit) ?>;
    var totalAUM        = <?= json_encode($totalAUM) ?>;
    var totalCommission = <?= json_encode($managerCommission) ?>;
    var monthlyData     = <?= json_encode($monthlyData) ?>;
    /* flat list of all investment transactions with date + amounts for custom range */
    var allTx = <?= json_encode(array_values(array_map(function($t) use ($fundReturnMap) {
        $fid = (int)($t['FundID'] ?? 0);
        return [
            'date'    => (string)($t['TransactionDate'] ?? ''),
            'capital' => (float)($t['FundCapital'] ?? 0),
            'retPct'  => $fundReturnMap[$fid] ?? 0.0,
        ];
    }, array_filter($transactions, fn($t) => ($t['TransactionType'] ?? '') === 'Investment')))) ?>;

    function fmt(n) {
        return n.toLocaleString('en-US', {maximumFractionDigits: 0}) + ' <small>SAR</small>';
    }
    function setCards(profit, aum, label) {
        document.getElementById('mdCardProfit').innerHTML      = fmt(profit);
        document.getElementById('mdCardAUM').innerHTML         = fmt(aum);
        document.getElementById('mdCardCommission').innerHTML  = fmt(profit * 0.04);
        document.getElementById('mdCardProfitSub').textContent     = label;
        document.getElementById('mdCardAUMSub').textContent        = label;
        document.getElementById('mdCardCommissionSub').textContent = '4% of profit — ' + label;
        document.getElementById('mdPeriodBadge').textContent = label;
    }

    function applyMonth(month) {
        if (month === 'all') {
            setCards(totalProfit, totalAUM, 'All Time');
        } else {
            var d = monthlyData[month] || {profit: 0, aum: 0};
            var parts = month.split('-');
            var mNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            setCards(d.profit, d.aum, mNames[parseInt(parts[1],10)-1] + ' ' + parts[0]);
        }
    }

    window.applyCustomRange = function() {
        var from = document.getElementById('mdRangeFrom').value;
        var to   = document.getElementById('mdRangeTo').value;
        if (!from || !to) return;
        var fromTs = new Date(from).getTime();
        var toTs   = new Date(to).getTime() + 86400000 - 1; /* end of day */
        var profit = 0, aum = 0;
        allTx.forEach(function(t) {
            if (!t.date) return;
            var ts = new Date(t.date).getTime();
            if (ts >= fromTs && ts <= toTs) {
                aum    += t.capital;
                profit += t.capital * t.retPct;
            }
        });
        var label = from + ' → ' + to;
        setCards(profit, aum, label);
    };

    window.onPeriodSelect = function(val) {
        var customDiv = document.getElementById('mdCustomRange');
        if (val === 'custom') {
            customDiv.style.display = 'flex';
            /* set default range: first of current month → today */
            var today = new Date();
            var y = today.getFullYear(), m = String(today.getMonth()+1).padStart(2,'0'), d = String(today.getDate()).padStart(2,'0');
            document.getElementById('mdRangeFrom').value = y + '-' + m + '-01';
            document.getElementById('mdRangeTo').value   = y + '-' + m + '-' + d;
            applyCustomRange();
        } else {
            customDiv.style.display = 'none';
            applyMonth(val);
        }
    };
})();
</script>

<?php
render_footer();
render_responsive_shell_end();
render_end();
?>




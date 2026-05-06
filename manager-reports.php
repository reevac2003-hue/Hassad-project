<?php
/**
 * ملاحظات توثيقية للصفحة: manager-reports.php
 * الغرض: تقارير المدير التنفيذية والتحليلية.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - current_bank_account(): جلب البطاقة/الحساب البنكي للطالب. القيمة الراجعة: array.
 * - current_manager(): جلب بيانات المدير الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - customers_with_details(): جلب العملاء مع تفاصيلهم لمدير محدد. القيمة الراجعة: array.
 * - db_connection(): فتح اتصال PDO بقاعدة البيانات. القيمة الراجعة: ?PDO.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - fund_by_id(): جلب صندوق محدد عبر FundID. القيمة الراجعة: ?array.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - manager_funds(): جلب صناديق مدير محدد فقط. القيمة الراجعة: array.
 * - manager_transaction_details(): جلب تفاصيل عمليات المدير مع الفلاتر. القيمة الراجعة: array.
 * - mask_card(): إخفاء جزء من رقم البطاقة عند العرض. القيمة الراجعة: string.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - repo_fetch_all(): تنفيذ استعلام DB وإرجاع عدة صفوف. القيمة الراجعة: array.
 * - repo_fetch_one(): تنفيذ استعلام DB وإرجاع صف واحد. القيمة الراجعة: ?array.
 * - report_summary_stats(): جلب مؤشرات التقارير الإجمالية. القيمة الراجعة: array.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
/**
 * Manager Reports Page
 * --------------------------------
 * Shows manager reports about funds, investors and transactions
 */

// Check manager access
if (!is_logged_in_as_manager()) {
    set_flash('error', 'Access denied. Manager login required.');
    redirect_to('login', ['role' => 'manager']);
}

$manager = current_manager();
$licenseId = (int) $manager['FundManagerNumberofLicense'];
$pdo = db_connection();

if ($pdo) {
    $transactions = repo_fetch_all(
        'SELECT * FROM `Transaction` WHERE FundManagerNumberofLicense = ? ORDER BY TransactionID DESC',
        [$licenseId]
    );

    $funds = repo_fetch_all(
        'SELECT * FROM Fund WHERE FundManagerNumberofLicense = ? ORDER BY FundID DESC',
        [$licenseId]
    );

    $studentRows = repo_fetch_all(
        'SELECT DISTINCT s.*
         FROM Student s
         INNER JOIN `Transaction` t ON t.StudentID = s.StudentID
         WHERE t.FundManagerNumberofLicense = ?',
        [$licenseId]
    );

    $customers = [];
    foreach ($studentRows as $studentRow) {
        $sid = (int) ($studentRow['StudentID'] ?? 0);
        if ($sid <= 0) {
            continue;
        }

        $walletRow = repo_fetch_one('SELECT * FROM InvestmentWallet WHERE StudentID = ?', [$sid]);
        $contractsCount = (int) (repo_fetch_one(
            'SELECT COUNT(*) AS cnt FROM Contract WHERE StudentID = ?',
            [$sid]
        )['cnt'] ?? 0);

        $totalInvestedDb = (float) (repo_fetch_one(
            'SELECT COALESCE(SUM(FundCapital), 0) AS total
             FROM `Transaction`
             WHERE StudentID = ?
               AND FundManagerNumberofLicense = ?
               AND TransactionType = "Investment"',
            [$sid, $licenseId]
        )['total'] ?? 0);

        $customers[] = [
            'student' => $studentRow,
            'wallet' => $walletRow ?: [],
            'contracts_count' => $contractsCount,
            'total_invested' => $totalInvestedDb,
        ];
    }

    $stats = [
        'total_students' => (int) (repo_fetch_one('SELECT COUNT(*) AS cnt FROM Student')['cnt'] ?? 0),
        'total_managers' => (int) (repo_fetch_one('SELECT COUNT(*) AS cnt FROM FundManager')['cnt'] ?? 0),
        'total_funds' => (int) (repo_fetch_one('SELECT COUNT(*) AS cnt FROM Fund')['cnt'] ?? 0),
        'total_investments' => (float) (repo_fetch_one(
            'SELECT COALESCE(SUM(FundCapital), 0) AS total FROM `Transaction` WHERE TransactionType = "Investment"'
        )['total'] ?? 0),
        'total_returns' => (float) (repo_fetch_one(
            'SELECT COALESCE(SUM(InvestmentWalletReturn), 0) AS total FROM InvestmentWallet'
        )['total'] ?? 0),
        'total_transactions' => (int) (repo_fetch_one('SELECT COUNT(*) AS cnt FROM `Transaction`')['cnt'] ?? 0),
    ];
} else {
    $transactions = manager_transaction_details($licenseId);
    $funds = manager_funds($licenseId);
    $customers = customers_with_details();
    $stats = report_summary_stats();
}

$fetchBankAccount = static function (int $studentId) use ($pdo): ?array {
    if ($pdo) {
        return repo_fetch_one('SELECT * FROM BankAccount WHERE StudentID = ?', [$studentId]);
    }

    $bank = current_bank_account($studentId);
    return empty($bank['CreditCardNumber']) ? null : $bank;
};
$filterType = $_GET['type'] ?? 'all';
$sectionFilter = $_GET['section'] ?? 'all';
$executiveReport = $_GET['exec'] ?? 'all';

$allowedTypes = ['all', 'investment', 'withdrawal'];
if (!in_array($filterType, $allowedTypes, true)) {
    $filterType = 'all';
}

$allowedSections = ['executive', 'funds', 'investors', 'transactions', 'system'];
if (!in_array($sectionFilter, $allowedSections, true)) {
    $sectionFilter = 'executive';
}

$allowedExecutiveReports = ['all', 'performance', 'kpi', 'risk-management', 'funds-overview', 'liquidity-flows', 'fund-risks'];
if (!in_array($executiveReport, $allowedExecutiveReports, true)) {
    $executiveReport = 'all';
}

// ط·آ­ط·آ³ط·آ§ط·آ¨ ط·آ¥ط·آ­ط·آµط·آ§ط·آ¦ط¸ظ¹ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ¯ط¸ظ¹ط·آ± ط¸â€¦ط¸â€  ط·آ§ط¸â€‍ط·آ¨ط¸ظ¹ط·آ§ط¸â€ ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·ع¾ط¸ث†ط¸ظ¾ط·آ±ط·آ©
$totalCapital = array_sum(array_map(fn($t) => (float)($t['FundCapital'] ?? 0), array_filter($transactions, fn($t) => $t['TransactionType'] === 'Investment')));
$uniqueInvestors = count(array_unique(array_column($transactions, 'StudentID')));
$publishedFunds = count(array_filter($funds, fn($f) => strtolower($f['FundAccountStatus'] ?? '') === 'published'));
$totalFunds = count($funds);

$managerDetails = [
    'total_capital' => $totalCapital,
    'unique_investors' => $uniqueInvestors,
    'published_funds' => $publishedFunds,
    'total_funds' => $totalFunds,
];

$managerStatus = strtolower((string)($manager['FundManagerAccountStatus'] ?? ''));
$now = new DateTimeImmutable('now');

$semiAnnualRows = [];
for ($i = 0; $i < 3; $i++) {
    $periodEnd = $now->modify('-' . ($i * 6) . ' months');
    $periodStart = $periodEnd->modify('-6 months');

    $periodTransactions = array_filter($transactions, static function ($t) use ($periodStart, $periodEnd): bool {
        $dateRaw = $t['TransactionDate'] ?? null;
        if (!$dateRaw) {
            return false;
        }
        $txTimestamp = strtotime((string)$dateRaw);
        if ($txTimestamp === false) {
            return false;
        }
        $startTimestamp = strtotime($periodStart->format('Y-m-d'));
        $endTimestamp = strtotime($periodEnd->format('Y-m-d'));
        return $txTimestamp >= $startTimestamp && $txTimestamp <= $endTimestamp;
    });

    $periodInvested = array_sum(array_map(
        static fn($t) => $t['TransactionType'] === 'Investment' ? (float)($t['FundCapital'] ?? 0) : 0.0,
        $periodTransactions
    ));
    $periodWithdrawn = array_sum(array_map(
        static fn($t) => $t['TransactionType'] === 'FullWithdrawal' ? (float)($t['FundFullWithdrawalAmount'] ?? 0) : 0.0,
        $periodTransactions
    ));

    $netProfit = $periodWithdrawn - $periodInvested;
    $returnPct = $periodInvested > 0 ? ($netProfit / $periodInvested) * 100 : 0.0;
    $absReturn = abs($returnPct);

    if ($absReturn >= 12) {
        $riskLevel = 'High';
    } elseif ($absReturn >= 5) {
        $riskLevel = 'Medium';
    } else {
        $riskLevel = 'Low';
    }

    $semiAnnualRows[] = [
        'report_type' => 'Semi-Annual',
        'deals' => count($periodTransactions),
        'invested' => $periodInvested,
        'net_profit' => $netProfit,
        'return_pct' => $returnPct,
        'risk' => $riskLevel,
        'compliance' => $managerStatus === 'active' ? 'Completed' : 'Under Review',
        'ref_no' => 'MGR-' . $periodEnd->format('Y') . '-' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT),
    ];
}

$totalDealsSummary = array_sum(array_map(static fn($r) => $r['deals'], $semiAnnualRows));
$totalInvestedSummary = array_sum(array_map(static fn($r) => $r['invested'], $semiAnnualRows));
$totalNetSummary = array_sum(array_map(static fn($r) => $r['net_profit'], $semiAnnualRows));
$totalReturnSummary = $totalInvestedSummary > 0 ? ($totalNetSummary / $totalInvestedSummary) * 100 : 0.0;

if (abs($totalReturnSummary) >= 12) {
    $summaryRisk = 'High';
} elseif (abs($totalReturnSummary) >= 5) {
    $summaryRisk = 'Medium';
} else {
    $summaryRisk = 'Balanced';
}

$allCompliant = !in_array('Under Review', array_map(static fn($r) => $r['compliance'], $semiAnnualRows), true);

$returnSeries = array_map(static fn($r) => (float)$r['return_pct'], $semiAnnualRows);
$winningPeriods = count(array_filter($returnSeries, static fn($v) => $v > 0));
$winningRate = count($returnSeries) > 0 ? ($winningPeriods / count($returnSeries)) * 100 : 0.0;

$holdingPeriods = [];
$typeCounter = [];
$limitControlOk = true;
foreach ($funds as $fund) {
    $type = (string)($fund['InvestmentType'] ?? 'General');
    $typeCounter[$type] = ($typeCounter[$type] ?? 0) + 1;

    $holding = (float)($fund['HoldingPeriod'] ?? 0);
    if ($holding > 0) {
        $holdingPeriods[] = $holding;
    }

    $minAmount = (float)($fund['FundAmountMinimum'] ?? 0);
    $maxAmount = (float)($fund['FundAmountMaximum'] ?? 0);
    if ($minAmount <= 0 || $maxAmount < $minAmount) {
        $limitControlOk = false;
    }
}

$avgInvestmentDuration = count($holdingPeriods) > 0 ? array_sum($holdingPeriods) / count($holdingPeriods) : 0.0;
$topSector = 'N/A';
$lowestSector = 'N/A';
if (!empty($typeCounter)) {
    arsort($typeCounter);
    $topSector = (string)array_key_first($typeCounter);
    asort($typeCounter);
    $lowestSector = (string)array_key_first($typeCounter);
}

$diversificationScore = count($typeCounter);
if ($diversificationScore >= 3) {
    $diversificationEval = 'Excellent';
} elseif ($diversificationScore === 2) {
    $diversificationEval = 'Good';
} else {
    $diversificationEval = 'Moderate';
}

$limitsEval = $limitControlOk ? 'Excellent' : 'Needs Review';

$avgReturn = count($returnSeries) > 0 ? array_sum($returnSeries) / count($returnSeries) : 0.0;
$variance = 0.0;
foreach ($returnSeries as $value) {
    $variance += ($value - $avgReturn) ** 2;
}
$volatilityPct = count($returnSeries) > 0 ? sqrt($variance / count($returnSeries)) : 0.0;
if ($volatilityPct >= 10) {
    $volatilityEval = 'High';
} elseif ($volatilityPct >= 5) {
    $volatilityEval = 'Medium';
} else {
    $volatilityEval = 'Low';
}

$drawdownPct = 0.0;
foreach ($returnSeries as $value) {
    if ($value < $drawdownPct) {
        $drawdownPct = $value;
    }
}
$maxDrawdown = abs($drawdownPct);

$totalInvestedAll = array_sum(array_map(
    static fn($t) => $t['TransactionType'] === 'Investment' ? (float)($t['FundCapital'] ?? 0) : 0.0,
    $transactions
));
$totalWithdrawnAll = array_sum(array_map(
    static fn($t) => $t['TransactionType'] === 'FullWithdrawal' ? (float)($t['FundFullWithdrawalAmount'] ?? 0) : 0.0,
    $transactions
));
$liquidityRatio = $totalInvestedAll > 0 ? ($totalWithdrawnAll / $totalInvestedAll) * 100 : 0.0;
if ($liquidityRatio >= 55) {
    $liquidityEval = 'Excellent';
} elseif ($liquidityRatio >= 30) {
    $liquidityEval = 'Effective';
} else {
    $liquidityEval = 'Moderate';
}

$fundInvestorMap = [];
foreach ($transactions as $t) {
    $fId = (int)($t['FundID'] ?? 0);
    $sId = (int)($t['StudentID'] ?? 0);
    if ($fId <= 0 || $sId <= 0) {
        continue;
    }
    if (!isset($fundInvestorMap[$fId])) {
        $fundInvestorMap[$fId] = [];
    }
    $fundInvestorMap[$fId][$sId] = true;
}

$fundOverviewRows = [];
foreach ($funds as $fund) {
    $fundId = (int)($fund['FundID'] ?? 0);
    $size = (float)($fund['FundAmountMaximum'] ?? ($fund['FundAmountMinimum'] ?? 0));
    $startDate = (string)($fund['FundDateStart'] ?? '');
    $year = $startDate !== '' ? date('Y', strtotime($startDate)) : date('Y');
    $fundOverviewRows[] = [
        'name' => (string)($fund['FundTitle'] ?? 'N/A'),
        'type' => (string)($fund['InvestmentType'] ?? 'General'),
        'year' => $year,
        'size' => $size,
        'investors' => isset($fundInvestorMap[$fundId]) ? count($fundInvestorMap[$fundId]) : 0,
    ];
}

$fundTransactionFlow = [];
foreach ($funds as $fund) {
    $fundId = (int)($fund['FundID'] ?? 0);
    $fundTransactionFlow[$fundId] = [
        'fund_name' => (string)($fund['FundTitle'] ?? 'N/A'),
        'deposits' => 0.0,
        'withdrawals' => 0.0,
    ];
}

foreach ($transactions as $t) {
    $fundId = (int)($t['FundID'] ?? 0);
    if (!isset($fundTransactionFlow[$fundId])) {
        continue;
    }
    $txType = (string)($t['TransactionType'] ?? '');
    if ($txType === 'Investment') {
        $fundTransactionFlow[$fundId]['deposits'] += (float)($t['FundCapital'] ?? 0);
    } elseif ($txType === 'FullWithdrawal') {
        $fundTransactionFlow[$fundId]['withdrawals'] += (float)($t['FundFullWithdrawalAmount'] ?? 0);
    }
}

$liquidityFlowRows = [];
foreach ($fundTransactionFlow as $flow) {
    $deposits = (float)$flow['deposits'];
    $withdrawals = (float)$flow['withdrawals'];
    $netFlow = $deposits - $withdrawals;
    $liquidityRatioFund = $deposits > 0 ? ($withdrawals / $deposits) * 100 : 0.0;

    if ($liquidityRatioFund >= 60) {
        $liquidityState = 'High';
    } elseif ($liquidityRatioFund >= 35) {
        $liquidityState = 'Good';
    } else {
        $liquidityState = 'Medium';
    }

    $liquidityFlowRows[] = [
        'fund_name' => $flow['fund_name'],
        'deposits' => $deposits,
        'withdrawals' => $withdrawals,
        'net_flow' => $netFlow,
        'liquidity' => $liquidityState,
    ];
}

$fundRiskRows = [];
foreach ($funds as $fund) {
    $risk = ucfirst(strtolower((string)($fund['RiskLevel'] ?? 'Medium')));
    $expectedReturn = (float)($fund['ExpectedReturnPercentage'] ?? 0);

    if ($expectedReturn >= 15) {
        $volatility = 'High';
    } elseif ($expectedReturn >= 8) {
        $volatility = 'Medium';
    } else {
        $volatility = 'Low';
    }

    if ($risk === 'High') {
        $marketSensitivity = 'High';
    } elseif ($risk === 'Low') {
        $marketSensitivity = 'Low';
    } else {
        $marketSensitivity = 'Medium';
    }

    $fundRiskRows[] = [
        'fund_name' => (string)($fund['FundTitle'] ?? 'N/A'),
        'risk' => $risk,
        'volatility' => $volatility,
        'sensitivity' => $marketSensitivity,
    ];
}

$filteredTransactions = $transactions;
if ($filterType === 'investment') {
    $filteredTransactions = array_values(array_filter($transactions, static fn($t) => ($t['TransactionType'] ?? '') === 'Investment'));
} elseif ($filterType === 'withdrawal') {
    $filteredTransactions = array_values(array_filter($transactions, static fn($t) => ($t['TransactionType'] ?? '') === 'FullWithdrawal'));
}

$transactionInvestmentTotal = array_sum(array_map(
    static fn($t) => ($t['TransactionType'] ?? '') === 'Investment' ? (float)($t['FundCapital'] ?? 0) : 0.0,
    $filteredTransactions
));
$transactionWithdrawalTotal = array_sum(array_map(
    static fn($t) => ($t['TransactionType'] ?? '') === 'FullWithdrawal' ? (float)($t['FundFullWithdrawalAmount'] ?? 0) : 0.0,
    $filteredTransactions
));
$transactionNetTotal = $transactionInvestmentTotal - $transactionWithdrawalTotal;

$formatPct = static fn(float $value): string => rtrim(rtrim(number_format($value, 1), '0'), '.');
$formatMoneyPlain = static fn(float $value): string => number_format($value, 0);

render_head('Manager Reports');
render_responsive_shell_start('');
?>

<div class="card-stack reports-page manager-reports-redesign u-inline-80">
    <!-- Manager Statistics -->
    <section class="stat-grid reports-stats">
        <article class="stat-box">
            <div class="meta-label">Total Capital</div>
            <div class="stat-value"><?= e(format_sar($managerDetails['total_capital'])) ?></div>
        </article>
        <article class="stat-box">
            <div class="meta-label">Total Investors</div>
            <div class="stat-value"><?= e($managerDetails['unique_investors']) ?></div>
        </article>
        <article class="stat-box">
            <div class="meta-label">Published Funds</div>
            <div class="stat-value"><?= e($managerDetails['published_funds']) ?></div>
        </article>
        <article class="stat-box">
            <div class="meta-label">Total Funds</div>
            <div class="stat-value"><?= e($managerDetails['total_funds']) ?></div>
        </article>
    </section>

    <section class="report-section reports-control-panel">
        <div class="report-control-head">
            <div>
                <h3 class="section-title">Manager Reports Center</h3>
                <p class="control-note">Filter each report block and focus on what matters in one view.</p>
            </div>
            <div class="control-tools">
                <span class="control-date">Updated: <?= e(date('Y-m-d H:i')) ?></span>
                <button type="button" class="btn-print" onclick="window.print()">&#128424; Print</button>
            </div>
        </div>

        <div class="reports-master-filter">
            <a href="<?= e(route('manager-reports', ['section' => 'executive', 'type' => $filterType, 'exec' => $executiveReport])) ?>" class="filter-btn <?= $sectionFilter === 'executive' ? 'active' : '' ?>">Executive Summary</a>
            <a href="<?= e(route('manager-reports', ['section' => 'funds', 'type' => $filterType, 'exec' => $executiveReport])) ?>" class="filter-btn <?= $sectionFilter === 'funds' ? 'active' : '' ?>">Funds</a>
            <a href="<?= e(route('manager-reports', ['section' => 'investors', 'type' => $filterType, 'exec' => $executiveReport])) ?>" class="filter-btn <?= $sectionFilter === 'investors' ? 'active' : '' ?>">Investors</a>
            <a href="<?= e(route('manager-reports', ['section' => 'transactions', 'type' => $filterType, 'exec' => $executiveReport])) ?>" class="filter-btn <?= $sectionFilter === 'transactions' ? 'active' : '' ?>">Transactions</a>
            <a href="<?= e(route('manager-reports', ['section' => 'system', 'type' => $filterType, 'exec' => $executiveReport])) ?>" class="filter-btn <?= $sectionFilter === 'system' ? 'active' : '' ?>">System Stats</a>
        </div>
    </section>

    <?php if ($sectionFilter === 'executive'): ?>
    <section class="report-section executive-report-panel">
        <div class="executive-sub-filter">
            <a href="<?= e(route('manager-reports', ['section' => 'executive', 'exec' => 'all', 'type' => $filterType])) ?>" class="filter-btn <?= $executiveReport === 'all' ? 'active' : '' ?>">All Executive Reports</a>
            <a href="<?= e(route('manager-reports', ['section' => 'executive', 'exec' => 'performance', 'type' => $filterType])) ?>" class="filter-btn <?= $executiveReport === 'performance' ? 'active' : '' ?>">1. Performance Summary</a>
            <a href="<?= e(route('manager-reports', ['section' => 'executive', 'exec' => 'kpi', 'type' => $filterType])) ?>" class="filter-btn <?= $executiveReport === 'kpi' ? 'active' : '' ?>">2. KPIs</a>
            <a href="<?= e(route('manager-reports', ['section' => 'executive', 'exec' => 'risk-management', 'type' => $filterType])) ?>" class="filter-btn <?= $executiveReport === 'risk-management' ? 'active' : '' ?>">3. Risk Management</a>
            <a href="<?= e(route('manager-reports', ['section' => 'executive', 'exec' => 'funds-overview', 'type' => $filterType])) ?>" class="filter-btn <?= $executiveReport === 'funds-overview' ? 'active' : '' ?>">4. Funds Overview</a>
            <a href="<?= e(route('manager-reports', ['section' => 'executive', 'exec' => 'liquidity-flows', 'type' => $filterType])) ?>" class="filter-btn <?= $executiveReport === 'liquidity-flows' ? 'active' : '' ?>">5. Liquidity & Flows</a>
            <a href="<?= e(route('manager-reports', ['section' => 'executive', 'exec' => 'fund-risks', 'type' => $filterType])) ?>" class="filter-btn <?= $executiveReport === 'fund-risks' ? 'active' : '' ?>">6. Fund Risks</a>
        </div>

        <?php if ($executiveReport === 'all' || $executiveReport === 'performance'): ?>
        <h3 class="section-title">1. Manager Performance Summary</h3>

        <!-- Executive Summary KPI Cards -->
        <div class="exec-kpi-row">
            <div class="exec-kpi-card">
                <span class="exec-kpi-label">Total Deals</span>
                <strong class="exec-kpi-value"><?= e((string)$totalDealsSummary) ?></strong>
                <span class="exec-kpi-sub">Last 18 months</span>
            </div>
            <div class="exec-kpi-card">
                <span class="exec-kpi-label">Net Profit</span>
                <strong class="exec-kpi-value <?= $totalNetSummary >= 0 ? 'exec-pos' : 'exec-neg' ?>">
                    <?= e($formatMoneyPlain(abs((float)$totalNetSummary))) ?> SAR
                </strong>
                <span class="exec-kpi-sub"><?= $totalNetSummary >= 0 ? 'Profit' : 'Loss' ?></span>
            </div>
            <div class="exec-kpi-card">
                <span class="exec-kpi-label">Total Return</span>
                <strong class="exec-kpi-value <?= $totalReturnSummary >= 0 ? 'exec-pos' : 'exec-neg' ?>">
                    <?= e($formatPct(abs((float)$totalReturnSummary))) ?>%
                </strong>
                <span class="exec-kpi-sub">Blended rate</span>
            </div>
            <div class="exec-kpi-card">
                <span class="exec-kpi-label">Overall Risk</span>
                <strong class="exec-kpi-value"><?= e($summaryRisk) ?></strong>
                <span class="exec-kpi-sub">Portfolio risk level</span>
            </div>
            <div class="exec-kpi-card">
                <span class="exec-kpi-label">Compliance</span>
                <strong class="exec-kpi-value <?= $allCompliant ? 'exec-pos' : '' ?>">
                    <?= e($allCompliant ? 'Compliant' : 'Under Review') ?>
                </strong>
                <span class="exec-kpi-sub">Regulatory status</span>
            </div>
            <div class="exec-kpi-card">
                <span class="exec-kpi-label">Winning Rate</span>
                <strong class="exec-kpi-value exec-pos"><?= e($formatPct((float)$winningRate)) ?>%</strong>
                <span class="exec-kpi-sub">Periods with positive return</span>
            </div>
        </div>

        <!-- Semi-annual breakdown -->
        <div class="exec-period-list">
            <?php foreach ($semiAnnualRows as $i => $row): ?>
            <div class="exec-period-card">
                <div class="exec-period-head">
                    <span class="exec-period-ref"><?= e($row['ref_no']) ?></span>
                    <span class="exec-period-type"><?= e($row['report_type']) ?></span>
                    <span class="exec-period-risk exec-risk-<?= strtolower($row['risk']) ?>"><?= e($row['risk']) ?> Risk</span>
                </div>
                <div class="exec-period-body">
                    <div><span>Deals</span><strong><?= e((string)$row['deals']) ?></strong></div>
                    <div><span>Net Profit</span><strong class="<?= $row['net_profit'] >= 0 ? 'exec-pos' : 'exec-neg' ?>"><?= e($formatMoneyPlain((float)$row['net_profit'])) ?> SAR</strong></div>
                    <div><span>Return</span><strong class="<?= $row['return_pct'] >= 0 ? 'exec-pos' : 'exec-neg' ?>"><?= e($formatPct((float)$row['return_pct'])) ?>%</strong></div>
                    <div><span>Compliance</span><strong><?= e($row['compliance']) ?></strong></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($executiveReport === 'all' || $executiveReport === 'kpi' || $executiveReport === 'risk-management'): ?>
        <div class="executive-grid-two">
            <?php if ($executiveReport === 'all' || $executiveReport === 'kpi'): ?>
            <div class="executive-card">
                <h4>2. Key Performance Indicators</h4>
                <table class="data-table executive-table compact">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>AUM</td>
                            <td><?= e($formatMoneyPlain((float)$managerDetails['total_capital'])) ?></td>
                        </tr>
                        <tr>
                            <td>Winning Trades</td>
                            <td><?= e($formatPct((float)$winningRate)) ?>%</td>
                        </tr>
                        <tr>
                            <td>Avg Investment Duration</td>
                            <td><?= e($formatPct((float)$avgInvestmentDuration)) ?> months</td>
                        </tr>
                        <tr>
                            <td>Top Sector</td>
                            <td><?= e($topSector) ?></td>
                        </tr>
                        <tr>
                            <td>Lowest Sector</td>
                            <td><?= e($lowestSector) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($executiveReport === 'all' || $executiveReport === 'risk-management'): ?>
            <div class="executive-card">
                <h4>3. Risk Management</h4>
                <table class="data-table executive-table compact">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Evaluation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Diversification</td>
                            <td><?= e($diversificationEval) ?></td>
                        </tr>
                        <tr>
                            <td>Investment Limits</td>
                            <td><?= e($limitsEval) ?></td>
                        </tr>
                        <tr>
                            <td>Return Volatility</td>
                            <td><?= e($volatilityEval) ?></td>
                        </tr>
                        <tr>
                            <td>Max Drawdown</td>
                            <td><?= e($formatPct((float)$maxDrawdown)) ?>%</td>
                        </tr>
                        <tr>
                            <td>Liquidity</td>
                            <td><?= e($liquidityEval) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($executiveReport === 'all' || $executiveReport === 'funds-overview'): ?>
        <div class="executive-card full-width">
            <h4>4. Funds Overview</h4>
            <div class="table-container executive-table-wrap">
                <table class="data-table executive-table">
                    <thead>
                        <tr>
                            <th>Fund Name</th>
                            <th>Type</th>
                            <th>Year</th>
                            <th>Size</th>
                            <th>Investors</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fundOverviewRows)): ?>
                            <tr>
                                <td colspan="5">No funds available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fundOverviewRows as $fundRow): ?>
                                <tr>
                                    <td><?= e($fundRow['name']) ?></td>
                                    <td><?= e($fundRow['type']) ?></td>
                                    <td><?= e((string)$fundRow['year']) ?></td>
                                    <td><?= e($formatMoneyPlain((float)$fundRow['size'])) ?></td>
                                    <td><?= e((string)$fundRow['investors']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($executiveReport === 'all' || $executiveReport === 'liquidity-flows'): ?>
        <div class="executive-card full-width">
            <h4>5. Liquidity & Flows</h4>
            <div class="table-container executive-table-wrap">
                <table class="data-table executive-table">
                    <thead>
                        <tr>
                            <th>Fund</th>
                            <th>Deposits</th>
                            <th>Withdrawals</th>
                            <th>Net Flow</th>
                            <th>Liquidity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($liquidityFlowRows)): ?>
                            <tr>
                                <td colspan="5">No liquidity data available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($liquidityFlowRows as $flowRow): ?>
                                <tr>
                                    <td><?= e($flowRow['fund_name']) ?></td>
                                    <td><?= e($formatMoneyPlain((float)$flowRow['deposits'])) ?></td>
                                    <td><?= e($formatMoneyPlain((float)$flowRow['withdrawals'])) ?></td>
                                    <td><?= e($formatMoneyPlain((float)$flowRow['net_flow'])) ?></td>
                                    <td><?= e($flowRow['liquidity']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($executiveReport === 'all' || $executiveReport === 'fund-risks'): ?>
        <div class="executive-card full-width">
            <h4>6. Fund Risks</h4>
            <div class="table-container executive-table-wrap">
                <table class="data-table executive-table">
                    <thead>
                        <tr>
                            <th>Fund</th>
                            <th>Risk Level</th>
                            <th>Volatility</th>
                            <th>Market Sensitivity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fundRiskRows)): ?>
                            <tr>
                                <td colspan="4">No risk data available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fundRiskRows as $riskRow): ?>
                                <tr>
                                    <td><?= e($riskRow['fund_name']) ?></td>
                                    <td><?= e($riskRow['risk']) ?></td>
                                    <td><?= e($riskRow['volatility']) ?></td>
                                    <td><?= e($riskRow['sensitivity']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($sectionFilter === 'funds'): ?>
    <!-- Funds -->
    <?php
    $fundFilter = $_GET['fund_filter'] ?? 'all';
    $allowedFundFilters = ['all', 'published', 'draft', 'most-return', 'least-return'];
    if (!in_array($fundFilter, $allowedFundFilters, true)) $fundFilter = 'all';

    $displayFunds = $funds;
    if ($fundFilter === 'published') {
        $displayFunds = array_values(array_filter($funds, fn($f) => strtolower($f['FundAccountStatus'] ?? '') === 'published'));
    } elseif ($fundFilter === 'draft') {
        $displayFunds = array_values(array_filter($funds, fn($f) => strtolower($f['FundAccountStatus'] ?? '') === 'draft'));
    } elseif ($fundFilter === 'most-return') {
        usort($displayFunds, fn($a, $b) => (float)($b['ExpectedReturnPercentage'] ?? 0) <=> (float)($a['ExpectedReturnPercentage'] ?? 0));
    } elseif ($fundFilter === 'least-return') {
        usort($displayFunds, fn($a, $b) => (float)($a['ExpectedReturnPercentage'] ?? 0) <=> (float)($b['ExpectedReturnPercentage'] ?? 0));
    }
    ?>
    <section class="report-section">
        <div class="rpt-section-head">
            <h3 class="section-title">My Investment Funds</h3>
            <div class="filter-buttons">
                <a href="<?= e(route('manager-reports', ['section' => 'funds', 'fund_filter' => 'all', 'type' => $filterType])) ?>"
                   class="filter-btn <?= $fundFilter === 'all' ? 'active' : '' ?>">All</a>
                <a href="<?= e(route('manager-reports', ['section' => 'funds', 'fund_filter' => 'published', 'type' => $filterType])) ?>"
                   class="filter-btn <?= $fundFilter === 'published' ? 'active' : '' ?>">Published Only</a>
                <a href="<?= e(route('manager-reports', ['section' => 'funds', 'fund_filter' => 'draft', 'type' => $filterType])) ?>"
                   class="filter-btn <?= $fundFilter === 'draft' ? 'active' : '' ?>">Draft Only</a>
                <a href="<?= e(route('manager-reports', ['section' => 'funds', 'fund_filter' => 'most-return', 'type' => $filterType])) ?>"
                   class="filter-btn <?= $fundFilter === 'most-return' ? 'active' : '' ?>">Most Returns</a>
                <a href="<?= e(route('manager-reports', ['section' => 'funds', 'fund_filter' => 'least-return', 'type' => $filterType])) ?>"
                   class="filter-btn <?= $fundFilter === 'least-return' ? 'active' : '' ?>">Least Returns</a>
            </div>
        </div>
        <div class="fund-card-row">
            <?php if (empty($displayFunds)): ?>
                <p class="rpt-empty">No funds match the selected filter.</p>
            <?php else: ?>
            <?php foreach ($displayFunds as $fund): ?>
                <article class="fund-card">
                    <div class="fund-card-status fund-status-<?= strtolower(e($fund['FundAccountStatus'] ?? 'draft')) ?>">
                        <?= e($fund['FundAccountStatus'] ?? 'Draft') ?>
                    </div>
                    <h4><?= e($fund['FundTitle']) ?></h4>
                    <div class="card-mini-metrics">
                        <div><span>Minimum</span><strong><?= e(format_sar($fund['FundAmountMinimum'])) ?></strong></div>
                        <div><span>Maximum</span><strong><?= e(format_sar($fund['FundAmountMaximum'])) ?></strong></div>
                        <div><span>Expected Return</span><strong class="success"><?= e(number_format($fund['ExpectedReturnPercentage'], 1)) ?>%</strong></div>
                        <div><span>Risk</span><strong><?= e(ucfirst(strtolower($fund['RiskLevel'] ?? 'N/A'))) ?></strong></div>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($sectionFilter === 'investors'): ?>
    <!-- Investors Data -->
    <?php
    $investorSearch  = trim($_GET['investor_search'] ?? '');
    $investorStatus  = $_GET['investor_status'] ?? 'all';
    $allowedInvStatus = ['all', 'verified', 'pending'];
    if (!in_array($investorStatus, $allowedInvStatus, true)) $investorStatus = 'all';

    $filteredCustomers = $customers;
    if ($investorStatus !== 'all') {
        $filteredCustomers = array_values(array_filter($filteredCustomers, function ($c) use ($investorStatus) {
            return strtolower($c['student']['StudentAccountStates'] ?? '') === $investorStatus;
        }));
    }
    if ($investorSearch !== '') {
        $needle = strtolower($investorSearch);
        $filteredCustomers = array_values(array_filter($filteredCustomers, function ($c) use ($needle) {
            $s = $c['student'];
            $fullName = strtolower(($s['StudentNameFirst'] ?? '') . ' ' . ($s['StudentNameLast'] ?? ''));
            $major = strtolower($s['StudentMajor'] ?? '');
            $id = (string)($s['StudentID'] ?? '');
            return str_contains($fullName, $needle) || str_contains($major, $needle) || str_contains($id, $needle);
        }));
    }
    ?>
    <section class="report-section investors-section">
        <div class="rpt-section-head">
            <h3 class="section-title">Investors</h3>
            <span class="rpt-count"><?= count($filteredCustomers) ?> investor<?= count($filteredCustomers) !== 1 ? 's' : '' ?></span>
        </div>

        <!-- Investor Filters -->
        <div class="investor-filter-bar">
            <form method="GET" action="" class="investor-search-form">
                <input type="hidden" name="page" value="manager-reports">
                <input type="hidden" name="section" value="investors">
                <input type="hidden" name="investor_status" value="<?= e($investorStatus) ?>">
                <input type="text" name="investor_search" value="<?= e($investorSearch) ?>"
                       placeholder="Search by name, major, or ID…" class="investor-search-input">
                <button type="submit" class="filter-btn active">Search</button>
                <?php if ($investorSearch !== ''): ?>
                <a href="<?= e(route('manager-reports', ['section' => 'investors', 'investor_status' => $investorStatus])) ?>" class="filter-btn">Clear</a>
                <?php endif; ?>
            </form>
            <div class="filter-buttons">
                <a href="<?= e(route('manager-reports', ['section' => 'investors', 'investor_status' => 'all', 'investor_search' => $investorSearch])) ?>"
                   class="filter-btn <?= $investorStatus === 'all' ? 'active' : '' ?>">All</a>
                <a href="<?= e(route('manager-reports', ['section' => 'investors', 'investor_status' => 'verified', 'investor_search' => $investorSearch])) ?>"
                   class="filter-btn <?= $investorStatus === 'verified' ? 'active' : '' ?>">Verified</a>
                <a href="<?= e(route('manager-reports', ['section' => 'investors', 'investor_status' => 'pending', 'investor_search' => $investorSearch])) ?>"
                   class="filter-btn <?= $investorStatus === 'pending' ? 'active' : '' ?>">Pending</a>
            </div>
        </div>

        <?php if (empty($filteredCustomers)): ?>
            <div class="empty-state">
                <span class="empty-icon">&#128100;</span>
                <p>No investors found<?= $investorSearch !== '' ? ' for "' . e($investorSearch) . '"' : '' ?></p>
            </div>
        <?php else: ?>
        <div class="investor-cards-grid">
            <?php foreach ($filteredCustomers as $customer):
                $s = $customer['student'];
                $statusClass = strtolower($s['StudentAccountStates'] ?? '') === 'verified' ? 'status-verified' : 'status-pending';
            ?>
            <div class="investor-card">
                <div class="investor-card-header">
                    <div class="investor-avatar">
                        <?= strtoupper(substr($s['StudentNameFirst'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div>
                        <h4 class="investor-name"><?= e(($s['StudentNameFirst'] ?? '') . ' ' . ($s['StudentNameLast'] ?? '')) ?></h4>
                        <span class="badge <?= $statusClass ?>"><?= e($s['StudentAccountStates'] ?? 'N/A') ?></span>
                    </div>
                </div>
                <div class="investor-info-list">
                    <div class="investor-info-row"><span>Student ID</span><strong><?= e($s['StudentID']) ?></strong></div>
                    <div class="investor-info-row"><span>Major</span><strong><?= e($s['StudentMajor'] ?? 'N/A') ?></strong></div>
                    <div class="investor-info-row"><span>Campus</span><strong><?= e($s['StudentCampus'] ?? 'N/A') ?></strong></div>
                    <div class="investor-info-row"><span>Degree</span><strong><?= e($s['StudentAcademicDegree'] ?? 'N/A') ?></strong></div>
                    <div class="investor-info-row"><span>Phone</span><strong><?= e($s['StudentPhoneNumber'] ?? 'N/A') ?></strong></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($sectionFilter === 'transactions'): ?>
    <!-- Transaction History -->
    <section class="report-section">
        <div class="filter-bar">
            <div>
                <h3 class="section-title">&#128196; All Transactions</h3>
                <p class="transactions-subtitle">Professional view for manager operations with clean direction and value tracking.</p>
            </div>
            <div class="filter-buttons">
                <a href="<?= e(route('manager-reports', ['section' => $sectionFilter, 'type' => 'all', 'exec' => $executiveReport])) ?>" class="filter-btn <?= $filterType === 'all' ? 'active' : '' ?>">All</a>
                <a href="<?= e(route('manager-reports', ['section' => $sectionFilter, 'type' => 'investment', 'exec' => $executiveReport])) ?>" class="filter-btn <?= $filterType === 'investment' ? 'active' : '' ?>">Investment</a>
                <a href="<?= e(route('manager-reports', ['section' => $sectionFilter, 'type' => 'withdrawal', 'exec' => $executiveReport])) ?>" class="filter-btn <?= $filterType === 'withdrawal' ? 'active' : '' ?>">Withdrawal</a>
            </div>
        </div>

        <div class="transactions-kpi-grid">
            <article class="transactions-kpi">
                <span class="kpi-label">Transactions</span>
                <strong class="kpi-value"><?= e((string)count($filteredTransactions)) ?></strong>
            </article>
            <article class="transactions-kpi">
                <span class="kpi-label">Deposits</span>
                <strong class="kpi-value"><?= e(format_sar($transactionInvestmentTotal)) ?></strong>
            </article>
            <article class="transactions-kpi">
                <span class="kpi-label">Withdrawals</span>
                <strong class="kpi-value"><?= e(format_sar($transactionWithdrawalTotal)) ?></strong>
            </article>
            <article class="transactions-kpi">
                <span class="kpi-label">Net Flow</span>
                <strong class="kpi-value <?= $transactionNetTotal >= 0 ? 'success' : 'danger' ?>"><?= e(format_sar($transactionNetTotal)) ?></strong>
            </article>
        </div>
        
        <div class="transactions-table-wrapper">
            <?php if (empty($filteredTransactions)): ?>
                <div class="empty-state">
                    <span class="empty-icon">&#128203;</span>
                    <p>No transactions yet</p>
                </div>
            <?php else: ?>
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Direction</th>
                            <th>Amount</th>
                            <th>Fund</th>
                            <th>Investor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredTransactions as $t): 
                            $fund = fund_by_id((int) $t['FundID']);
                            $fundTitle = $fund ? $fund['FundTitle'] : 'N/A';
                            
                            // Get student data from DB only
                            $studentData = repo_fetch_one('SELECT * FROM Student WHERE StudentID = ?', [$t['StudentID']]);
                            $studentName = $studentData ? ($studentData['StudentNameFirst'] . ' ' . $studentData['StudentNameLast']) : $t['StudentID'];
                            
                            $typeClass = $t['TransactionType'] === 'Investment' ? 'type-investment' : 'type-withdrawal';
                            $typeLabel = $t['TransactionType'] === 'Investment' ? 'Investment' : 'Withdrawal';
                            $direction = (string)($t['Direction'] ?? '');
                            if ($direction === '') {
                                $direction = $t['TransactionType'] === 'Investment' ? 'In' : 'Out';
                            }
                            $directionClass = $direction === 'In' ? 'direction-in' : 'direction-out';
                            $directionLabel = $direction === 'In' ? 'Deposit' : 'Withdraw';
                            $amount = $t['TransactionType'] === 'Investment' ? ($t['FundCapital'] ?? 0) : ($t['FundFullWithdrawalAmount'] ?? 0);
                        ?>
                            <tr>
                                <td><?= e($t['TransactionID']) ?></td>
                                <td><span class="badge <?= $typeClass ?>"><?= e($typeLabel) ?></span></td>
                                <td><span class="badge <?= $directionClass ?>"><?= e($directionLabel) ?></span></td>
                                <td class="amount"><?= e(format_sar($amount)) ?></td>
                                <td><?= e($fundTitle) ?></td>
                                <td><?= e($studentName) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($sectionFilter === 'system'): ?>
    <!-- System Statistics -->
    <section class="report-section system-stats">
        <h3 class="section-title">&#128200; System Statistics</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">&#128100;</div>
                <div class="stat-info">
                    <span class="stat-number"><?= e($stats['total_students']) ?></span>
                    <span class="stat-label">Total Students</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">&#128188;</div>
                <div class="stat-info">
                    <span class="stat-number"><?= e($stats['total_managers']) ?></span>
                    <span class="stat-label">Fund Managers</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">&#128178;</div>
                <div class="stat-info">
                    <span class="stat-number"><?= e($stats['total_funds']) ?></span>
                    <span class="stat-label">Active Funds</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">&#128221;</div>
                <div class="stat-info">
                    <span class="stat-number"><?= e($stats['total_transactions']) ?></span>
                    <span class="stat-label">Total Transactions</span>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Navigation Buttons -->
    <section class="report-actions">
        <a href="<?= e(route('home')) ?>" class="cta-primary">&#8592; Back to Home</a>
        <button type="button" class="cta-secondary" onclick="window.print()">&#128424; Print Report</button>
    </section>
</div>


<?php
render_responsive_shell_end();
render_end();
?>






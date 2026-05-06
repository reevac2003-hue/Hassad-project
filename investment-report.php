<?php
/**
 * ملاحظات توثيقية للصفحة: investment-report.php
 * الغرض: تقرير استثماري تفصيلي لصندوق محدد.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - current_student(): جلب بيانات الطالب الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - fund_by_slug(): جلب صندوق باستخدام slug من الرابط. القيمة الراجعة: ?array.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - latest_investment_amount(): حساب آخر مبلغ استثماري للطالب داخل الصندوق. القيمة الراجعة: float.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - repo_fetch_all(): تنفيذ استعلام DB وإرجاع عدة صفوف. القيمة الراجعة: array.
 * - repo_fetch_one(): تنفيذ استعلام DB وإرجاع صف واحد. القيمة الراجعة: ?array.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 * - student_has_contract(): التحقق هل الطالب لديه عقد اشتراك في الصندوق. القيمة الراجعة: bool.
 */
/**
 * Investment Report Page
 * --------------------------------
 * Shows detailed report for a specific investment/fund subscription
 */

// Verify student or manager access
if (!is_logged_in_as_student() && !is_logged_in_as_manager()) {
    set_flash('error', 'Access denied. Login required.');
    redirect_to('login');
}

$slug = (string) ($_GET['slug'] ?? '');
$fund = $slug !== '' ? fund_by_slug($slug) : null;

if (!$fund && is_logged_in_as_student()) {
    $student = current_student();
    $studentId = (int) ($student['StudentID'] ?? 0);
    $contracts = student_contracts($studentId);
    if (!empty($contracts)) {
        $fallbackFundId = (int) ($contracts[0]['FundID'] ?? 0);
        if ($fallbackFundId > 0) {
            $fund = fund_by_id($fallbackFundId);
        }
    }
}

if (!$fund) {
    set_flash('error', 'Fund not found.');
    redirect_to('student-dashboard');
}

$student = current_student();
$studentId = (int) $student['StudentID'];
$fundId = (int) $fund['FundID'];

// Check if student has subscription
if (!student_has_contract($studentId, $fundId)) {
    set_flash('error', 'You are not subscribed to this fund.');
    redirect_to('student-dashboard');
}

// Get investment data
$investedAmount = latest_investment_amount($studentId, $fundId);
$returnPercentage = (float) $fund['ExpectedReturnPercentage'];
$currentValue = round($investedAmount * (1 + ($returnPercentage / 100)), 2);
$profitAmount = round($currentValue - $investedAmount, 2);

// Get contract details
$contract = repo_fetch_one('SELECT * FROM Contract WHERE StudentID = ? AND FundID = ?', [$studentId, $fundId]);

// Get transactions for this fund
$transactions = repo_fetch_all(
    'SELECT * FROM `Transaction` WHERE StudentID = ? AND FundID = ? ORDER BY TransactionID DESC',
    [$studentId, $fundId]
);

// Calculate dates
$startDate = $contract['ContractDateStart'] ?? $fund['FundDateStart'];
$endDate = $fund['FundDateEnd'];
$holdingPeriod = (int) $fund['HoldingPeriod'];

// Calculate days remaining
$endTimestamp = strtotime($endDate);
$now = time();
$daysRemaining = max(0, (int) ceil(($endTimestamp - $now) / 86400));

$formatPct = static fn(float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');
$nextDistributionDate = date('Y-m-d', strtotime('+3 months'));
$liquidityStatus = $daysRemaining > 0 ? 'Locked (Holding Period)' : 'Available';
$sectionFilter = (string) ($_GET['section'] ?? 'all');
$allowedSections = ['all', 'snapshot', 'content', 'transactions', 'maturity'];
if (!in_array($sectionFilter, $allowedSections, true)) {
    $sectionFilter = 'all';
}
$reportSlug = (string) ($fund['slug'] ?? $slug);

render_head($fund['FundTitle'] . ' - Investment Report');
render_responsive_shell_start('', true);
?>

<div class="card-stack reports-page manager-reports-redesign student-report-redesign">
    <section class="stat-grid reports-stats">
        <article class="stat-box">
            <div class="meta-label">Invested Amount</div>
            <div class="stat-value"><?= e(format_sar($investedAmount)) ?></div>
        </article>
        <article class="stat-box">
            <div class="meta-label">Current Value</div>
            <div class="stat-value"><?= e(format_sar($currentValue)) ?></div>
        </article>
        <article class="stat-box">
            <div class="meta-label">Profit</div>
            <div class="stat-value success"><?= e(format_sar($profitAmount)) ?></div>
        </article>
        <article class="stat-box">
            <div class="meta-label">Return Rate</div>
            <div class="stat-value"><?= e($formatPct($returnPercentage)) ?>%</div>
        </article>
    </section>

    <section class="report-section reports-control-panel">
        <div class="report-control-head">
            <div>
                <h3 class="section-title">Student Reports Center</h3>
                <p class="control-note">Your investment report layout now matches manager reports for consistency.</p>
            </div>
            <div class="control-tools">
                <span class="control-date">Updated: <?= e(date('Y-m-d H:i')) ?></span>
                <button type="button" class="btn-print" onclick="window.print()">Print</button>
            </div>
        </div>

        <div class="reports-master-filter student-tabs-filter">
            <a href="<?= e(route('investment-report', ['slug' => $reportSlug, 'section' => 'all'])) ?>" class="filter-btn <?= $sectionFilter === 'all' ? 'active' : '' ?>">All Reports</a>
            <a href="<?= e(route('investment-report', ['slug' => $reportSlug, 'section' => 'snapshot'])) ?>" class="filter-btn <?= $sectionFilter === 'snapshot' ? 'active' : '' ?>">Investment Snapshot</a>
            <a href="<?= e(route('investment-report', ['slug' => $reportSlug, 'section' => 'content'])) ?>" class="filter-btn <?= $sectionFilter === 'content' ? 'active' : '' ?>">Report Content</a>
            <a href="<?= e(route('investment-report', ['slug' => $reportSlug, 'section' => 'transactions'])) ?>" class="filter-btn <?= $sectionFilter === 'transactions' ? 'active' : '' ?>">Transactions</a>
            <a href="<?= e(route('investment-report', ['slug' => $reportSlug, 'section' => 'maturity'])) ?>" class="filter-btn <?= $sectionFilter === 'maturity' ? 'active' : '' ?>">Maturity</a>
        </div>
    </section>

    <?php if ($sectionFilter === 'all' || $sectionFilter === 'snapshot'): ?>
    <section class="report-section executive-report-panel">
        <h3 class="section-title">1. Investment Snapshot</h3>
        <div class="table-container executive-table-wrap">
            <table class="data-table executive-table">
                <thead>
                    <tr>
                        <th>Fund Name</th>
                        <th>Investment Type</th>
                        <th>Risk Level</th>
                        <th>Subscription Date</th>
                        <th>Maturity Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= e((string) ($fund['FundTitle'] ?? '-')) ?></td>
                        <td><?= e((string) ($fund['InvestmentType'] ?? '-')) ?></td>
                        <td><?= e(ucfirst((string) ($fund['RiskLevel'] ?? 'medium'))) ?></td>
                        <td><?= e((string) ($startDate ?? '-')) ?></td>
                        <td><?= e((string) ($endDate ?? '-')) ?></td>
                        <td><?= e($liquidityStatus) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($sectionFilter === 'all' || $sectionFilter === 'content'): ?>
    <section class="report-section executive-report-panel">
        <h3 class="section-title">2. Report Content (Data Tables)</h3>
        <div class="table-container executive-table-wrap">
            <table class="data-table executive-table">
                <thead>
                    <tr>
                        <th>Fund</th>
                        <th>Total Invested</th>
                        <th>Current Return</th>
                        <th>Current Value</th>
                        <th>Holding Window</th>
                        <th>Liquidity</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= e((string) ($fund['FundTitle'] ?? '-')) ?></td>
                        <td><?= e(format_sar($investedAmount)) ?></td>
                        <td><?= e(format_sar($profitAmount)) ?></td>
                        <td><?= e(format_sar($currentValue)) ?></td>
                        <td><?= e((string) $holdingPeriod) ?> months</td>
                        <td><?= e($liquidityStatus) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($sectionFilter === 'all' || $sectionFilter === 'transactions'): ?>
    <section class="report-section executive-report-panel">
        <h3 class="section-title">3. Transaction Details</h3>
        <div class="table-container executive-table-wrap">
            <table class="data-table executive-table">
                <thead>
                    <tr>
                        <th>Ref No</th>
                        <th>Status</th>
                        <th>Fund</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6">No transactions recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <?php
                                $type = (string) ($t['TransactionType'] ?? 'Investment');
                                $isInvestment = strtolower($type) === 'investment';
                                $amount = $isInvestment ? (float) ($t['FundCapital'] ?? 0) : (float) ($t['FundFullWithdrawalAmount'] ?? 0);
                                $direction = (string) ($t['Direction'] ?? ($isInvestment ? 'In' : 'Out'));
                                $status = $direction === 'In' ? 'Completed' : 'Processed';
                                $txDate = (string) ($t['TransactionDate'] ?? date('Y-m-d'));
                            ?>
                            <tr>
                                <td>TXN-<?= e((string) ($t['TransactionID'] ?? '0')) ?></td>
                                <td><?= e($status) ?></td>
                                <td><?= e((string) ($fund['FundTitle'] ?? '-')) ?></td>
                                <td><?= e(format_sar($amount)) ?></td>
                                <td><?= e($isInvestment ? 'Monthly Deduction' : 'Withdrawal') ?></td>
                                <td><?= e($txDate) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($sectionFilter === 'all' || $sectionFilter === 'maturity'): ?>
    <section class="report-section executive-report-panel">
        <h3 class="section-title">4. Investment Maturity & Distribution</h3>
        <div class="table-container executive-table-wrap">
            <table class="data-table executive-table">
                <thead>
                    <tr>
                        <th>Liquidity Status</th>
                        <th>Lock End Date</th>
                        <th>Distribution Cycle</th>
                        <th>Next Distribution Date</th>
                        <th>Fund</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= e($liquidityStatus) ?></td>
                        <td><?= e((string) ($endDate ?? '-')) ?></td>
                        <td><?= e((string) ($fund['ReturnPolicy'] ?? 'Quarterly')) ?></td>
                        <td><?= e($nextDistributionDate) ?></td>
                        <td><?= e((string) ($fund['FundTitle'] ?? '-')) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <section class="report-actions">
        <a href="<?= e(route('student-dashboard')) ?>" class="cta-primary">Back to Dashboard</a>
        <a href="<?= e(route('fund', ['slug' => (string) ($fund['slug'] ?? '')])) ?>" class="cta-secondary">View Fund Details</a>
        <button type="button" class="cta-secondary" onclick="window.print()">Print Report</button>
    </section>
</div>

<?php
render_responsive_shell_end();
render_end();
?>




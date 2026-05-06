<?php
/**
 * ملاحظات توثيقية للصفحة: wallet.php
 * الغرض: عرض المحفظة والعمليات والبطاقات.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - current_bank_account(): جلب البطاقة/الحساب البنكي للطالب. القيمة الراجعة: array.
 * - current_student(): جلب بيانات الطالب الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - current_wallet(): جلب بيانات محفظة الطالب. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - fund_by_id(): جلب صندوق محدد عبر FundID. القيمة الراجعة: ?array.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - latest_investment_amount(): حساب آخر مبلغ استثماري للطالب داخل الصندوق. القيمة الراجعة: float.
 * - mask_card(): إخفاء جزء من رقم البطاقة عند العرض. القيمة الراجعة: string.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 * - student_contracts(): جلب عقود الطالب. القيمة الراجعة: array.
 * - student_transactions(): جلب العمليات المالية للطالب. القيمة الراجعة: array.
 * - student_withdrawal_requests(): جلب طلبات سحب الطالب. القيمة الراجعة: array.
 */
/**
 * Virtual Wallet Page - ط·آµط¸ظ¾ط·آ­ط·آ© ط·آ§ط¸â€‍ط¸â€¦ط·آ­ط¸ظ¾ط·آ¸ط·آ© ط·آ§ط¸â€‍ط·آ§ط¸ظ¾ط·ع¾ط·آ±ط·آ§ط·آ¶ط¸ظ¹ط·آ©
 * --------------------------------
 * Displays student's virtual investment balances and returns
 */

if (!is_logged_in_as_student()) {
    set_flash('error', 'Access denied. Student login required.');
    redirect_to('login', ['role' => 'student']);
}

$student = current_student();
$studentId = (int) $student['StudentID'];
$wallet = current_wallet($studentId);
$bankAccount = current_bank_account($studentId);
$transactions = student_transactions($studentId);
$withdrawalRequests = student_withdrawal_requests($studentId);
$contracts = student_contracts($studentId);

// Calculate totals
$totalInvested = 0;
$totalWithdrawn = 0;
$totalReturns = (float) $wallet['InvestmentWalletReturn'];

foreach ($transactions as $t) {
    if ($t['TransactionType'] === 'Investment') {
        $totalInvested += (float) ($t['FundCapital'] ?? 0);
    } elseif ($t['TransactionType'] === 'FullWithdrawal') {
        $totalWithdrawn += (float) ($t['FundFullWithdrawalAmount'] ?? 0);
    } elseif ($t['TransactionType'] === 'ProfitWithdrawal') {
        $totalWithdrawn += (float) ($t['FundWithdrawnProfit'] ?? 0);
    }
}

$netInvestment = $totalInvested - $totalWithdrawn;
$totalValue = (float) $wallet['InvestmentWalletTotalAmount'];
$availableCredit = (float) $wallet['InvestmentWalletCredit'];

render_head('My Wallet');
render_responsive_shell_start('');
?>

<div class="wallet-page">
    <!-- Page Header -->
    <div class="page-header-modern">
        <div class="header-content">
            <h1 class="page-title">My Virtual Wallet</h1>
            <p class="page-subtitle">Track your investments, returns, and manage withdrawals</p>
        </div>
        <div class="header-actions">
            <a href="<?= e(route('home')) ?>" class="btn-outline">Back to Home</a>
        </div>
    </div>

    <!-- Wallet Overview Cards -->
    <div class="wallet-overview">
        <div class="wallet-card primary">
            <div class="wallet-card-icon">VAL</div>
            <div class="wallet-card-content">
                <span class="wallet-card-label">Total Portfolio Value</span>
                <span class="wallet-card-value"><?= format_sar($totalValue) ?></span>
            </div>
        </div>
        
        <div class="wallet-card success">
            <div class="wallet-card-icon">RET</div>
            <div class="wallet-card-content">
                <span class="wallet-card-label">Available Returns</span>
                <span class="wallet-card-value"><?= format_sar($totalReturns) ?></span>
            </div>
        </div>
        
        <div class="wallet-card info">
            <div class="wallet-card-icon">CR</div>
            <div class="wallet-card-content">
                <span class="wallet-card-label">Available Credit</span>
                <span class="wallet-card-value"><?= format_sar($availableCredit) ?></span>
            </div>
        </div>
        
        <div class="wallet-card warning">
            <div class="wallet-card-icon">NET</div>
            <div class="wallet-card-content">
                <span class="wallet-card-label">Net Investment</span>
                <span class="wallet-card-value"><?= format_sar($netInvestment) ?></span>
            </div>
        </div>
    </div>

    <!-- Withdrawal Section -->
    <div class="wallet-section">
        <h2 class="section-title">Withdraw Profits</h2>
        
        <?php if ($totalReturns > 0): ?>
        <div class="withdrawal-form-container">
            <form action="<?= e(action_url('withdraw-profit')) ?>" method="POST" class="withdrawal-form">
                <div class="form-group">
                    <label for="amount">Amount to Withdraw (SAR)</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="1" max="<?= $totalReturns ?>" 
                           placeholder="Enter amount" required class="form-input">
                    <small class="form-hint">Available: <?= format_sar($totalReturns) ?></small>
                </div>
                
                <div class="form-group">
                    <label for="withdrawal_type">Withdrawal Type</label>
                    <select id="withdrawal_type" name="withdrawal_type" class="form-select">
                        <option value="Profit">Profit Only</option>
                        <option value="Partial">Partial Withdrawal</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Withdrawal to</label>
                    <div class="bank-info">
                        <span class="bank-icon">IBAN</span>
                        <span class="bank-iban"><?= e(mask_card($bankAccount['BankAccountIban'] ?? 'No IBAN registered')) ?></span>
                    </div>
                </div>
                
                <button type="submit" class="btn-gold btn-full">Request Withdrawal</button>
            </form>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">0</div>
            <h3>No Returns Available</h3>
            <p>You don't have any returns available for withdrawal yet.</p>
            <a href="<?= e(route('search-funds')) ?>" class="btn-gold">Explore Investment Funds</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pending Withdrawal Requests -->
    <?php if (!empty($withdrawalRequests)): ?>
    <div class="wallet-section">
        <h2 class="section-title">Withdrawal Requests</h2>
        <div class="requests-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($withdrawalRequests as $request): ?>
                    <tr>
                        <td><?= e(date('M d, Y', strtotime($request['RequestDate']))) ?></td>
                        <td><?= format_sar($request['Amount']) ?></td>
                        <td><?= e($request['WithdrawalType']) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($request['Status']) ?>">
                                <?= e($request['Status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Active Investments -->
    <div class="wallet-section">
        <h2 class="section-title">Active Investments</h2>
        
        <?php if (!empty($contracts)): ?>
        <div class="investments-grid">
            <?php foreach ($contracts as $contract): 
                $fund = fund_by_id((int)$contract['FundID']);
                if (!$fund) continue;
                $invested = latest_investment_amount($studentId, (int)$contract['FundID']);
                $currentValue = $invested * (1 + ((float)$fund['ExpectedReturnPercentage'] / 100));
                $profit = $currentValue - $invested;
            ?>
            <div class="investment-card">
                <div class="investment-header">
                    <h3><?= e($fund['FundTitle']) ?></h3>
                    <span class="risk-badge risk-<?= strtolower($fund['RiskLevel']) ?>"><?= e(ucfirst($fund['RiskLevel'])) ?></span>
                </div>
                <div class="investment-details">
                    <div class="detail-row">
                        <span>Invested:</span>
                        <span><?= format_sar($invested) ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Current Value:</span>
                        <span><?= format_sar($currentValue) ?></span>
                    </div>
                    <div class="detail-row profit">
                        <span>Profit:</span>
                        <span class="<?= $profit >= 0 ? 'positive' : 'negative' ?>">
                            <?= $profit >= 0 ? '+' : '' ?><?= format_sar($profit) ?>
                        </span>
                    </div>
                </div>
                <a href="<?= e(route('fund', ['slug' => $fund['slug']])) ?>" class="btn-outline btn-sm">View Fund</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">0</div>
            <h3>No Active Investments</h3>
            <p>You haven't subscribed to any investment funds yet.</p>
            <a href="<?= e(route('search-funds')) ?>" class="btn-gold">Browse Available Funds</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Transactions -->
    <div class="wallet-section">
        <h2 class="section-title">Recent Transactions</h2>
        
        <?php if (!empty($transactions)): ?>
        <div class="transactions-list">
            <?php foreach (array_slice($transactions, 0, 10) as $t): ?>
            <div class="transaction-item">
                <div class="transaction-icon <?= $t['Direction'] === 'In' ? 'in' : 'out' ?>">
                    <?= $t['Direction'] === 'In' ? 'IN' : 'OUT' ?>
                </div>
                <div class="transaction-details">
                    <span class="transaction-type"><?= e($t['TransactionType']) ?></span>
                    <span class="transaction-date"><?= e(date('M d, Y', strtotime($t['TransactionDate'] ?? 'now'))) ?></span>
                </div>
                <div class="transaction-amount <?= $t['Direction'] === 'In' ? 'positive' : 'negative' ?>">
                    <?= $t['Direction'] === 'In' ? '+' : '-' ?>
                    <?= format_sar((float)($t['FundCapital'] ?: $t['FundFullWithdrawalAmount'] ?: $t['FundWithdrawnProfit'] ?: 0)) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="section-footer">
            <a href="<?= e(route('reports')) ?>" class="btn-outline">View All Transactions</a>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p>No transactions yet</p>
        </div>
        <?php endif; ?>
    </div>
</div>


<?php
render_responsive_shell_end();
render_end();
?>





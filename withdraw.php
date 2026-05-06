<?php
/**
 * ملاحظات توثيقية للصفحة: withdraw.php
 * الغرض: إرسال طلب سحب ومتابعة حالته.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - current_bank_account(): جلب البطاقة/الحساب البنكي للطالب. القيمة الراجعة: array.
 * - current_student(): جلب بيانات الطالب الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - current_wallet(): جلب بيانات محفظة الطالب. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - require_student_login(): فرض تسجيل الدخول كطالب قبل متابعة التنفيذ. القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - student_withdrawal_requests(): جلب طلبات سحب الطالب. القيمة الراجعة: array.
 */
/**
 * ط·آµط¸ظ¾ط·آ­ط·آ© ط·آ·ط¸â€‍ط·آ¨ ط·آ³ط·آ­ط·آ¨ ط·آ§ط¸â€‍ط·آ£ط·آ±ط·آ¨ط·آ§ط·آ­ - Withdraw Request Page
 * ---------------------------------------------
 * ط·ع¾ط·ع¾ط¸ظ¹ط·آ­ ط¸â€‍ط¸â€‍ط·آ·ط·آ§ط¸â€‍ط·آ¨ ط·آ·ط¸â€‍ط·آ¨ ط·آ³ط·آ­ط·آ¨ ط·آ£ط·آ±ط·آ¨ط·آ§ط·آ­ط¸â€، ط¸â€¦ط¸â€  ط·آ§ط¸â€‍ط¸â€¦ط·آ­ط¸ظ¾ط·آ¸ط·آ© ط·آ§ط¸â€‍ط·آ§ط·آ³ط·ع¾ط·آ«ط¸â€¦ط·آ§ط·آ±ط¸ظ¹ط·آ©
 */

// ط·آ§ط¸â€‍ط·ع¾ط·آ­ط¸â€ڑط¸â€ڑ ط¸â€¦ط¸â€  ط·ع¾ط·آ³ط·آ¬ط¸ظ¹ط¸â€‍ ط·آ¯ط·آ®ط¸ث†ط¸â€‍ ط·آ§ط¸â€‍ط·آ·ط·آ§ط¸â€‍ط·آ¨
require_student_login();

$student = current_student();
$studentId = (int) $student['StudentID'];
$wallet = current_wallet($studentId);
$bankAccount = current_bank_account($studentId);

// ط·آ­ط·آ³ط·آ§ط·آ¨ ط·آ§ط¸â€‍ط·آ±ط·آµط¸ظ¹ط·آ¯ ط·آ§ط¸â€‍ط¸â€¦ط·ع¾ط·آ§ط·آ­ ط¸â€‍ط¸â€‍ط·آ³ط·آ­ط·آ¨
$availableBalance = (float)($wallet['InvestmentWalletReturn'] ?? 0);
$pendingWithdrawals = student_withdrawal_requests($studentId);
$pendingAmount = array_sum(array_map(fn($w) => $w['Status'] === 'Pending' ? (float)$w['Amount'] : 0, $pendingWithdrawals));
$withdrawable = max(0, $availableBalance - $pendingAmount);

// ط¸â€¦ط·آ¹ط·آ§ط¸â€‍ط·آ¬ط·آ© ط·آ±ط·آ³ط·آ§ط·آ¦ط¸â€‍ Flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

render_head('Withdraw Profits');
render_responsive_shell_start('');
?>

<div class="withdraw-page">
    <!-- Header -->
    <div class="page-header">
        <a href="<?= e(route('wallet')) ?>" class="back-link">Back to Wallet</a>
        <h1>Withdraw Profits</h1>
        <p>Request withdrawal of your investment returns</p>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Balance Summary -->
    <div class="balance-summary">
        <div class="balance-card">
            <div class="balance-icon">RET</div>
            <div class="balance-info">
                <span class="balance-label">Available Returns</span>
                <span class="balance-value"><?= format_sar($availableBalance) ?></span>
            </div>
        </div>
        <div class="balance-card pending">
            <div class="balance-icon">PD</div>
            <div class="balance-info">
                <span class="balance-label">Pending Withdrawals</span>
                <span class="balance-value"><?= format_sar($pendingAmount) ?></span>
            </div>
        </div>
        <div class="balance-card withdrawable">
            <div class="balance-icon">OK</div>
            <div class="balance-info">
                <span class="balance-label">Withdrawable Now</span>
                <span class="balance-value"><?= format_sar($withdrawable) ?></span>
            </div>
        </div>
    </div>

    <div class="withdraw-container">
        <!-- Withdrawal Form -->
        <div class="withdraw-form-section">
            <h2>New Withdrawal Request</h2>
            
            <?php if ($withdrawable <= 0): ?>
            <div class="no-balance">
                <div class="no-balance-icon">0</div>
                <h3>No Funds Available</h3>
                <p>You don't have any profits available for withdrawal at this time.</p>
                <a href="<?= e(route('home')) ?>" class="btn-outline">Back to Home</a>
            </div>
            <?php else: ?>
            <form action="<?= e(action_url('withdraw-profit')) ?>" method="POST" class="withdraw-form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                
                <div class="form-group">
                    <label for="amount">Withdrawal Amount (SAR)</label>
                    <input type="number" 
                           id="amount" 
                           name="amount" 
                           min="1" 
                           max="<?= e($withdrawable) ?>" 
                           step="0.01" 
                           required
                           placeholder="Enter amount to withdraw">
                    <small>Maximum: <?= format_sar($withdrawable) ?></small>
                </div>

                <div class="form-group">
                    <label for="withdrawal_type">Withdrawal Type</label>
                    <select id="withdrawal_type" name="withdrawal_type" required>
                        <option value="">Select type...</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="reinvest">Reinvest in Fund</option>
                    </select>
                </div>

                <div class="bank-details" id="bankDetails" class="u-inline-75">
                    <h4>Bank Account Details</h4>
                    <?php if ($bankAccount['BankAccountIban']): ?>
                    <div class="saved-account">
                        <div class="account-info">
                            <span class="account-label">IBAN:</span>
                            <span class="account-value"><?= e($bankAccount['BankAccountIban']) ?></span>
                        </div>
                        <div class="account-info">
                            <span class="account-label">Name:</span>
                            <span class="account-value"><?= e($bankAccount['CreditCardName']) ?></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="no-bank-account">
                        <p>No bank account on file. Please add one:</p>
                        <a href="<?= e(route('bank-cards')) ?>" class="btn-sm btn-outline">Add Bank Account</a>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-gold btn-withdraw">
                    <span>Submit Withdrawal Request</span>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Withdrawal History -->
        <div class="history-section">
            <h2>Withdrawal History</h2>
            
            <?php if (empty($pendingWithdrawals)): ?>
            <div class="no-history">
                <p>No withdrawal requests yet.</p>
            </div>
            <?php else: ?>
            <div class="history-list">
                <?php foreach ($pendingWithdrawals as $withdrawal): ?>
                <div class="history-item">
                    <div class="item-header">
                        <span class="item-amount"><?= format_sar($withdrawal['Amount']) ?></span>
                        <span class="status-badge status-<?= strtolower($withdrawal['Status']) ?>">
                            <?= e($withdrawal['Status']) ?>
                        </span>
                    </div>
                    <div class="item-details">
                        <span class="item-type"><?= e(ucwords(str_replace('_', ' ', $withdrawal['WithdrawalType'] ?? 'bank_transfer'))) ?></span>
                        <span class="item-date"><?= e($withdrawal['RequestedAt'] ?? date('Y-m-d')) ?></span>
                    </div>
                    <?php if (!empty($withdrawal['RejectionReason'])): ?>
                    <div class="rejection-reason">
                        <strong>Reason:</strong> <?= e($withdrawal['RejectionReason']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Section -->
    <div class="info-section">
        <h3>Withdrawal Information</h3>
        <div class="info-cards">
            <div class="info-card">
                <div class="info-icon">TIME</div>
                <h4>Processing Time</h4>
                <p>Withdrawal requests are typically processed within 3-5 business days.</p>
            </div>
            <div class="info-card">
                <div class="info-icon">BANK</div>
                <h4>Transfer Methods</h4>
                <p>Funds are transferred to your registered bank account via IBAN.</p>
            </div>
            <div class="info-card">
                <div class="info-icon">RE</div>
                <h4>Reinvestment</h4>
                <p>You can choose to reinvest your profits into any available fund.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('withdrawal_type')?.addEventListener('change', function() {
    const bankDetails = document.getElementById('bankDetails');
    if (this.value === 'bank_transfer') {
        bankDetails.style.display = 'block';
    } else {
        bankDetails.style.display = 'none';
    }
});
</script>


<?php
render_responsive_shell_end();
render_end();
?>






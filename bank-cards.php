<?php
/**
 * ملاحظات توثيقية للصفحة: bank-cards.php
 * الغرض: إدارة بطاقات الطالب المرتبطة بالحساب.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - bank_cards_for_student(): جلب البطاقات المرتبطة بالطالب. القيمة الراجعة: array.
 * - current_student(): جلب بيانات الطالب الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - mask_card(): إخفاء جزء من رقم البطاقة عند العرض. القيمة الراجعة: string.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 * - student_transactions(): جلب العمليات المالية للطالب. القيمة الراجعة: array.
 */
if (!is_logged_in_as_student()) {
    set_flash('error', 'Access denied. Student login required.');
    redirect_to('login', ['role' => 'student']);
}

$student = current_student();
$studentId = (int) $student['StudentID'];
$bankCards = bank_cards_for_student($studentId);
$transactions = student_transactions($studentId);

render_head('Bank Cards & Transactions');
render_responsive_shell_start('');
?>

<div class="clean-dashboard bank-cards-page">
    <!-- Page Header -->
    <div class="page-header-clean">
        <a href="<?= e(route('home')) ?>" class="back-link">Back to Home</a>
        <h1>My Account</h1>
    </div>

    <!-- Bank Card Section -->
    <section class="bank-card-section">
        <h3 class="section-title-dark">Linked Card</h3>
        
        <?php if (empty($bankCards)): ?>
        <div class="empty-card-box">
            <p>No bank card linked to your account</p>
            <a href="<?= e(route('edit-profile')) ?>" class="btn-gold">Link a Card</a>
        </div>
        <?php else: ?>
        <div class="u-inline-60">
            <?php foreach ($bankCards as $bankCard): ?>
            <?php $expiryText = !empty($bankCard['CreditCardDayExpired']) ? date('m/y', strtotime((string) $bankCard['CreditCardDayExpired'])) : 'N/A'; ?>
            <div>
                <div class="credit-card-visual">
                    <div class="card-chip"></div>
                    <div class="card-number"><?= e(mask_card((string) $bankCard['CreditCardNumber'])) ?></div>
                    <div class="card-bottom">
                        <div class="card-holder">
                            <span class="card-label">Card Holder</span>
                            <span class="card-value"><?= e($bankCard['CreditCardName'] ?? $student['StudentNameFirst'] . ' ' . $student['StudentNameLast']) ?></span>
                        </div>
                        <div class="card-expiry">
                            <span class="card-label">Expires</span>
                            <span class="card-value"><?= e($expiryText) ?></span>
                        </div>
                    </div>
                </div>
                <div class="iban-box">
                    <span class="iban-label">IBAN</span>
                    <span class="iban-value"><?= e($bankCard['BankAccountIban'] ?: 'N/A') ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="card-actions">
            <a href="<?= e(route('edit-profile')) ?>" class="btn-outline">Manage Card</a>
        </div>
        <?php endif; ?>
    </section>

    <!-- Transactions Section -->
    <section class="transactions-section-full">
        <h3 class="section-title-dark">Transaction History</h3>
        
        <?php if (empty($transactions)): ?>
        <div class="empty-transactions-box">
            <p>No transactions yet</p>
        </div>
        <?php else: ?>
        <div class="transactions-list-full">
            <?php foreach ($transactions as $txn): 
                $type = strtolower($txn['TransactionType'] ?? '');
                $isDeposit = in_array($type, ['deposit', 'investment']);
            ?>
            <div class="transaction-row <?= $isDeposit ? 'deposit' : 'withdrawal' ?>">
                <div class="txn-icon-box">
                    <?= $isDeposit ? 'IN' : 'OUT' ?>
                </div>
                <div class="txn-info">
                    <span class="txn-type"><?= e(ucfirst($txn['TransactionType'] ?? 'Transaction')) ?></span>
                    <span class="txn-date"><?= e(date('M d, Y - H:i', strtotime($txn['TransactionDate'] ?? 'now'))) ?></span>
                    <?php if (!empty($txn['FundID'])): ?>
                    <span class="txn-fund">Fund #<?= e($txn['FundID']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="txn-amount-box <?= $isDeposit ? 'positive' : 'negative' ?>">
                    <?= $isDeposit ? '+' : '-' ?><?= number_format((float)($txn['TransactionAmount'] ?? $txn['FundCapital'] ?? 0), 2) ?> SAR
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php
render_responsive_shell_end();
render_end();
?>




<?php
/**
 * ملاحظات توثيقية للصفحة: student-settings.php
 * الغرض: إعدادات الطالب والعقود والحذف والتجميد.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - all_managers(): جلب قائمة المديرين. القيمة الراجعة: array.
 * - current_student(): جلب بيانات الطالب الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - fund_by_id(): جلب صندوق محدد عبر FundID. القيمة الراجعة: ?array.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - is_user_banned(): التحقق من وجود حظر نشط للمستخدم. القيمة الراجعة: bool.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 * - student_contracts(): جلب عقود الطالب. القيمة الراجعة: array.
 */
/**
 * Student Settings Page - ط·آ¥ط·آ¹ط·آ¯ط·آ§ط·آ¯ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ·ط·آ§ط¸â€‍ط·آ¨
 */

if (!is_logged_in_as_student()) {
    set_flash('error', 'Access denied. Student login required.');
    redirect_to('login', ['role' => 'student']);
}

$student = current_student();
$studentId = (int) ($student['StudentID'] ?? 0);
if ($studentId <= 0) {
    set_flash('error', 'Unable to load student profile. Please sign in again.');
    redirect_to('login', ['role' => 'student']);
}
$isBanned = is_user_banned($studentId);
$contracts = student_contracts($studentId);
$managers = all_managers();

$managerByLicense = [];
foreach ($managers as $m) {
    $license = (int) ($m['FundManagerNumberofLicense'] ?? 0);
    if ($license > 0) {
        $managerByLicense[$license] = $m;
    }
}

usort($contracts, static function (array $a, array $b): int {
    $aId = (int) ($a['ContractID'] ?? 0);
    $bId = (int) ($b['ContractID'] ?? 0);
    if ($aId !== $bId) {
        return $bId <=> $aId;
    }
    return strcmp((string) ($b['Date'] ?? ''), (string) ($a['Date'] ?? ''));
});

render_head('Settings');
render_responsive_shell_start('');
?>

<div class="settings-page student-settings-page">

    <!-- Page Header -->
    <div class="page-header-clean">
        <a href="<?= e(route('home')) ?>" class="back-link">Back to Home</a>
        <h1>Settings</h1>
    </div>

    <section class="settings-card">
        <div class="settings-card-header">
            <div class="settings-icon">OPT</div>
            <div>
                <h2>Account Options</h2>
                <p>All student options are available here inside Settings</p>
            </div>
        </div>
        <div class="settings-card-body">
            <div class="settings-option-grid">
                <a class="settings-option" href="<?= e(route('edit-profile')) ?>">
                    <div class="settings-option-icon">PR</div>
                    <div>
                        <strong>Edit Profile</strong>
                        <p>Update your personal and bank information</p>
                    </div>
                </a>
                <a class="settings-option" href="<?= e(route('terms')) ?>" target="_blank" rel="noopener noreferrer">
                    <div class="settings-option-icon">PP</div>
                    <div>
                        <strong>Privacy</strong>
                        <p>Open Terms and Privacy information</p>
                    </div>
                </a>
                <a class="settings-option" href="#my-contracts">
                    <div class="settings-option-icon">CT</div>
                    <div>
                        <strong>My Contracts</strong>
                        <p>View your contracts with managers for each subscribed fund</p>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="settings-card" id="my-contracts">
        <div class="settings-card-header">
            <div class="settings-icon">CT</div>
            <div>
                <h2>My Contracts</h2>
                <p>Contracts between you and fund managers by fund subscription</p>
            </div>
        </div>
        <div class="settings-card-body">
            <?php if (empty($contracts)): ?>
            <div class="contracts-empty">
                <strong>No contracts yet.</strong>
                <p>You will see contract details here after subscribing to a fund.</p>
                <a href="<?= e(route('search-funds')) ?>" class="btn-outline btn-oval">Explore Funds</a>
            </div>
            <?php else: ?>
            <div class="contracts-grid">
                <?php foreach ($contracts as $contract):
                    $fundId = (int) ($contract['FundID'] ?? 0);
                    $fund = fund_by_id($fundId);
                    $managerLicense = is_array($fund) ? (int) ($fund['FundManagerNumberofLicense'] ?? 0) : 0;
                    $manager = $managerByLicense[$managerLicense] ?? null;
                    $managerName = trim((string) ($manager['FundManagerNameFirst'] ?? '') . ' ' . (string) ($manager['FundManagerNameLast'] ?? ''));
                    $contractStatus = (string) ($contract['Status'] ?? 'Active');
                    $amount = (float) ($contract['Amount'] ?? 0);
                    $returnPct = is_array($fund) ? (float) ($fund['ExpectedReturnPercentage'] ?? 0) : 0;
                    $returnPolicy = is_array($fund) ? (string) ($fund['ReturnPolicy'] ?? $fund['ReturnTimingPolicy'] ?? 'Annual') : 'Annual';
                    $fundTitle = is_array($fund) ? (string) ($fund['FundTitle'] ?? ('Fund #' . $fundId)) : ('Fund #' . $fundId);
                    $fundSlug = is_array($fund) ? (string) ($fund['slug'] ?? '') : '';
                ?>
                <article class="contract-card">
                    <div class="contract-head">
                        <h3><?= e($fundTitle) ?></h3>
                        <span class="contract-status"><?= e($contractStatus) ?></span>
                    </div>
                    <div class="contract-meta">
                        <div><span>Contract ID</span><strong>#<?= e((string) ($contract['ContractID'] ?? 'N/A')) ?></strong></div>
                        <div><span>Manager</span><strong><?= e($managerName !== '' ? $managerName : 'N/A') ?></strong></div>
                        <div><span>Manager License</span><strong><?= e($managerLicense > 0 ? (string) $managerLicense : 'N/A') ?></strong></div>
                        <div><span>Subscribed Amount</span><strong><?= e(format_sar($amount)) ?></strong></div>
                        <div><span>Expected Return</span><strong><?= e(number_format($returnPct, 2)) ?>%</strong></div>
                        <div><span>Return Policy</span><strong><?= e($returnPolicy) ?></strong></div>
                        <div><span>Subscription Date</span><strong><?= e((string) ($contract['Date'] ?? 'N/A')) ?></strong></div>
                    </div>
                    <?php if ($fundSlug !== ''): ?>
                    <a href="<?= e(route('fund', ['slug' => $fundSlug])) ?>" class="btn-outline btn-oval">View Fund</a>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Freeze Account Section -->
    <section class="settings-card">
        <div class="settings-card-header">
            <div class="settings-icon">SB</div>
            <div>
                <h2>Freeze Account (Self-Ban)</h2>
                <p>Temporarily restrict your investing to protect yourself from impulsive decisions</p>
            </div>
        </div>
        <div class="settings-card-body">
            <?php if ($isBanned): ?>
            <div class="freeze-active-notice">
                <span class="freeze-icon">ON</span>
                <div>
                    <strong>Self-Ban is Active</strong>
                    <p>Your account is currently frozen. You cannot make new investments.</p>
                </div>
            </div>
            <?php else: ?>
            <p>Use Self-Ban to temporarily lock your investment activity for a set period.</p>
            <a href="<?= e(route('self-ban')) ?>" class="btn-outline btn-oval">Go to Self-Ban</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Delete Account Section -->
    <section class="settings-card settings-card-danger">
        <div class="settings-card-header">
            <div class="settings-icon">DEL</div>
            <div>
                <h2>Delete Account</h2>
                <p>Permanently delete your account and all associated data</p>
            </div>
        </div>
        <div class="settings-card-body">
            <p>Once you delete your account, this action <strong>cannot be undone</strong>. All investments, transactions, and personal data will be permanently removed.</p>
            <button type="button" class="btn-danger-outline btn-oval" onclick="openDeletePopup()">Delete My Account</button>
        </div>
    </section>

</div>

<!-- Delete Account Popup -->
<div class="popup-overlay" id="deletePopup">
    <div class="popup-card">
        <div class="popup-header">
            <h3>Delete Account</h3>
            <button type="button" class="popup-close" onclick="closeDeletePopup()">X</button>
        </div>
        <div class="popup-body">
            <div class="popup-warning-icon">DEL</div>
            <h4>Are you sure you want to delete your account?</h4>
            <ul class="delete-info-list">
                <li>All your personal information will be permanently erased</li>
                <li>Your investment history and reports will be deleted</li>
                <li>Any pending withdrawals will be cancelled</li>
                <li>You will lose access to all your fund subscriptions</li>
                <li>This action <strong>cannot be reversed</strong></li>
            </ul>
            <p class="popup-confirm-text">To confirm, type <strong>DELETE</strong> below:</p>
            <input type="text" id="deleteConfirmInput" class="text-input" placeholder="Type DELETE to confirm">
        </div>
        <div class="popup-footer">
            <button type="button" class="btn-secondary btn-oval" onclick="closeDeletePopup()">Cancel</button>
            <form method="post" action="<?= e(action_url('delete-account')) ?>" id="deleteAccountForm">
                <input type="hidden" name="user_type" value="student">
                <button type="button" class="btn-danger btn-oval" onclick="confirmDelete()">Delete My Account</button>
            </form>
        </div>
    </div>
</div>


<script>
function openDeletePopup() {
    document.getElementById('deletePopup').style.display = 'flex';
}
function closeDeletePopup() {
    document.getElementById('deletePopup').style.display = 'none';
    document.getElementById('deleteConfirmInput').value = '';
}
function confirmDelete() {
    const val = document.getElementById('deleteConfirmInput').value.trim();
    if (val !== 'DELETE') {
        alert('Please type DELETE to confirm account deletion.');
        return;
    }
    document.getElementById('deleteAccountForm').submit();
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeletePopup();
});
</script>

<?php
render_responsive_shell_end();
render_end();
?>






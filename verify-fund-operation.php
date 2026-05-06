<?php
/**
 * ملاحظات توثيقية للصفحة: verify-fund-operation.php
 * الغرض: التحقق من العمليات الحساسة على الصناديق.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - current_manager(): جلب بيانات المدير الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - fund_by_id(): جلب صندوق محدد عبر FundID. القيمة الراجعة: ?array.
 * - mask_phone(): إخفاء جزء من رقم الهاتف عند العرض. القيمة الراجعة: string.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - require_manager_login(): فرض تسجيل الدخول كمدير قبل متابعة التنفيذ. القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
/**
 * ط·آµط¸ظ¾ط·آ­ط·آ© ط·آ§ط¸â€‍ط·ع¾ط·آ­ط¸â€ڑط¸â€ڑ ط·آ§ط¸â€‍ط·آ«ط¸â€ ط·آ§ط·آ¦ط¸ظ¹ ط¸â€‍ط¸â€‍ط·آµط¸â€ ط·آ¯ط¸ث†ط¸â€ڑ - Fund Operation Verification Page
 * ---------------------------------------------------------------
 * ط·ع¾ط·ع¾ط¸ظ¹ط·آ­ ط¸â€‍ط¸â€‍ط¸â€¦ط·آ¯ط¸ظ¹ط·آ± ط·آ¥ط¸ئ’ط¸â€¦ط·آ§ط¸â€‍ ط·آ§ط¸â€‍ط·ع¾ط·آ­ط¸â€ڑط¸â€ڑ ط·آ§ط¸â€‍ط·آ«ط¸â€ ط·آ§ط·آ¦ط¸ظ¹ ط¸â€‍ط¸â€‍ط·آ¹ط¸â€¦ط¸â€‍ط¸ظ¹ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط·آ­ط·آ³ط·آ§ط·آ³ط·آ© ط·آ¹ط¸â€‍ط¸â€° ط·آ§ط¸â€‍ط·آµط¸â€ ط·آ¯ط¸ث†ط¸â€ڑ
 */

// ط·آ§ط¸â€‍ط·ع¾ط·آ­ط¸â€ڑط¸â€ڑ ط¸â€¦ط¸â€  ط·ع¾ط·آ³ط·آ¬ط¸ظ¹ط¸â€‍ ط·آ¯ط·آ®ط¸ث†ط¸â€‍ ط·آ§ط¸â€‍ط¸â€¦ط·آ¯ط¸ظ¹ط·آ±
require_manager_login();

$manager = current_manager();
$managerLicense = (int) $manager['FundManagerNumberofLicense'];

// ط·آ§ط¸â€‍ط·ع¾ط·آ­ط¸â€ڑط¸â€ڑ ط¸â€¦ط¸â€  ط¸ث†ط·آ¬ط¸ث†ط·آ¯ ط·آ¹ط¸â€¦ط¸â€‍ط¸ظ¹ط·آ© ط¸â€¦ط·آ¹ط¸â€‍ط¸â€ڑط·آ©
$pendingVerification = $_SESSION['pending_fund_verification'] ?? null;

if (!$pendingVerification) {
    set_flash('error', 'No pending operation requires verification.');
    redirect_to('manager-dashboard');
}

$fundId = (int) $pendingVerification['fund_id'];
$operationType = $pendingVerification['operation_type'] ?? 'unknown';
$fund = fund_by_id($fundId);

// ط¸â€¦ط·آ¹ط·آ§ط¸â€‍ط·آ¬ط·آ© ط·آ±ط·آ³ط·آ§ط·آ¦ط¸â€‍ Flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

render_head('Verify Operation');
render_responsive_shell_start('');
?>

<div class="verify-operation-page">
    <!-- Header -->
    <div class="page-header">
        <a href="<?= e(route('home')) ?>" class="back-link">Back to Home</a>
        <h1>Two-Step Verification</h1>
        <p>Verify your identity to complete this operation</p>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <div class="verify-container">
        <!-- Operation Details -->
        <div class="operation-details">
            <h2>Operation Details</h2>
            <div class="details-card">
                <div class="detail-row">
                    <span class="label">Operation Type</span>
                    <span class="value operation-type"><?= e(ucwords(str_replace('_', ' ', $operationType))) ?></span>
                </div>
                <?php if ($fund): ?>
                <div class="detail-row">
                    <span class="label">Fund Name</span>
                    <span class="value"><?= e($fund['FundTitle']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Fund ID</span>
                    <span class="value"><?= e($fundId) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="label">Requested By</span>
                    <span class="value"><?= e($manager['FundManagerNameFirst'] . ' ' . $manager['FundManagerNameLast']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Requested At</span>
                    <span class="value"><?= e(date('M d, Y H:i')) ?></span>
                </div>
            </div>

            <div class="security-notice">
                <div class="notice-icon">SEC</div>
                <div class="notice-text">
                    <strong>Security Notice</strong>
                    <p>A verification code has been sent to your registered phone number. Enter it below to proceed.</p>
                </div>
            </div>
        </div>

        <!-- Verification Form -->
        <div class="verification-form-section">
            <h2>Enter Verification Code</h2>
            <form action="<?= e(action_url('verify-fund-operation')) ?>" method="POST" class="verification-form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="fund_id" value="<?= e($fundId) ?>">
                <input type="hidden" name="operation_type" value="<?= e($operationType) ?>">
                
                <div class="code-input-group">
                    <label for="verification_code">4-Digit Code</label>
                    <div class="code-inputs">
                        <input type="text" maxlength="1" class="code-digit" pattern="[0-9]" required onkeyup="moveToNext(this, 1)">
                        <input type="text" maxlength="1" class="code-digit" pattern="[0-9]" required onkeyup="moveToNext(this, 2)">
                        <input type="text" maxlength="1" class="code-digit" pattern="[0-9]" required onkeyup="moveToNext(this, 3)">
                        <input type="text" maxlength="1" class="code-digit" pattern="[0-9]" required onkeyup="moveToNext(this, 4)">
                    </div>
                    <input type="hidden" name="verification_code" id="fullCode">
                </div>

                <div class="code-info">
                    <p>Code sent to: <?= e(mask_phone($manager['FundManagerPhone'])) ?></p>
                    <p class="timer" id="timer">Code expires in: <span id="countdown">10:00</span></p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-verify">
                        Verify and Complete Operation
                    </button>
                </div>

                <div class="resend-section">
                    <p>Didn't receive the code?</p>
                    <button type="button" class="btn-resend" onclick="resendCode()" id="resendBtn">
                        Resend Code
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Help Section -->
    <div class="help-section">
        <h3>Need Help?</h3>
        <div class="help-grid">
            <div class="help-item">
                <h4>Code not received?</h4>
                <p>Check your phone number is correct in your profile. The code may take up to 2 minutes to arrive.</p>
            </div>
            <div class="help-item">
                <h4>Code expired?</h4>
                <p>Click "Resend Code" to receive a new verification code. Each code is valid for 10 minutes.</p>
            </div>
            <div class="help-item">
                <h4>Wrong phone number?</h4>
                <p>Update your phone number in your profile settings, then try again.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Move to next input
function moveToNext(current, nextIndex) {
    if (current.value.length === 1 && nextIndex <= 4) {
        const inputs = document.querySelectorAll('.code-digit');
        if (inputs[nextIndex]) {
            inputs[nextIndex].focus();
        }
    }
    updateFullCode();
}

// Update hidden full code field
function updateFullCode() {
    const inputs = document.querySelectorAll('.code-digit');
    let code = '';
    inputs.forEach(input => code += input.value);
    document.getElementById('fullCode').value = code;
}

// Countdown timer
let timeLeft = 10 * 60; // 10 minutes
const countdownEl = document.getElementById('countdown');
const timerInterval = setInterval(() => {
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    
    if (timeLeft <= 0) {
        clearInterval(timerInterval);
        countdownEl.textContent = 'Expired';
        countdownEl.parentElement.style.color = '#dc3545';
    }
    timeLeft--;
}, 1000);

// Resend code
let resendCooldown = 0;
function resendCode() {
    if (resendCooldown > 0) return;
    
    // In demo mode, just show message
    alert('A new verification code has been sent to your phone.');
    
    // Set cooldown
    resendCooldown = 60;
    const btn = document.getElementById('resendBtn');
    btn.disabled = true;
    
    const cooldownInterval = setInterval(() => {
        resendCooldown--;
        btn.textContent = `Resend in ${resendCooldown}s`;
        if (resendCooldown <= 0) {
            clearInterval(cooldownInterval);
            btn.textContent = 'Resend Code';
            btn.disabled = false;
        }
    }, 1000);
    
    // Reset timer
    timeLeft = 10 * 60;
}

// Handle paste
document.querySelectorAll('.code-digit').forEach((input, index) => {
    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text');
        const digits = paste.replace(/\D/g, '').split('');
        document.querySelectorAll('.code-digit').forEach((inp, i) => {
            if (digits[i]) inp.value = digits[i];
        });
        updateFullCode();
    });
});
</script>


<?php
render_responsive_shell_end();
render_end();
?>





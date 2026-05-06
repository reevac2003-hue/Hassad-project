<?php
/**
 * ملاحظات توثيقية للصفحة: edit-profile.php
 * الغرض: تحديث الملف الشخصي والبيانات البنكية وكلمة المرور.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - current_bank_account(): جلب البطاقة/الحساب البنكي للطالب. القيمة الراجعة: array.
 * - current_manager(): جلب بيانات المدير الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - current_student(): جلب بيانات الطالب الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - mask_card(): إخفاء جزء من رقم البطاقة عند العرض. القيمة الراجعة: string.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
/**
 * Edit Profile Page
 * --------------------------------
 * Allows students and managers to update their profile information
 */

// Check login status
$isStudent = is_logged_in_as_student();
$isManager = is_logged_in_as_manager();

if (!$isStudent && !$isManager) {
    set_flash('error', 'Please login to access your profile.');
    redirect_to('login');
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

if ($isStudent) {
    $student = current_student();
    $bank = current_bank_account((int) $student['StudentID']);
    $bankExpiryMonth = '';
    if (!empty($bank['CreditCardDayExpired'])) {
        $ts = strtotime((string) $bank['CreditCardDayExpired']);
        if ($ts !== false) {
            $bankExpiryMonth = date('Y-m', $ts);
        }
    }
    $pageTitle = 'Edit Profile';
    $dashboardRoute = 'home';
} else {
    $manager = current_manager();
    $pageTitle = 'Edit Profile';
    $dashboardRoute = 'home';
}

render_head($pageTitle);
render_responsive_shell_start($pageTitle, true);
?>

<div class="edit-profile-page">
    <header class="ep-hero">
        <div class="ep-hero-copy">
            <p class="ep-kicker">Account Center</p>
            <h1><?= e($pageTitle) ?></h1>
            <p>Manage your personal information, security settings, and payment details from one secure workspace.</p>
        </div>
       
    </header>

    <!-- Success/Error Messages -->
    <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($isStudent): ?>
    <section class="profile-edit-card ep-card">
        <div class="card-header">
            <h2>Personal Information</h2>
            <p>Keep your profile details up to date for account verification and communication</p>
        </div>
        
        <form class="profile-form" method="post" action="<?= e(action_url('update-profile')) ?>">
            <input type="hidden" name="user_type" value="student">
            
            <div class="form-grid two-col">
                <div class="field">
                    <label>First Name</label>
                    <input class="text-input" type="text" name="first_name" value="<?= e($student['StudentNameFirst']) ?>" required>
                </div>
                <div class="field">
                    <label>Last Name</label>
                    <input class="text-input" type="text" name="last_name" value="<?= e($student['StudentNameLast']) ?>" required>
                </div>
                <div class="field">
                    <label>Student ID</label>
                    <input class="text-input" type="text" value="<?= e($student['StudentID']) ?>" disabled>
                    <small class="field-hint">Student ID cannot be changed</small>
                </div>
                <div class="field">
                    <label>Phone Number</label>
                    <input class="text-input" type="tel" name="phone" value="<?= e($student['StudentPhoneNumber']) ?>" required>
                </div>
                <div class="field">
                    <label>Campus</label>
                    <input class="text-input" type="text" name="campus" value="<?= e($student['StudentCampus']) ?>" required>
                </div>
                <div class="field">
                    <label>Academic Degree</label>
                    <select class="select-input" name="degree">
                        <option value="Bachelor" <?= $student['StudentAcademicDegree'] === 'Bachelor' ? 'selected' : '' ?>>Bachelor</option>
                        <option value="Master" <?= $student['StudentAcademicDegree'] === 'Master' ? 'selected' : '' ?>>Master</option>
                        <option value="PhD" <?= $student['StudentAcademicDegree'] === 'PhD' ? 'selected' : '' ?>>PhD</option>
                        <option value="Diploma" <?= $student['StudentAcademicDegree'] === 'Diploma' ? 'selected' : '' ?>>Diploma</option>
                    </select>
                </div>
                <div class="field full-width">
                    <label>Major</label>
                    <input class="text-input" type="text" name="major" value="<?= e($student['StudentMajor']) ?>" required>
                </div>
            </div>
            
            <div class="form-actions ep-form-actions">
                <a href="<?= e(route($dashboardRoute)) ?>" class="btn-secondary btn-oval">Cancel</a>
                <button type="submit" class="cta-primary btn-oval">Save Changes</button>
            </div>
        </form>
    </section>

    <section class="profile-edit-card ep-card">
        <div class="card-header">
            <h2>Bank Information</h2>
            <p>Update your linked payment details used for subscriptions and withdrawals</p>
        </div>
        
        <form class="profile-form" method="post" action="<?= e(action_url('update-bank')) ?>">
            <div class="form-grid two-col">
                <div class="field full-width">
                    <label>Bank Account IBAN</label>
                    <input class="text-input" type="text" name="iban" value="<?= e($bank['BankAccountIban'] ?? '') ?>" placeholder="SA00 0000 0000 0000 0000 0000" required pattern="SA\d{22}" title="IBAN must be SA followed by 22 digits" maxlength="24" inputmode="text">
                </div>
                <div class="field">
                    <label>Card Holder Name</label>
                    <input class="text-input" type="text" name="card_name" value="<?= e($bank['CreditCardName'] ?? '') ?>" required pattern="[A-Za-z\u0600-\u06FF ]{2,100}" title="Card holder name must contain letters only" maxlength="100">
                </div>
                <div class="field">
                    <label>Card Number</label>
                    <input class="text-input" type="text" name="card_number" value="<?= e((string) ($bank['CreditCardNumber'] ?? '')) ?>" placeholder="14-digit card number" maxlength="14" pattern="\d{14}" inputmode="numeric" required>
                    <small class="field-hint">Enter your linked 14-digit card number.</small>
                </div>
                <div class="field">
                    <label>Card Expiry Date</label>
                    <input class="text-input" type="month" name="expiry" value="<?= e($bankExpiryMonth) ?>" required>
                    <small class="field-hint">Required when adding or replacing a card.</small>
                </div>
                <div class="field">
                    <label>Card CVV</label>
                    <input class="text-input" type="text" name="cvv" value="<?= e(((int) ($bank['CreditCardCVV'] ?? 0)) > 0 ? str_pad((string) ((int) $bank['CreditCardCVV']), 3, '0', STR_PAD_LEFT) : '') ?>" maxlength="3" pattern="\d{3}" inputmode="numeric" placeholder="3-digit CVV" required>
                    <small class="field-hint">You can change CVV and save it.</small>
                </div>
            </div>
            
            <div class="form-actions ep-form-actions">
                <button type="submit" class="cta-primary btn-oval">Update Bank Info</button>
            </div>
        </form>
    </section>

    <section class="profile-edit-card ep-card">
        <div class="card-header">
            <h2>Change Password</h2>
            <p>Protect your account with a stronger password and regular updates</p>
        </div>
        
        <form class="profile-form ep-password-form" method="post" action="<?= e(action_url('change-password')) ?>">
            <input type="hidden" name="user_type" value="student">
            
            <div class="form-grid two-col">
                <div class="field">
                    <label>Current Password</label>
                    <input class="text-input" type="password" name="current_password" id="current_password" required>
                    <a href="<?= e(route('student-settings')) ?>" class="forgot-password-link">Forgot Password?</a>
                </div>
                <div class="field">
                    <label>New Password</label>
                    <input class="text-input" type="password" name="new_password" id="new_password" required oninput="checkPasswordStrength(this.value)">
                </div>
                <div class="field">
                    <label>Confirm New Password</label>
                    <input class="text-input" type="password" name="confirm_password" id="confirm_password" required>
                </div>
                <div class="field">
                    <label class="helper-text">Password Requirements</label>
                    <ul class="hint-list password-checklist">
                        <li id="req-length" class="req-item req-pending">Not met: At least 8 characters</li>
                        <li id="req-upper" class="req-item req-pending">Not met: Include one uppercase letter</li>
                        <li id="req-special" class="req-item req-pending">Not met: Include one special character</li>
                        <li id="req-number" class="req-item req-pending">Not met: Include a number</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-actions ep-form-actions">
                <button type="submit" class="cta-primary btn-oval">Change Password</button>
            </div>
        </form>
    </section>

    <?php else: ?>
    <section class="profile-edit-card ep-card">
        <div class="card-header">
            <h2>Personal Information</h2>
            <p>Keep your manager profile accurate for compliance and communication</p>
        </div>
        
        <form class="profile-form" method="post" action="<?= e(action_url('update-profile')) ?>">
            <input type="hidden" name="user_type" value="manager">
            
            <div class="form-grid two-col">
                <div class="field">
                    <label>First Name</label>
                    <input class="text-input" type="text" name="first_name" value="<?= e($manager['FundManagerNameFirst']) ?>" required>
                </div>
                <div class="field">
                    <label>Last Name</label>
                    <input class="text-input" type="text" name="last_name" value="<?= e($manager['FundManagerNameLast']) ?>" required>
                </div>
                <div class="field">
                    <label>License Number</label>
                    <input class="text-input" type="text" value="<?= e($manager['FundManagerNumberofLicense']) ?>" disabled>
                    <small class="field-hint">License number cannot be changed</small>
                </div>
                <div class="field">
                    <label>Phone Number</label>
                    <input class="text-input" type="tel" name="phone" value="<?= e($manager['FundManagerPhone']) ?>" required>
                </div>
            </div>
            
            <div class="form-actions ep-form-actions">
                <a href="<?= e(route($dashboardRoute)) ?>" class="btn-secondary btn-oval">Cancel</a>
                <button type="submit" class="cta-primary btn-oval">Save Changes</button>
            </div>
        </form>
    </section>

    <section class="profile-edit-card ep-card">
        <div class="card-header">
            <h2>Change Password</h2>
            <p>Use a strong password to secure manager operations</p>
        </div>
        
        <form class="profile-form ep-password-form" method="post" action="<?= e(action_url('change-password')) ?>">
            <input type="hidden" name="user_type" value="manager">
            
            <div class="form-grid two-col">
                <div class="field">
                    <label>Current Password</label>
                    <input class="text-input" type="password" name="current_password" required>
                </div>
                <div class="field">
                    <label>New Password</label>
                    <input class="text-input" type="password" name="new_password" required>
                </div>
                <div class="field">
                    <label>Confirm New Password</label>
                    <input class="text-input" type="password" name="confirm_password" required>
                </div>
                <div class="field">
                    <label class="helper-text">Password Requirements</label>
                    <ul class="hint-list password-checklist">
                        <li class="req-item req-pending">Not met: At least 8 characters</li>
                        <li class="req-item req-pending">Not met: Include one uppercase letter</li>
                        <li class="req-item req-pending">Not met: Include one special character</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-actions ep-form-actions">
                <button type="submit" class="cta-primary btn-oval">Change Password</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

</div>

<script>
function checkPasswordStrength(val) {
    const rules = [
        { id: 'req-length', ok: val.length >= 8, label: 'At least 8 characters' },
        { id: 'req-upper', ok: /[A-Z]/.test(val), label: 'Include one uppercase letter' },
        { id: 'req-special', ok: /[^a-zA-Z0-9]/.test(val), label: 'Include one special character' },
        { id: 'req-number', ok: /[0-9]/.test(val), label: 'Include a number' }
    ];

    rules.forEach(function(rule) {
        const el = document.getElementById(rule.id);
        if (!el) return;
        el.textContent = (rule.ok ? 'Met' : 'Not met') + ': ' + rule.label;
        el.classList.toggle('req-met', rule.ok);
        el.classList.toggle('req-pending', !rule.ok);
    });
}
</script>

<?php
render_responsive_shell_end();
render_end();
?>



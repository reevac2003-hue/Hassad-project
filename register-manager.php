<?php
/**
 * ملاحظات توثيقية للصفحة: register-manager.php
 * الغرض: تسجيل مدير جديد.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - is_logged_in_as_admin(): التحقق من أن الجلسة الحالية لإداري. القيمة الراجعة: bool.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
// ط¸ظ¾ط¸â€ڑط·آ· ط·آ§ط¸â€‍ط·آ£ط·آ¯ط¸â€¦ط¸â€  ط¸ظ¹ط¸â€ڑط·آ¯ط·آ± ط¸ظ¹ط¸â€ ط·آ´ط·آ¦ ط·آ­ط·آ³ط·آ§ط·آ¨ ط¸â€¦ط·آ¯ط¸ظ¹ط·آ±
if (!is_logged_in_as_admin()) {
    set_flash('error', 'Access denied. Only admin can create manager accounts.');
    redirect_to('login', ['role' => 'admin']);
}
render_head('Create Fund Manager Account');
render_responsive_shell_start('Create Fund Manager Account');
?>
<form class="form-stack form-stack-compact" method="post" action="<?= e(action_url('register-manager')) ?>">
    <div class="form-grid two-col">
        <div class="field">
            <label>First Name:</label>
            <input class="text-input" name="first_name" placeholder="Enter first name" required>
        </div>
        <div class="field">
            <label>Upload contract:</label>
            <input class="text-input" name="contract_name" placeholder="Contract file name">
        </div>
        <div class="field">
            <label>Last Name:</label>
            <input class="text-input" name="last_name" placeholder="Enter last name" required>
        </div>
        <div class="field input-with-icon">
            <label>Password:</label>
            <input class="text-input" type="password" name="password" placeholder="Enter password" required>
            <span class="input-icon">&#128065;</span>
        </div>
        <div class="field">
            <label>ID License:</label>
            <input class="text-input" name="license" placeholder="Enter license number" required>
        </div>
        <div class="field">
            <label class="helper-text">Password Requirements</label>
            <ul class="hint-list">
                <li>At least one uppercase letter.</li>
                <li>At least one special character.</li>
                <li>8+ characters and one number.</li>
            </ul>
        </div>
        <div class="field">
            <label>Phone Number:</label>
            <input class="text-input" name="phone" placeholder="Enter phone number" required>
        </div>
        <div class="field input-with-icon">
            <label>Confirm Password:</label>
            <input class="text-input" type="password" name="confirm_password" placeholder="Confirm password" required>
            <span class="input-icon">&#128065;</span>
        </div>
    </div>
    <div class="auth-actions">
        <a class="small-btn" href="<?= e(route('home')) ?>">Cancel</a>
        <button class="cta-primary" type="submit">Create Manager Account</button>
    </div>
</form>
<?php
render_responsive_shell_end();
render_end();
?>



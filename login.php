<?php
/**
 * ملاحظات توثيقية للصفحة: login.php
 * الغرض: واجهة تسجيل الدخول بحسب الدور.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 */
$selectedRole = trim((string) ($_GET['role'] ?? 'student'));
$selectedRole = in_array($selectedRole, ['student', 'manager', 'admin'], true) ? $selectedRole : 'student';
$identity = '';
$password = '';

render_head('Login');
render_responsive_shell_start('');
?>
<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="login-icon">IN</div>
            <h1 class="login-title">Welcome Back</h1>
            <p class="login-subtitle">Choose your account type to sign in</p>
        </div>

        <!-- Role Tabs -->
        <div class="login-role-tabs">
            <button type="button" class="login-role-tab<?= $selectedRole === 'student' ? ' active' : '' ?>" data-role="student" data-label="Student ID" data-placeholder="Enter your Student ID" data-icon="ID">Student</button>
            <button type="button" class="login-role-tab<?= $selectedRole === 'manager' ? ' active' : '' ?>" data-role="manager" data-label="License Number" data-placeholder="Enter your License Number" data-icon="LIC">Manager</button>
            <button type="button" class="login-role-tab<?= $selectedRole === 'admin' ? ' active' : '' ?>" data-role="admin" data-label="Username" data-placeholder="Enter your Username" data-icon="ADM">Admin</button>
        </div>
        
        <form class="login-form" method="post" action="<?= e(action_url('login')) ?>">
            <input type="hidden" name="role" id="login-role-input" value="<?= e($selectedRole) ?>">
            
            <div class="field">
                <label class="field-label" id="identity-label">
                    <span class="label-icon" id="identity-icon"><?= $selectedRole === 'manager' ? 'LIC' : ($selectedRole === 'admin' ? 'ADM' : 'ID') ?></span>
                    <span id="identity-label-text"><?= $selectedRole === 'manager' ? 'License Number' : ($selectedRole === 'admin' ? 'Username' : 'Student ID') ?></span>
                </label>
                <input class="text-input" name="identity" id="identity-input" value="<?= e((string) $identity) ?>"
                       placeholder="<?= $selectedRole === 'manager' ? 'Enter your License Number' : ($selectedRole === 'admin' ? 'Enter your Username' : 'Enter your Student ID') ?>" required>
            </div>
            
            <div class="field input-with-icon">
                <label class="field-label">
                    <span class="label-icon">PW</span>
                    Password
                </label>
                <input class="text-input" type="password" name="password" value="<?= e((string) $password) ?>"
                       placeholder="Enter your password" required>
                <span class="input-icon toggle-password" title="Show/Hide">Show</span>
            </div>
            
            <div class="form-actions">
                <button class="cta-primary login-btn" type="submit">
                    <span>Sign In</span>
                    <span class="btn-arrow">Go</span>
                </button>
            </div>
            
            <div class="form-footer">
                <a class="link-subtle" href="<?= e(route('home')) ?>">Back to Home</a>
                <span class="separator">|</span>
                <a class="link-subtle" href="<?= e(route('register-student')) ?>">Create Account</a>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.login-role-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.login-role-tab').forEach(function(t) { t.classList.remove('active'); });
        tab.classList.add('active');
        document.getElementById('login-role-input').value = tab.dataset.role;
        document.getElementById('identity-label-text').textContent = tab.dataset.label;
        document.getElementById('identity-icon').textContent = tab.dataset.icon;
        var input = document.getElementById('identity-input');
        input.placeholder = tab.dataset.placeholder;
        input.value = '';
        input.focus();
    });
});
</script>
<?php
render_responsive_shell_end();
render_end();
?>



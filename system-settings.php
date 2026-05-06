<?php
/**
 * ملاحظات توثيقية للصفحة: system-settings.php
 * الغرض: إعدادات النظام العامة للإدارة.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - current_admin(): جلب بيانات الإداري الحالي. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - get_system_setting(): قراءة قيمة إعداد من إعدادات النظام. القيمة الراجعة: mixed.
 * - is_logged_in_as_admin(): التحقق من أن الجلسة الحالية لإداري. القيمة الراجعة: bool.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
/**
 * System Settings - ط·آ¥ط·آ¹ط·آ¯ط·آ§ط·آ¯ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€ ط·آ¸ط·آ§ط¸â€¦
 * ---------------------------------
 * Admin system configuration page
 */

if (!is_logged_in_as_admin()) {
    set_flash('error', 'Access denied. Admin login required.');
    redirect_to('login', ['role' => 'admin']);
}

$admin = current_admin();

// Get current settings (with defaults)
$settings = [
    'site_name' => get_system_setting('site_name') ?? 'Hassad Investment Platform',
    'site_description' => get_system_setting('site_description') ?? 'Student Investment Fund Management',
    'min_subscription' => get_system_setting('min_subscription') ?? '100',
    'max_subscription' => get_system_setting('max_subscription') ?? '10000',
    'withdrawal_limit_daily' => get_system_setting('withdrawal_limit_daily') ?? '5000',
    'withdrawal_limit_monthly' => get_system_setting('withdrawal_limit_monthly') ?? '50000',
    'sms_enabled' => get_system_setting('sms_enabled') ?? '1',
    'self_ban_enabled' => get_system_setting('self_ban_enabled') ?? '1',
    'two_step_verification' => get_system_setting('two_step_verification') ?? '1',
    'maintenance_mode' => get_system_setting('maintenance_mode') ?? '0',
];

render_head('System Settings');
render_responsive_shell_start('');
?>

<div class="settings-page">
    <div class="page-header">
        <a href="<?= e(route('home')) ?>" class="back-link">Back to Home</a>
        <h1>System Settings</h1>
        <p>Configure platform settings and parameters</p>
    </div>

    <form action="<?= e(action_url('update-system')) ?>" method="POST" class="settings-form">
        <!-- General Settings -->
        <div class="settings-section">
            <h3>General Settings</h3>
            
            <div class="form-group">
                <label for="site_name">Site Name</label>
                <input type="text" id="site_name" name="settings[site_name]" value="<?= e($settings['site_name']) ?>">
            </div>

            <div class="form-group">
                <label for="site_description">Site Description</label>
                <textarea id="site_description" name="settings[site_description]" rows="2"><?= e($settings['site_description']) ?></textarea>
            </div>
        </div>

        <!-- Subscription Settings -->
        <div class="settings-section">
            <h3>Subscription Limits</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="min_subscription">Minimum Subscription (SAR)</label>
                    <input type="number" id="min_subscription" name="settings[min_subscription]" value="<?= e($settings['min_subscription']) ?>" min="0">
                </div>
                <div class="form-group">
                    <label for="max_subscription">Maximum Subscription (SAR)</label>
                    <input type="number" id="max_subscription" name="settings[max_subscription]" value="<?= e($settings['max_subscription']) ?>" min="0">
                </div>
            </div>
        </div>

        <!-- Withdrawal Settings -->
        <div class="settings-section">
            <h3>Withdrawal Limits</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="withdrawal_limit_daily">Daily Withdrawal Limit (SAR)</label>
                    <input type="number" id="withdrawal_limit_daily" name="settings[withdrawal_limit_daily]" value="<?= e($settings['withdrawal_limit_daily']) ?>" min="0">
                </div>
                <div class="form-group">
                    <label for="withdrawal_limit_monthly">Monthly Withdrawal Limit (SAR)</label>
                    <input type="number" id="withdrawal_limit_monthly" name="settings[withdrawal_limit_monthly]" value="<?= e($settings['withdrawal_limit_monthly']) ?>" min="0">
                </div>
            </div>
        </div>

        <!-- Feature Toggles -->
        <div class="settings-section">
            <h3>Feature Toggles</h3>
            
            <div class="toggles-grid">
                <label class="toggle-item">
                    <input type="hidden" name="settings[sms_enabled]" value="0">
                    <input type="checkbox" name="settings[sms_enabled]" value="1" <?= $settings['sms_enabled'] == '1' ? 'checked' : '' ?>>
                    <span class="toggle-label">
                        <span class="toggle-icon">SMS</span>
                        <span class="toggle-text">
                            <strong>SMS Notifications</strong>
                            <small>Enable SMS alerts and notifications</small>
                        </span>
                    </span>
                </label>

                <label class="toggle-item">
                    <input type="hidden" name="settings[self_ban_enabled]" value="0">
                    <input type="checkbox" name="settings[self_ban_enabled]" value="1" <?= $settings['self_ban_enabled'] == '1' ? 'checked' : '' ?>>
                    <span class="toggle-label">
                        <span class="toggle-icon">SB</span>
                        <span class="toggle-text">
                            <strong>Self-Ban Feature</strong>
                            <small>Allow students to self-ban their accounts</small>
                        </span>
                    </span>
                </label>

                <label class="toggle-item">
                    <input type="hidden" name="settings[two_step_verification]" value="0">
                    <input type="checkbox" name="settings[two_step_verification]" value="1" <?= $settings['two_step_verification'] == '1' ? 'checked' : '' ?>>
                    <span class="toggle-label">
                        <span class="toggle-icon">2FA</span>
                        <span class="toggle-text">
                            <strong>Two-Step Verification</strong>
                            <small>Require verification for fund operations</small>
                        </span>
                    </span>
                </label>

                <label class="toggle-item warning">
                    <input type="hidden" name="settings[maintenance_mode]" value="0">
                    <input type="checkbox" name="settings[maintenance_mode]" value="1" <?= $settings['maintenance_mode'] == '1' ? 'checked' : '' ?>>
                    <span class="toggle-label">
                        <span class="toggle-icon">MT</span>
                        <span class="toggle-text">
                            <strong>Maintenance Mode</strong>
                            <small>Disable public access during maintenance</small>
                        </span>
                    </span>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-save">Save Settings</button>
        </div>
    </form>

    <!-- System Info -->
    <div class="system-info">
        <h3>System Information</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">PHP Version</span>
                <span class="info-value"><?= e(phpversion()) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Server Time</span>
                <span class="info-value"><?= e(date('Y-m-d H:i:s')) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Data Source</span>
                <span class="info-value">Database Only</span>
            </div>
            <div class="info-item">
                <span class="info-label">Admin User</span>
                <span class="info-value"><?= e($admin['UserName']) ?></span>
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="danger-zone">
        <h3>Danger Zone</h3>
        <p>These actions are irreversible. Proceed with caution.</p>
        
        <div class="danger-actions">
            <form action="<?= e(action_url('clear-audit-logs')) ?>" method="POST" class="u-inline-59">
                <button type="submit" class="btn-danger" onclick="return confirm('Clear all audit logs?')">
                    Clear Audit Logs
                </button>
            </form>
        </div>
    </div>
</div>


<?php
render_responsive_shell_end();
render_end();
?>






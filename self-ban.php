<?php
/**
 * ملاحظات توثيقية للصفحة: self-ban.php
 * الغرض: إدارة التجميد الذاتي لحساب الطالب.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - current_student(): جلب بيانات الطالب الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - is_user_banned(): التحقق من وجود حظر نشط للمستخدم. القيمة الراجعة: bool.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - require_student_login(): فرض تسجيل الدخول كطالب قبل متابعة التنفيذ. القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 */
/**
 * ط·آµط¸ظ¾ط·آ­ط·آ© ط·آ§ط¸â€‍ط·آ­ط·آ¸ط·آ± ط·آ§ط¸â€‍ط·آ°ط·آ§ط·ع¾ط¸ظ¹ - Self-Ban Page
 * ----------------------------------
 * ط·ع¾ط·ع¾ط¸ظ¹ط·آ­ ط¸â€‍ط¸â€‍ط·آ·ط·آ§ط¸â€‍ط·آ¨ ط·آ­ط·آ¸ط·آ± ط¸â€ ط¸ظ¾ط·آ³ط¸â€، ط¸â€¦ط¸â€  ط·آ§ط¸â€‍ط¸â€ ط·آ¸ط·آ§ط¸â€¦ ط¸â€‍ط¸ظ¾ط·ع¾ط·آ±ط·آ© ط¸â€¦ط·آ­ط·آ¯ط·آ¯ط·آ©
 * ط¸â€‍ط·آ­ط¸â€¦ط·آ§ط¸ظ¹ط·آ© ط¸â€ ط¸ظ¾ط·آ³ط¸â€، ط¸â€¦ط¸â€  ط·آ§ط¸â€‍ط¸â€ڑط·آ±ط·آ§ط·آ±ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·ع¾ط·آ³ط·آ±ط·آ¹ط·آ©
 */

// ط·آ§ط¸â€‍ط·ع¾ط·آ­ط¸â€ڑط¸â€ڑ ط¸â€¦ط¸â€  ط·ع¾ط·آ³ط·آ¬ط¸ظ¹ط¸â€‍ ط·آ¯ط·آ®ط¸ث†ط¸â€‍ ط·آ§ط¸â€‍ط·آ·ط·آ§ط¸â€‍ط·آ¨
require_student_login();

$student = current_student();
$studentId = (int) $student['StudentID'];

// ط·آ§ط¸â€‍ط·ع¾ط·آ­ط¸â€ڑط¸â€ڑ ط·آ¥ط·آ°ط·آ§ ط¸ئ’ط·آ§ط¸â€  ط·آ§ط¸â€‍ط·آ·ط·آ§ط¸â€‍ط·آ¨ ط¸â€¦ط·آ­ط·آ¸ط¸ث†ط·آ±ط·آ§ط¸â€¹ ط·آ­ط·آ§ط¸â€‍ط¸ظ¹ط·آ§ط¸â€¹
$isBanned = is_user_banned($studentId);

// ط¸â€¦ط·آ¹ط·آ§ط¸â€‍ط·آ¬ط·آ© ط·آ±ط·آ³ط·آ§ط·آ¦ط¸â€‍ Flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

render_head('Self-Ban');
render_responsive_shell_start('');
?>

<div class="self-ban-page">
    <!-- Header -->
    <div class="page-header">
        <a href="<?= e(route('home')) ?>" class="back-link">Back to Home</a>
        <h1>Self-Ban Protection</h1>
        <p>Protect yourself from impulsive investment decisions</p>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($isBanned): ?>
    <!-- Already Banned -->
    <div class="banned-notice">
        <div class="notice-icon">ON</div>
        <h2>Self-Ban Active</h2>
        <p>Your account is currently self-banned. You cannot make new investments or subscriptions during this period.</p>
        <p>The ban will be automatically lifted based on your selected duration.</p>
        <div class="ban-info">
            <p><strong>Note:</strong> You can still view your existing investments and account balance.</p>
        </div>
    </div>
    <?php else: ?>
    <!-- Self-Ban Form -->
    <div class="ban-container">
        <div class="warning-section">
            <div class="warning-icon">WARN</div>
            <h2>What is Self-Ban?</h2>
            <p>Self-ban is a responsible investment tool that allows you to temporarily restrict your ability to make new investments. This feature helps:</p>
            <ul class="benefits-list">
                <li>Prevent impulsive investment decisions</li>
                <li>Protect your savings during emotional periods</li>
                <li>Take time to reconsider your investment strategy</li>
                <li>Focus on studies without investment distractions</li>
            </ul>
        </div>

        <div class="form-section">
            <h2>Request Self-Ban</h2>
            <form action="<?= e(action_url('self-ban')) ?>" method="POST" class="self-ban-form" onsubmit="return confirmBan()">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                
                <div class="form-group">
                    <label for="duration">Ban Duration</label>
                    <select id="duration" name="duration" required>
                        <option value="">Select duration...</option>
                        <option value="1_day">1 Day - Cool down period</option>
                        <option value="3_days">3 Days - Short break</option>
                        <option value="1_week">1 Week - Study focus</option>
                        <option value="2_weeks">2 Weeks - Extended break</option>
                        <option value="1_month">1 Month - Long-term protection</option>
                        <option value="end_of_semester">Until End of Semester</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Self-Ban</label>
                    <textarea id="reason" name="reason" rows="3" placeholder="Optional: Why are you choosing to self-ban?"></textarea>
                    <small>This information is private and helps us improve our services.</small>
                </div>

                <div class="confirm-section">
                    <label class="checkbox-label">
                        <input type="checkbox" id="confirm_understand" required>
                        <span>I understand that once activated, the self-ban cannot be removed early</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="confirm_voluntary" required>
                        <span>I am voluntarily requesting this self-ban to protect myself</span>
                    </label>
                </div>

                <button type="submit" class="btn-ban">
                    Activate Self-Ban
                </button>
            </form>
        </div>
    </div>

    <!-- FAQ Section -->
    <div class="faq-section">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-list">
            <div class="faq-item">
                <h4>Can I cancel the self-ban early?</h4>
                <p>No. The self-ban cannot be removed early to ensure maximum protection. You must wait for the selected duration to end.</p>
            </div>
            <div class="faq-item">
                <h4>What happens to my existing investments?</h4>
                <p>Your existing investments remain active. You can still view them and receive any returns. You just cannot make new subscriptions.</p>
            </div>
            <div class="faq-item">
                <h4>Can I withdraw my profits during the ban?</h4>
                <p>Yes, you can still request withdrawals of your profits during the self-ban period.</p>
            </div>
            <div class="faq-item">
                <h4>Who can see my self-ban status?</h4>
                <p>Only system administrators can see that you have self-banned. This information is not shared with other users.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function confirmBan() {
    const duration = document.getElementById('duration').value;
    const durationText = document.getElementById('duration').options[document.getElementById('duration').selectedIndex].text;
    
    return confirm(`Are you sure you want to self-ban for: ${durationText}?\n\nThis action cannot be undone early.`);
}
</script>


<?php
render_responsive_shell_end();
render_end();
?>





<?php
/**
 * ملاحظات توثيقية للصفحة: send-notification.php
 * الغرض: إرسال الإشعارات/الرسائل ومراجعة السجل.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - all_managers(): جلب قائمة المديرين. القيمة الراجعة: array.
 * - all_students(): جلب قائمة الطلاب. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - get_sms_logs(): جلب سجل رسائل SMS المرسلة. القيمة الراجعة: array.
 * - is_logged_in_as_admin(): التحقق من أن الجلسة الحالية لإداري. القيمة الراجعة: bool.
 * - mask_phone(): إخفاء جزء من رقم الهاتف عند العرض. القيمة الراجعة: string.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
/**
 * Send Notification - ط·آ¥ط·آ±ط·آ³ط·آ§ط¸â€‍ ط·آ¥ط·آ´ط·آ¹ط·آ§ط·آ± SMS
 * ------------------------------------
 * Admin SMS notification management
 */

if (!is_logged_in_as_admin()) {
    set_flash('error', 'Access denied. Admin login required.');
    redirect_to('login', ['role' => 'admin']);
}

$students = all_students();
$managers = all_managers();
$smsLogs = get_sms_logs();

render_head('Send Notification');
render_responsive_shell_start('');
?>

<div class="notification-page">
    <div class="page-header">
        <a href="<?= e(route('home')) ?>" class="back-link">Back to Home</a>
        <h1>Send SMS Notification</h1>
        <p>Send SMS messages to users</p>
    </div>

    <div class="notification-container">
        <div class="send-section">
            <form action="<?= e(action_url('send-sms')) ?>" method="POST" class="sms-form">
                <div class="form-section">
                    <h3>Compose Message</h3>

                    <div class="form-group">
                        <label for="recipient_type">Recipient Type</label>
                        <select id="recipient_type" name="recipient_type" required onchange="toggleRecipient(this.value)">
                            <option value="single">Single Recipient</option>
                            <option value="all_students">All Students</option>
                            <option value="all_managers">All Fund Managers</option>
                            <option value="all">All Users</option>
                        </select>
                    </div>

                    <div class="form-group" id="single-recipient">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" placeholder="05XXXXXXXX">
                        <small>Enter Saudi phone number (starting with 05)</small>
                    </div>

                    <div class="form-group">
                        <label for="message_type">Message Type</label>
                        <select id="message_type" name="message_type" required>
                            <option value="Notification">General Notification</option>
                            <option value="Alert">Important Alert</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Verification">Verification</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">Message Content</label>
                        <textarea id="message" name="message" rows="4" maxlength="160" 
                                  placeholder="Type your message here..." required
                                  oninput="updateCharCount()"></textarea>
                        <div class="char-count">
                            <span id="charCount">0</span>/160 characters
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-send">Send Message</button>
                </div>
            </form>

            <!-- Recipients Preview -->
            <div class="recipients-preview" id="recipients-preview" class="u-inline-75">
                <h4>Recipients Preview</h4>
                <div class="preview-content" id="preview-content"></div>
            </div>
        </div>

        <div class="logs-section">
            <h3>Recent SMS Logs</h3>
            <div class="logs-list">
                <?php foreach (array_slice($smsLogs, 0, 10) as $log): ?>
                <div class="log-item">
                    <div class="log-header">
                        <span class="log-type"><?= e($log['MessageType']) ?></span>
                        <span class="log-status status-<?= strtolower($log['Status']) ?>"><?= e($log['Status']) ?></span>
                    </div>
                    <div class="log-content">
                        <p class="log-message"><?= e(substr($log['MessageContent'], 0, 50)) ?>...</p>
                        <span class="log-recipient">To: <?= e(mask_phone($log['RecipientPhone'])) ?></span>
                    </div>
                    <div class="log-footer">
                        <span class="log-date"><?= e(date('M d, H:i', strtotime($log['SentAt']))) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($smsLogs)): ?>
                <div class="no-logs">
                    <p>No SMS logs yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRecipient(type) {
    const singleRecipient = document.getElementById('single-recipient');
    const preview = document.getElementById('recipients-preview');
    const previewContent = document.getElementById('preview-content');
    
    if (type === 'single') {
        singleRecipient.style.display = 'block';
        preview.style.display = 'none';
    } else {
        singleRecipient.style.display = 'none';
        preview.style.display = 'block';
        
        let count = 0;
        let label = '';
        
        if (type === 'all_students') {
            count = <?= count($students) ?>;
            label = 'students';
        } else if (type === 'all_managers') {
            count = <?= count($managers) ?>;
            label = 'fund managers';
        } else if (type === 'all') {
            count = <?= count($students) + count($managers) ?>;
            label = 'users';
        }
        
        previewContent.innerHTML = `<p>Message will be sent to <strong>${count}</strong> ${label}</p>`;
    }
}

function updateCharCount() {
    const message = document.getElementById('message');
    document.getElementById('charCount').textContent = message.value.length;
}
</script>


<?php
render_responsive_shell_end();
render_end();
?>






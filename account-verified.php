<?php
/**
 * ملاحظات توثيقية للصفحة: account-verified.php
 * الغرض: عرض حالة تأكيد/توثيق الحساب بعد التسجيل.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 */
$loggedIn = is_logged_in_as_student() || is_logged_in_as_manager();
$target = route('home');
if (!$loggedIn) {
    $target = route('login');
}
$buttonText = $loggedIn ? 'Continue' : 'Login';
render_head('Account Verified');
render_responsive_shell_start('Account Verified');
?>
<div class="state-stack">
    <div class="status-icon">&#10003;</div>
    <h2>Account Successfully Created</h2>
    <p>Your account has been successfully created. You can now log in.</p>
    <div class="button-row">
        <a class="cta-primary" href="<?= e($target) ?>"><?= e($buttonText) ?></a>
    </div>
</div>
<?php
render_responsive_shell_end();
render_end();
?>



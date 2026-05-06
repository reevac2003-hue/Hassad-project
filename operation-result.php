<?php
/**
 * ملاحظات توثيقية للصفحة: operation-result.php
 * الغرض: عرض نتيجة عملية منجزة ورسائل الحالة.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - is_logged_in_as_admin(): التحقق من أن الجلسة الحالية لإداري. القيمة الراجعة: bool.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
if (!is_logged_in_as_student() && !is_logged_in_as_manager() && !is_logged_in_as_admin()) {
    set_flash('error', 'Please login first.');
    redirect_to('login');
}

$op = (string) ($_GET['op'] ?? '');
$slug = (string) ($_GET['slug'] ?? '');

$title = 'Operation Completed';
$subtitle = 'Your request has been completed successfully.';
$primaryLabel = 'Go to Home';
$primaryHref = route('home');
$secondaryLabel = 'Back';
$secondaryHref = 'javascript:history.back()';

if ($op === 'cancel-subscription') {
    $title = 'Subscription Cancelled';
    $subtitle = 'The cancellation was completed successfully and your account remains active.';
    $primaryLabel = 'View Fund';
    $primaryHref = $slug !== '' ? route('fund', ['slug' => $slug]) : route('search-funds');
    $secondaryLabel = 'Open Wallet';
    $secondaryHref = route('wallet');
} elseif ($op === 'undo-fund') {
    $title = 'Draft Cancelled';
    $subtitle = 'The draft fund was removed successfully. You can start a new draft any time.';
    $primaryLabel = 'Create New Fund';
    $primaryHref = route('create-fund');
    $secondaryLabel = 'Home';
    $secondaryHref = is_logged_in_as_manager() ? route('manager-dashboard') : route('home');
}

render_head($title);
render_responsive_shell_start($title, true);
?>
<div class="card-stack operation-result-wrap">
    <section class="confirm-card operation-result-card">
        <div class="center-stack">
            <div class="operation-result-icon">OK</div>
            <h2><?= e($title) ?></h2>
            <p class="centered-copy"><?= e($subtitle) ?></p>
        </div>
        <div class="confirm-actions mt-18">
            <a class="cta-primary" href="<?= e($primaryHref) ?>"><?= e($primaryLabel) ?></a>
            <a class="small-btn" href="<?= e($secondaryHref) ?>"><?= e($secondaryLabel) ?></a>
        </div>
    </section>
</div>
<?php
render_responsive_shell_end();
render_end();
?>


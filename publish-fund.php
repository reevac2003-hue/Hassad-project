<?php
/**
 * ملاحظات توثيقية للصفحة: publish-fund.php
 * الغرض: مراجعة الصندوق وتجهيزه للنشر أو الإرجاع للمسودة.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - capitalize(): تحسين شكل النص (حرف أول كبير). القيمة الراجعة: string.
 * - current_manager(): جلب بيانات المدير الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - fund_by_id(): جلب صندوق محدد عبر FundID. القيمة الراجعة: ?array.
 * - fund_by_slug(): جلب صندوق باستخدام slug من الرابط. القيمة الراجعة: ?array.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
// ط·آ§ط¸â€‍ط·ع¾ط·آ­ط¸â€ڑط¸â€ڑ ط¸â€¦ط¸â€  ط·آµط¸â€‍ط·آ§ط·آ­ط¸ظ¹ط·آ© ط·آ§ط¸â€‍ط¸â€¦ط·آ¯ط¸ظ¹ط·آ± - ط¸â€¦ط¸â€ ط·آ¹ ط·آ§ط¸â€‍ط·آ·ط·آ§ط¸â€‍ط·آ¨ ط¸â€¦ط¸â€  ط·آ§ط¸â€‍ط¸ث†ط·آµط¸ث†ط¸â€‍
if (!is_logged_in_as_manager()) {
    set_flash('error', 'Access denied. Manager login required.');
    redirect_to('login', ['role' => 'manager']);
}

$slug = $_GET['slug'] ?? null;
$fundId = (int) ($_GET['fund_id'] ?? ($_SESSION['pending_fund_id'] ?? 0));
if ($slug && !$fundId) {
    $fundData = fund_by_slug($slug);
    $fundId = $fundData ? (int) $fundData['FundID'] : 0;
}
$fund = $fundId > 0 ? fund_by_id($fundId) : null;
$manager = current_manager();
render_head('Confirm Fund Publication');
render_responsive_shell_start('Confirm Fund Publication');
?>
<div class="card-stack">
    <section class="form-box manager-snapshot">
        <h3>Fund Manager Snapshot</h3>
        <div class="preview-grid">
            <div class="preview-item"><span>Manager</span><strong><?= e(($manager['FundManagerNameFirst'] ?? '') . ' ' . ($manager['FundManagerNameLast'] ?? '')) ?></strong></div>
            <div class="preview-item"><span>License ID</span><strong><?= e($manager['FundManagerNumberofLicense']) ?></strong></div>
            <div class="preview-item"><span>Phone</span><strong><?= e($manager['FundManagerPhone']) ?></strong></div>
            <div class="preview-item"><span>Status</span><strong>Ready to publish</strong></div>
        </div>
    </section>

    <?php if (!$fund): ?>
        <section class="empty-state form-box center">
            <h2>No draft fund found</h2>
            <p class="centered-copy">Create a new fund first, then come back to publish it.</p>
            <a class="cta-primary" href="<?= e(route('create-fund')) ?>">Create Fund</a>
        </section>
    <?php else: ?>
        <section class="confirm-card">
            <div class="center-stack">
                <h2>Confirm Fund Publication</h2>
                <p class="centered-copy">By submitting this fund, it will be sent to the admin for approval. Once approved, it will become visible to investors.</p>
            </div>
            <div class="preview-grid mt-18">
                <div class="preview-item"><span>Fund Name</span><strong><?= e($fund['FundTitle']) ?></strong></div>
                <div class="preview-item"><span>Fund Type</span><strong><?= e($fund['InvestmentType']) ?></strong></div>
                <div class="preview-item"><span>Minimum Investment Amount</span><strong><?= e(format_sar($fund['FundAmountMinimum'])) ?></strong></div>
                <div class="preview-item"><span>Maximum Investment Amount</span><strong><?= e(format_sar($fund['FundAmountMaximum'])) ?></strong></div>
                <div class="preview-item"><span>Holding Period</span><strong><?= e((int) $fund['HoldingPeriod']) ?> months</strong></div>
                <div class="preview-item"><span>Risk Level</span><strong><?= e(capitalize((string) $fund['RiskLevel'])) ?></strong></div>
            </div>
            <div class="confirm-actions mt-18">
                <form method="post" action="<?= e(action_url('undo-fund')) ?>">
                    <input type="hidden" name="fund_id" value="<?= e($fund['FundID']) ?>">
                    <button class="small-btn" type="submit">Delete</button>
                </form>
                <form method="post" action="<?= e(action_url('save-fund-draft')) ?>">
                    <input type="hidden" name="fund_id" value="<?= e($fund['FundID']) ?>">
                    <button class="small-btn" type="submit">Draft</button>
                </form>
                <form method="post" action="<?= e(action_url('publish-fund')) ?>">
                    <input type="hidden" name="fund_id" value="<?= e($fund['FundID']) ?>">
                    <button class="cta-primary" type="submit">Submit for Approval</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php
render_responsive_shell_end();
render_end();
?>



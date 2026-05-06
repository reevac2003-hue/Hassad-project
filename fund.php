<?php
/**
 * ملاحظات توثيقية للصفحة: fund.php
 * الغرض: عرض تفاصيل الصندوق وحالة الاشتراك.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - all_funds(): جلب قائمة الصناديق (الكل أو المنشور فقط). القيمة الراجعة: array.
 * - bank_cards_for_student(): جلب البطاقة المرتبطة بالطالب. القيمة الراجعة: array.
 * - current_student(): جلب بيانات الطالب الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - fund_by_slug(): جلب صندوق باستخدام slug من الرابط. القيمة الراجعة: ?array.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 * - student_has_contract(): التحقق هل الطالب لديه عقد اشتراك في الصندوق. القيمة الراجعة: bool.
 */
if (!is_logged_in_as_student() && !is_logged_in_as_manager()) {
    set_flash('error', 'Please login to view fund details.');
    redirect_to('login');
}

$slug        = (string) ($_GET['slug'] ?? 'sukuk');
$fund        = fund_by_slug($slug) ?? all_funds(false)[0] ?? null;
$student     = current_student();
$isSubscribed = $student ? student_has_contract((int) $student['StudentID'], (int) ($fund['FundID'] ?? 0)) : false;
$isManager   = is_logged_in_as_manager();
$hasLinkedCard = $student ? !empty(bank_cards_for_student((int) ($student['StudentID'] ?? 0))) : false;

if (!$fund) {
    render_head('Fund Details');
    render_responsive_shell_start('', true);
    echo '<div class="empty-state">Fund not found.</div>';
    render_responsive_shell_end();
    render_end();
    return;
}

$returnPct  = rtrim(rtrim(number_format((float) $fund['ExpectedReturnPercentage'], 2), '0'), '.');
$riskLevel  = strtolower((string) ($fund['RiskLevel'] ?? 'medium'));
$riskLabel  = ucfirst($riskLevel);
$fundStatus = strtolower((string) ($fund['FundAccountStatus'] ?? 'published'));
$highlights = $fund['Highlights'] ?? [];
$isFundOpenForSubscription = $fundStatus === 'published';

render_head((string) $fund['FundTitle']);
render_responsive_shell_start('', true);
?>


<div class="fd-page">
    <!-- أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ HERO أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ -->
    <div class="fd-hero">
        <div class="fd-hero-inner">
            <div>
                <!-- Breadcrumb -->
                <div class="fd-hero-breadcrumb">
                    <a href="<?= e(route('search-funds')) ?>">Funds</a>
                    <span>/</span>
                    <span><?= e($fund['InvestmentType'] ?? 'Fund') ?></span>
                    <span>/</span>
                    <span><?= e($fund['FundTitle']) ?></span>
                </div>

                <!-- Tags -->
                <div class="fd-hero-tags">
                    <span class="fd-tag"><?= e($fund['InvestmentType'] ?? 'Fund') ?></span>
                    <span class="fd-tag risk-<?= e($riskLevel) ?>"><?= e($riskLabel) ?> Risk</span>
                    <span class="fd-tag status-<?= e($fundStatus) ?>"><?= e(ucfirst($fundStatus)) ?></span>
                </div>

                <h1><?= e($fund['FundTitle']) ?></h1>
                <p class="fd-hero-desc"><?= e(mb_substr((string)($fund['FundDescription'] ?? ''), 0, 180)) ?><?= mb_strlen((string)($fund['FundDescription'] ?? '')) > 180 ? '...' : '' ?></p>
            </div>

        </div>

        <!-- Stats bar -->
        <div class="fd-hero-stats u-inline-71">
            <div class="fd-hero-stat">
                <span class="fd-hero-stat-label">Expected Return</span>
                <span class="fd-hero-stat-value green"><?= e($returnPct) ?>%</span>
            </div>
            <div class="fd-hero-stat">
                <span class="fd-hero-stat-label">Min. Investment</span>
                <span class="fd-hero-stat-value"><?= e(format_sar($fund['FundAmountMinimum'])) ?></span>
            </div>
            <div class="fd-hero-stat">
                <span class="fd-hero-stat-label">Max. Investment</span>
                <span class="fd-hero-stat-value"><?= e(format_sar($fund['FundAmountMaximum'])) ?></span>
            </div>
            <div class="fd-hero-stat">
                <span class="fd-hero-stat-label">Holding Period</span>
                <span class="fd-hero-stat-value"><?= e((int)$fund['HoldingPeriod']) ?> months</span>
            </div>
            <div class="fd-hero-stat">
                <span class="fd-hero-stat-label">Duration</span>
                <span class="fd-hero-stat-value"><?= e((int)($fund['FundDuration'] ?? $fund['HoldingPeriod'])) ?> months</span>
            </div>
            <div class="fd-hero-stat">
                <span class="fd-hero-stat-label">Return Policy</span>
                <span class="fd-hero-stat-value u-inline-72"><?= e($fund['ReturnPolicy'] ?? 'Quarterly') ?></span>
            </div>
        </div>
    </div>

    <!-- أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ BODY أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ -->
    <div class="fd-body">
        <!-- Left column -->
        <div>
            <!-- About -->
            <div class="fd-section">
                <div class="fd-section-header">
                    <div class="fd-section-icon">INFO</div>
                    <h3 class="fd-section-title">About This Fund</h3>
                </div>
                <div class="fd-section-body">
                    <p class="fd-about-text"><?= e($fund['FundDescription']) ?></p>
                </div>
            </div>

            <div class="fd-section">
                <div class="fd-section-header">
                    <div class="fd-section-icon">KEY</div>
                    <h3 class="fd-section-title">Key Highlights</h3>
                </div>
                <div class="fd-section-body">
                    <?php if (!empty($highlights)): ?>
                    <ul class="fd-highlights">
                        <?php foreach ($highlights as $hl): ?>
                        <li><?= e($hl) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="fd-about-text">No key highlights have been added for this fund yet.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Right: Sidebar -->
        <div class="fd-sidebar">
            <div class="fd-sidebar-card">
                <!-- Head -->
                <div class="fd-sidebar-head">
                    <div class="fd-sidebar-fund-type"><?= e($fund['InvestmentType'] ?? 'Fund') ?></div>
                    <div class="fd-sidebar-fund-name"><?= e($fund['FundTitle']) ?></div>
                    <div class="fd-sidebar-return">
                        <span class="fd-sidebar-return-label">Expected Annual Return</span>
                        <span class="fd-sidebar-return-value"><?= e($returnPct) ?>%</span>
                    </div>
                </div>

                <!-- Body rows -->
                <div class="fd-sidebar-body">
                    <div class="fd-sidebar-row">
                        <span class="fd-sidebar-row-key">Min. Investment</span>
                        <span class="fd-sidebar-row-val"><?= e(format_sar($fund['FundAmountMinimum'])) ?></span>
                    </div>
                    <div class="fd-sidebar-row">
                        <span class="fd-sidebar-row-key">Max. Investment</span>
                        <span class="fd-sidebar-row-val"><?= e(format_sar($fund['FundAmountMaximum'])) ?></span>
                    </div>
                    <div class="fd-sidebar-row">
                        <span class="fd-sidebar-row-key">Lock-in Period</span>
                        <span class="fd-sidebar-row-val"><?= e((int)$fund['HoldingPeriod']) ?> months</span>
                    </div>
                    <div class="fd-sidebar-row">
                        <span class="fd-sidebar-row-key">Distribution</span>
                        <span class="fd-sidebar-row-val"><?= e($fund['ReturnPolicy'] ?? 'Quarterly') ?></span>
                    </div>
                    <div class="fd-sidebar-row">
                        <span class="fd-sidebar-row-key">Risk</span>
                        <span class="fd-sidebar-row-val" style="color:<?= $riskLevel==='low'?'#27ae60':($riskLevel==='high'?'#c0392b':'#d68910') ?>"><?= e($riskLabel) ?></span>
                    </div>
                </div>

                <!-- CTA -->
                <div class="fd-sidebar-cta">
                    <?php if ($isManager): ?>
                    <div class="fd-manager-notice">Manager view - subscription not available</div>
                    <?php elseif (!$isFundOpenForSubscription): ?>
                    <div class="fd-manager-notice">This fund is currently <?= e(ucfirst($fundStatus)) ?> and not open for student subscriptions</div>
                    <?php elseif ($isSubscribed): ?>
                    <div class="fd-subscribed-badge">Subscribed</div>
                    <form method="post" action="<?= e(action_url('cancel-subscription')) ?>">
                        <input type="hidden" name="fund_id" value="<?= e($fund['FundID']) ?>">
                        <button class="fd-sidecar-cancel" type="submit" onclick="return confirm('Cancel your subscription?')">
                            Cancel Subscription
                        </button>
                    </form>
                    <?php elseif (!$hasLinkedCard): ?>
                    <a class="fd-sidecar-subscribe" href="<?= e(route('edit-profile', ['error' => 'Please add your linked card before subscribing.'])) ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Add Card to Continue
                    </a>
                    <?php else: ?>
                    <a class="fd-sidecar-subscribe" href="<?= e(route('subscribe', ['slug' => $fund['slug']])) ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Subscribe to Fund
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /fd-body -->
</div><!-- /fd-page -->

<?php
render_responsive_shell_end();
render_end();
?>






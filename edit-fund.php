<?php
/**
 * ملاحظات توثيقية للصفحة: edit-fund.php
 * الغرض: تعديل بيانات الصندوق وحالته التشغيلية.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - current_manager(): جلب بيانات المدير الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - find_fund_by_id(): جلب صندوق عبر المعرف باستخدام دالة التوافق. القيمة الراجعة: ?array.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - require_manager_login(): فرض تسجيل الدخول كمدير قبل متابعة التنفيذ. القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
/**
 * Edit Fund - ط·ع¾ط·آ¹ط·آ¯ط¸ظ¹ط¸â€‍ ط·آµط¸â€ ط·آ¯ط¸ث†ط¸â€ڑ ط·آ§ط·آ³ط·ع¾ط·آ«ط¸â€¦ط·آ§ط·آ±ط¸ظ¹
 * --------------------------------
 * Fund Manager can edit their fund details
 */

require_manager_login();

$manager = current_manager();
$fundId = $_GET['id'] ?? null;

if (!$fundId) {
    set_flash('error', 'Fund ID is required.');
    redirect_to('manager-dashboard');
}

$fund = find_fund_by_id((int) $fundId);

if (!$fund) {
    set_flash('error', 'Fund not found.');
    redirect_to('manager-dashboard');
}

// Manager can edit any fund
// ط·ع¾ط¸â€¦ ط·آ¥ط·آ²ط·آ§ط¸â€‍ط·آ© ط·آ§ط¸â€‍ط·ع¾ط·آ­ط¸â€ڑط¸â€ڑ ط¸â€¦ط¸â€  ط·آ§ط¸â€‍ط¸â€¦ط¸â€‍ط¸ئ’ط¸ظ¹ط·آ© - ط·آ§ط¸â€‍ط¸â€¦ط·آ¯ط¸ظ¹ط·آ± ط¸ظ¹ط¸â€¦ط¸ئ’ط¸â€ ط¸â€، ط·ع¾ط·آ¹ط·آ¯ط¸ظ¹ط¸â€‍ ط·آ£ط¸ظ¹ ط·آµط¸â€ ط·آ¯ط¸ث†ط¸â€ڑ

render_head('Edit Fund');
render_responsive_shell_start('');
?>

<div class="edit-fund-page">
    <div class="edit-fund-header">
       
        <h1>Edit Fund</h1>
        <p>Refine your fund strategy, limits, and lifecycle settings in one place.</p>
    </div>

    <div class="fund-info-banner">
        <div class="info-item">
            <span class="label">Fund ID:</span>
            <span class="value"><?= e($fund['FundID']) ?></span>
        </div>
        <div class="info-item">
            <span class="label">Status:</span>
            <span class="status-badge status-<?= strtolower($fund['FundAccountStatus']) ?>"><?= e($fund['FundAccountStatus']) ?></span>
        </div>
        <div class="info-item">
            <span class="label">Created:</span>
            <span class="value"><?= e(date('M d, Y', strtotime($fund['FundDateStart'] ?? 'now'))) ?></span>
        </div>
    </div>

    <form action="<?= e(action_url('update-fund')) ?>" method="POST" class="edit-fund-form">
        <input type="hidden" name="fund_id" value="<?= e($fund['FundID']) ?>">

        <div class="form-section">
            <h3>Basic Information</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="title">Fund Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" value="<?= e($fund['FundTitle']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Fund Description <span class="required">*</span></label>
                <textarea id="description" name="description" rows="4" required><?= e($fund['FundDescription']) ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3>Financial Details</h3>

            <div class="form-row three-col">
                <div class="form-group">
                    <label for="risk_level">Risk Level <span class="required">*</span></label>
                    <select id="risk_level" name="risk_level" required>
                        <option value="low" <?= $fund['RiskLevel'] === 'low' ? 'selected' : '' ?>>Low Risk</option>
                        <option value="medium" <?= $fund['RiskLevel'] === 'medium' ? 'selected' : '' ?>>Medium Risk</option>
                        <option value="high" <?= $fund['RiskLevel'] === 'high' ? 'selected' : '' ?>>High Risk</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="expected_return">Expected Return (%) <span class="required">*</span></label>
                    <input type="number" id="expected_return" name="expected_return" value="<?= e($fund['ExpectedReturnPercentage'] ?? 0) ?>" step="0.1" min="0" max="100" required>
                </div>
                <div class="form-group">
                    <label for="min_amount">Minimum Investment (SAR) <span class="required">*</span></label>
                    <input type="number" id="min_amount" name="min_amount" value="<?= e($fund['FundAmountMinimum'] ?? 0) ?>" step="1" min="0" required>
                </div>
            </div>

            <div class="form-row two-col">
                <div class="form-group">
                    <label for="max_amount">Maximum Investment (SAR) <span class="required">*</span></label>
                    <input type="number" id="max_amount" name="max_amount" value="<?= e($fund['FundAmountMaximum'] ?? 0) ?>" step="1" min="0" required>
                </div>
                <div class="form-group">
                    <label for="return_policy">Return Distribution Policy <span class="required">*</span></label>
                    <select id="return_policy" name="return_policy" required>
                        <?php $returnPolicy = (string) ($fund['ReturnTimingPolicy'] ?? 'Quarterly'); ?>
                        <option value="Monthly" <?= $returnPolicy === 'Monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="Quarterly" <?= $returnPolicy === 'Quarterly' ? 'selected' : '' ?>>Quarterly</option>
                        <option value="Annual" <?= $returnPolicy === 'Annual' ? 'selected' : '' ?>>Annual</option>
                        <option value="On maturity" <?= $returnPolicy === 'On maturity' ? 'selected' : '' ?>>On maturity</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Timeline Settings</h3>

            <div class="form-row two-col">
                <div class="form-group">
                    <label for="subscription_start">Subscription Start</label>
                    <input type="date" id="subscription_start" name="subscription_start" value="<?= e($fund['FundDateStart'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="subscription_end">Subscription End</label>
                    <input type="date" id="subscription_end" name="subscription_end" value="<?= e($fund['FundDateEnd'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row two-col">
                <div class="form-group">
                    <label for="fund_start">Fund Start Date</label>
                    <input type="date" id="fund_start" name="fund_start" value="<?= e($fund['FundDateStart'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="fund_end">Fund End Date</label>
                    <input type="date" id="fund_end" name="fund_end" value="<?= e($fund['FundDateEnd'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="holding_period">Holding Period (Months) <span class="required">*</span></label>
                <input type="number" id="holding_period" name="holding_period" value="<?= e($fund['DurationMonths'] ?? $fund['HoldingPeriod'] ?? 0) ?>" min="1" required>
            </div>
        </div>

        <div class="form-section">
            <h3>Terms & Conditions</h3>
            
            <div class="form-group">
                <label for="terms">Terms and Conditions</label>
                <textarea id="terms" name="terms" rows="5"><?= e($fund['FundTermsAndConditions'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= e(route('home')) ?>" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-save">Save Changes</button>
        </div>
    </form>

    <?php $currentStatus = strtolower((string) ($fund['FundAccountStatus'] ?? 'draft')); ?>
    <div class="danger-zone visibility-zone">
        <div class="visibility-zone-header">
            <div>
                <h3>Fund Visibility</h3>
                <p>Control publication and visibility with clear lifecycle actions.</p>
            </div>
            <span class="status-badge status-<?= e($currentStatus) ?>">Current: <?= e(ucfirst($currentStatus)) ?></span>
        </div>

        <div class="visibility-grid">
            <article class="visibility-card">
                <h4>Publication State</h4>
                <p>Switch between Draft and Published to control subscription availability.</p>
                <div class="visibility-actions visibility-actions-primary">
                    <?php if ($currentStatus !== 'draft'): ?>
                    <form action="<?= e(action_url('save-fund-draft')) ?>" method="POST">
                        <input type="hidden" name="fund_id" value="<?= e($fund['FundID']) ?>">
                        <button type="submit" class="btn-hide visibility-btn" onclick="return confirm('Move this fund to Draft? Students will not be able to subscribe.')">Move to Draft</button>
                    </form>
                    <?php else: ?>
                    <form action="<?= e(action_url('publish-fund')) ?>" method="POST">
                        <input type="hidden" name="fund_id" value="<?= e($fund['FundID']) ?>">
                        <button type="submit" class="btn-show visibility-btn">Publish Fund</button>
                    </form>
                    <?php endif; ?>
                </div>
            </article>

            <article class="visibility-card">
                <h4>Student Listing</h4>
                <p>Hide the fund from student listings without deleting the fund data.</p>
                <div class="visibility-actions">
                    <?php if ($currentStatus === 'hidden'): ?>
                    <form action="<?= e(action_url('show-fund')) ?>" method="POST">
                        <input type="hidden" name="fund_id" value="<?= e($fund['FundID']) ?>">
                        <button type="submit" class="btn-show visibility-btn">Show Fund</button>
                    </form>
                    <?php else: ?>
                    <form action="<?= e(action_url('hide-fund')) ?>" method="POST">
                        <input type="hidden" name="fund_id" value="<?= e($fund['FundID']) ?>">
                        <button type="submit" class="btn-hide visibility-btn" onclick="return confirm('Hide this fund from students?')">Hide Fund</button>
                    </form>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    </div>
</div>


<?php
render_responsive_shell_end();
render_end();
?>






<?php
/**
 * ملاحظات توثيقية للصفحة: create-fund.php
 * الغرض: إنشاء صندوق جديد من طرف المدير.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - current_manager(): جلب بيانات المدير الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
if (!is_logged_in_as_manager()) {
    set_flash('error', 'Access denied. Manager login required.');
    redirect_to('login', ['role' => 'manager']);
}

$manager   = current_manager();
$mgrName   = trim(($manager['FundManagerNameFirst'] ?? '') . ' ' . ($manager['FundManagerNameLast'] ?? ''));

// Load platform limits for validation
$sysMinSub = (float) (get_system_setting('min_subscription') ?? 100);
$sysMaxSub = (float) (get_system_setting('max_subscription') ?? 100000);

render_head('Create Investment Fund');
render_responsive_shell_start('');
?>


<div class="cf-page">

    <!-- Hero Banner -->
    <div class="cf-hero">
        <div class="cf-hero-left">
            <div class="cf-hero-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Fund
            </div>
            <h1>Create Investment Fund</h1>
            <p>Set up a new Sharia-compliant investment product for your investors</p>
        </div>
        <div class="cf-hero-right">
            <div class="cf-manager-label">Fund Manager</div>
            <div class="cf-manager-name"><?= e($mgrName) ?></div>
            <div class="cf-manager-meta">
                <div class="cf-manager-chip">
                    <span>License</span>
                    <strong><?= e($manager['FundManagerNumberofLicense'] ?? '-') ?></strong>
                </div>
                <div class="cf-manager-chip">
                    <span>Phone</span>
                    <strong><?= e($manager['FundManagerPhone'] ?? '-') ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Steps progress indicator -->
    <div class="cf-steps" id="cfSteps">
        <div class="cf-step active" data-step="1">
            <div class="cf-step-num">1</div>
            <div class="cf-step-info">
                <span class="cf-step-label">Step 1</span>
                <span class="cf-step-title">Identity</span>
            </div>
        </div>
        <div class="cf-step-sep"></div>
        <div class="cf-step" data-step="2">
            <div class="cf-step-num">2</div>
            <div class="cf-step-info">
                <span class="cf-step-label">Step 2</span>
                <span class="cf-step-title">Financials</span>
            </div>
        </div>
        <div class="cf-step-sep"></div>
        <div class="cf-step" data-step="3">
            <div class="cf-step-num">3</div>
            <div class="cf-step-info">
                <span class="cf-step-label">Step 3</span>
                <span class="cf-step-title">Risk</span>
            </div>
        </div>
        <div class="cf-step-sep"></div>
        <div class="cf-step" data-step="4">
            <div class="cf-step-num">4</div>
            <div class="cf-step-info">
                <span class="cf-step-label">Step 4</span>
                <span class="cf-step-title">Description</span>
            </div>
        </div>
        <div class="cf-step-sep"></div>
        <div class="cf-step" data-step="5">
            <div class="cf-step-num">5</div>
            <div class="cf-step-info">
                <span class="cf-step-label">Step 5</span>
                <span class="cf-step-title">Confirm</span>
            </div>
        </div>
    </div>

    <form method="post" action="<?= e(action_url('create-fund')) ?>" id="cfForm">

        <div class="cf-layout">
            <!-- Left: Form steps -->
            <div id="cfFormArea">

                <!-- أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ STEP 1: Identity أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ -->
                <div class="cf-card cf-step-panel" data-panel="1">
                    <div class="cf-card-header">
                        <div class="cf-card-icon">ID</div>
                        <div>
                            <h2>Fund Identity</h2>
                            <p>Name, objective, and investment asset class</p>
                        </div>
                    </div>
                    <div class="cf-card-body">
                        <div class="cf-grid">
                            <div class="cf-field cf-full">
                                <label class="cf-label" for="cf_title">Fund Name <span class="req">*</span></label>
                                <input class="cf-input" type="text" id="cf_title" name="title"
                                    value="" required
                                    placeholder="e.g. Hassad Sukuk Growth Fund"
                                    oninput="cfPreview('name', this.value)">
                                <span class="cf-hint">A clear, descriptive name visible to investors</span>
                            </div>
                            <div class="cf-field">
                                <label class="cf-label" for="cf_inv_objective">Investment Objective <span class="req">*</span></label>
                                <select class="cf-select" id="cf_inv_objective" name="investment_objective"
                                    onchange="cfPreview('objective', this.value)">
                                    <option value="Capital Growth">Capital Growth</option>
                                    <option value="Income Generation">Income Generation</option>
                                    <option value="Balanced">Balanced</option>
                                </select>
                            </div>
                            <div class="cf-field">
                                <label class="cf-label" for="cf_inv_type">Investment Type <span class="req">*</span></label>
                                <select class="cf-select" id="cf_inv_type" name="investment_type"
                                    onchange="cfPreview('type', this.value)">
                                    <option value="Equities (Saudi Stocks - Saudi Exchange)">Equities (Saudi Stocks - Saudi Exchange)</option>
                                    <option value="Global Equities">Global Equities</option>
                                    <option value="Fixed Income (Bonds & Sukuk)">Fixed Income (Bonds & Sukuk)</option>
                                    <option value="Real Estate">Real Estate</option>
                                    <option value="Mutual Funds / ETFs">Mutual Funds / ETFs</option>
                                    <option value="Venture Capital (Startups)">Venture Capital (Startups)</option>
                                    <option value="Commodities (Gold, Oil, etc.)">Commodities (Gold, Oil, etc.)</option>
                                    <option value="Multi-Asset (Diversified Portfolio)">Multi-Asset (Diversified Portfolio)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="cf-actions">
                        <a href="<?= e(route('home')) ?>" class="cf-btn-cancel">
                            Back to Home
                        </a>
                        <button type="button" class="cf-btn-submit" onclick="cfGoTo(2)">
                            Next: Financials
                        </button>
                    </div>
                </div>

                <!-- أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ STEP 2: Financials أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ -->
                <div class="cf-card cf-step-panel u-inline-5" data-panel="2">
                    <div class="cf-card-header">
                        <div class="cf-card-icon">FIN</div>
                        <div>
                            <h2>Financial Parameters</h2>
                            <p>Investment limits and expected returns</p>
                        </div>
                    </div>
                    <div class="cf-card-body">
                        <!-- Platform limits notice -->
                        <div class="cf-limits-notice">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            Platform limits: minimum <strong><?= number_format($sysMinSub, 0) ?> SAR</strong> — maximum <strong><?= number_format($sysMaxSub, 0) ?> SAR</strong>
                        </div>

                        <div class="cf-grid">
                            <div class="cf-field">
                                <label class="cf-label" for="cf_min">Minimum Investment <span class="req">*</span></label>
                                <div class="cf-input-wrap">
                                    <input class="cf-input" type="number" id="cf_min" name="min_amount"
                                        value=""
                                        min="<?= (int) $sysMinSub ?>"
                                        max="<?= (int) $sysMaxSub ?>"
                                        required
                                        oninput="cfPreview('min', this.value); cfValidateLimits()">
                                    <span class="cf-suffix">SAR</span>
                                </div>
                                <span class="cf-hint" id="cf_min_hint">Min allowed: <?= number_format($sysMinSub, 0) ?> SAR</span>
                            </div>
                            <div class="cf-field">
                                <label class="cf-label" for="cf_max">Maximum Investment <span class="req">*</span></label>
                                <div class="cf-input-wrap">
                                    <input class="cf-input" type="number" id="cf_max" name="max_amount"
                                        value=""
                                        min="<?= (int) $sysMinSub ?>"
                                        max="<?= (int) $sysMaxSub ?>"
                                        required
                                        oninput="cfPreview('max', this.value); cfValidateLimits()">
                                    <span class="cf-suffix">SAR</span>
                                </div>
                                <span class="cf-hint" id="cf_max_hint">Max allowed: <?= number_format($sysMaxSub, 0) ?> SAR</span>
                            </div>
                            <div class="cf-field">
                                <label class="cf-label" for="cf_ret">Expected Annual Return <span class="req">*</span></label>
                                <div class="cf-input-wrap">
                                    <input class="cf-input" type="number" id="cf_ret" name="expected_return"
                                        value="" min="0" max="100" step="0.01" required
                                        oninput="cfPreview('ret', this.value)">
                                    <span class="cf-suffix">%</span>
                                </div>
                                <span class="cf-hint">Sharia-compliant projected yield</span>
                            </div>
                            <div class="cf-field">
                                <label class="cf-label">Return Distribution Policy <span class="req">*</span></label>
                                <div class="cf-return-group">
                                    <label class="cf-return-opt">
                                        <input type="radio" name="return_policy" value="Monthly" onchange="cfPreview('policy', 'Monthly')">
                                        <span class="ret-icon">M</span>
                                        <span class="ret-text">Monthly</span>
                                    </label>
                                    <label class="cf-return-opt">
                                        <input type="radio" name="return_policy" value="Quarterly" onchange="cfPreview('policy', 'Quarterly')">
                                        <span class="ret-icon">Q</span>
                                        <span class="ret-text">Quarterly</span>
                                    </label>
                                    <label class="cf-return-opt">
                                        <input type="radio" name="return_policy" value="Annual" checked onchange="cfPreview('policy', 'Annual')">
                                        <span class="ret-icon">Y</span>
                                        <span class="ret-text">Annual</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="cf-actions">
                        <button type="button" class="cf-btn-cancel" onclick="cfGoTo(1)">Back</button>
                        <button type="button" class="cf-btn-submit" onclick="cfGoTo(3)">Next: Risk</button>
                    </div>
                </div>

                <!-- أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ STEP 3: Risk أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ -->
                <div class="cf-card cf-step-panel u-inline-5" data-panel="3">
                    <div class="cf-card-header">
                        <div class="cf-card-icon">RISK</div>
                        <div>
                            <h2>Risk Profile</h2>
                            <p>Investor risk profile and lock period</p>
                        </div>
                    </div>
                    <div class="cf-card-body">
                        <div class="cf-grid-3">
                            <div class="cf-field">
                                <label class="cf-label" for="cf_hold">Holding Period <span class="req">*</span></label>
                                <div class="cf-input-wrap">
                                    <input class="cf-input" type="number" id="cf_hold" name="holding_period"
                                        value="" min="1" required
                                        oninput="cfPreview('hold', this.value)">
                                    <span class="cf-suffix">mo</span>
                                </div>
                                <span class="cf-hint">Minimum lock-in before withdrawal</span>
                            </div>
                        </div>

                        <hr class="cf-section-sep">

                        <div class="cf-field">
                            <label class="cf-label">Risk Level <span class="req">*</span></label>
                            <div class="cf-risk-group">
                                <label class="cf-risk-opt low-opt">
                                    <input type="radio" name="risk_level" value="low" onchange="cfPreview('risk','low')">
                                    <span class="risk-icon">L</span>
                                    <span class="risk-text">Low</span>
                                </label>
                                <label class="cf-risk-opt med-opt">
                                    <input type="radio" name="risk_level" value="medium" checked onchange="cfPreview('risk','medium')">
                                    <span class="risk-icon">M</span>
                                    <span class="risk-text">Medium</span>
                                </label>
                                <label class="cf-risk-opt high-opt">
                                    <input type="radio" name="risk_level" value="high" onchange="cfPreview('risk','high')">
                                    <span class="risk-icon">H</span>
                                    <span class="risk-text">High</span>
                                </label>
                            </div>
                            <span class="cf-hint">Determines the risk disclosure shown to investors</span>
                        </div>
                    </div>
                    <div class="cf-actions">
                        <button type="button" class="cf-btn-cancel" onclick="cfGoTo(2)">Back</button>
                        <button type="button" class="cf-btn-submit" onclick="cfGoTo(4)">Next: Description</button>
                    </div>
                </div>

                <!-- أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ STEP 4: Description أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ -->
                <div class="cf-card cf-step-panel u-inline-5" data-panel="4">
                    <div class="cf-card-header">
                        <div class="cf-card-icon">TXT</div>
                        <div>
                            <h2>Description & Highlights</h2>
                            <p>Content visible to potential investors</p>
                        </div>
                    </div>
                    <div class="cf-card-body">
                        <div class="cf-field u-inline-61">
                            <label class="cf-label" for="cf_desc">Fund Description <span class="req">*</span></label>
                            <textarea class="cf-textarea u-inline-62" id="cf_desc" name="description" required
                                placeholder="Describe the fund's objectives, strategy, and Sharia compliance..."></textarea>
                            <span class="cf-hint">Minimum 50 characters recommended for investor clarity</span>
                        </div>
                        <div class="cf-field">
                            <label class="cf-label" for="cf_highlight">Key Highlights <span class="req">*</span></label>
                            <textarea class="cf-textarea u-inline-63" id="cf_highlight" name="highlight" required
                                placeholder="List the fund's key selling points, one per line..."></textarea>
                            <span class="cf-hint">One highlight per line - displayed as a bullet list to investors</span>
                        </div>
                    </div>
                    <div class="cf-actions">
                        <button type="button" class="cf-btn-cancel" onclick="cfGoTo(3)">Back</button>
                        <button type="button" class="cf-btn-submit" onclick="cfGoTo(5)">Review & Confirm</button>
                    </div>
                </div>

                <!-- أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ STEP 5: Confirm أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯أ¢â€¢ع¯ -->
                <div class="cf-card cf-step-panel u-inline-5" data-panel="5">
                    <div class="cf-card-header">
                        <div class="cf-card-icon">OK</div>
                        <div>
                            <h2>Review & Confirm</h2>
                            <p>Please read and accept before submitting</p>
                        </div>
                    </div>
                    <div class="cf-card-body">
                        <!-- Summary table -->
                        <div class="u-inline-64">
                            <div class="u-inline-65">Fund Summary</div>
                            <div class="u-inline-66">
                                <div><span class="u-inline-67">Fund Name</span><div id="sum_name" class="u-inline-68">Sukuk Sector Fund</div></div>
                                <div><span class="u-inline-67">Fund ID</span><div id="sum_id" class="u-inline-68">Auto after creation</div></div>
                                <div><span class="u-inline-67">Investment Objective</span><div id="sum_objective" class="u-inline-68">Capital Growth</div></div>
                                <div><span class="u-inline-67">Investment Type</span><div id="sum_type" class="u-inline-68">Equities (Saudi Stocks - Saudi Exchange)</div></div>
                                <div><span class="u-inline-67">Risk</span><div id="sum_risk" class="u-inline-68">Medium</div></div>
                                <div><span class="u-inline-67">Investment Range</span><div id="sum_range" class="u-inline-68">0 - 0 SAR</div></div>
                                <div><span class="u-inline-67">Expected Return</span><div id="sum_ret" class="u-inline-68">0%</div></div>
                                <div><span class="u-inline-67">Holding Period</span><div id="sum_hold" class="u-inline-68">0 months</div></div>
                                <div><span class="u-inline-67">Return Policy</span><div id="sum_policy" class="u-inline-68">Annual</div></div>
                            </div>
                        </div>

                        <!-- Agreements -->
                        <div class="cf-checks">
                            <label class="cf-check">
                                <input type="checkbox" name="confirm_accuracy" required>
                                <div class="cf-check-text">
                                    <strong>Fund Information Accuracy</strong>
                                    <span>I confirm that all provided fund details are accurate, complete, and comply with CMA regulations.</span>
                                </div>
                            </label>
                            <label class="cf-check">
                                <input type="checkbox" name="confirm_terms" required>
                                <div class="cf-check-text">
                                    <strong>Platform Terms & Conditions</strong>
                                    <span>I have read and agree to the <a href="<?= e(route('terms')) ?>" target="_blank" class="u-inline-69">Hassad platform terms</a> governing investment fund operations.</span>
                                </div>
                            </label>
                            <label class="cf-check">
                                <input type="checkbox" name="confirm_sharia" required>
                                <div class="cf-check-text">
                                    <strong>Sharia Compliance Declaration</strong>
                                    <span>I confirm this fund's structure and operations are aligned with Islamic finance principles.</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="cf-actions">
                        <button type="button" class="cf-btn-cancel" onclick="cfGoTo(4)">Back</button>
                        <button type="submit" class="cf-btn-submit">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Create Fund
                        </button>
                    </div>
                </div>

            </div><!-- /cfFormArea -->

            <!-- Right: Live Preview -->
            <div class="cf-preview-card">
                <h3>Live Preview</h3>
                <div id="pv_type_tag" class="cf-preview-type-tag">Equities (Saudi Stocks - Saudi Exchange)</div>
                <div id="pv_name" class="cf-preview-name">-</div>
                <div id="pv_id" class="cf-preview-id">Fund ID auto-generated after creation</div>
                <div class="cf-preview-row">
                    <span class="cf-preview-key">Objective</span>
                    <span id="pv_objective" class="cf-preview-val">Capital Growth</span>
                </div>
                <div class="cf-preview-row">
                    <span class="cf-preview-key">Risk Level</span>
                    <span id="pv_risk" class="cf-preview-risk med">Medium</span>
                </div>
                <div class="cf-preview-row">
                    <span class="cf-preview-key">Min. Investment</span>
                    <span id="pv_min" class="cf-preview-val">0 SAR</span>
                </div>
                <div class="cf-preview-row">
                    <span class="cf-preview-key">Max. Investment</span>
                    <span id="pv_max" class="cf-preview-val">0 SAR</span>
                </div>
                <div class="cf-preview-row">
                    <span class="cf-preview-key">Expected Return</span>
                    <span id="pv_ret" class="cf-preview-val">0%</span>
                </div>
                <div class="cf-preview-row">
                    <span class="cf-preview-key">Distribution</span>
                    <span id="pv_policy" class="cf-preview-val">Annual</span>
                </div>
                <div class="cf-preview-row">
                    <span class="cf-preview-key">Lock-in Period</span>
                    <span id="pv_hold" class="cf-preview-val">0 months</span>
                </div>
            </div>

        </div><!-- /cf-layout -->
    </form>
</div>

<script>
(function () {
    var currentStep = 1;

    /* ── Inline error helper ── */
    function cfShowError(inputEl, msg) {
        inputEl.classList.add('cf-input-error');
        var hint = inputEl.parentElement.querySelector('.cf-hint') || inputEl.nextElementSibling;
        if (hint) {
            hint.className = 'cf-hint cf-hint-error';
            hint.textContent = '✗ ' + msg;
        }
        inputEl.focus();
    }
    function cfClearError(inputEl) {
        inputEl.classList.remove('cf-input-error');
        var hint = inputEl.parentElement.querySelector('.cf-hint') || inputEl.nextElementSibling;
        if (hint && hint.classList.contains('cf-hint-error')) {
            hint.className = 'cf-hint';
        }
    }

    /* ── Step validators ── */
    function cfValidateStep(from) {
        if (from === 1) {
            var title = document.getElementById('cf_title');
            if (!title.value.trim()) { cfShowError(title, 'Fund name is required'); return false; }
            cfClearError(title);
        }
        if (from === 2) {
            var minEl  = document.getElementById('cf_min');
            var maxEl  = document.getElementById('cf_max');
            var retEl  = document.getElementById('cf_ret');
            var ok = true;
            if (!minEl.value || parseFloat(minEl.value) <= 0) { cfShowError(minEl, 'Minimum investment is required'); ok = false; }
            if (!maxEl.value || parseFloat(maxEl.value) <= 0) { cfShowError(maxEl, 'Maximum investment is required'); ok = false; }
            if (!retEl.value || parseFloat(retEl.value) <= 0) { cfShowError(retEl, 'Expected return is required'); ok = false; }
            if (ok && !cfValidateLimits()) ok = false;
            return ok;
        }
        if (from === 3) {
            var hold = document.getElementById('cf_hold');
            if (!hold.value || parseInt(hold.value) < 1) { cfShowError(hold, 'Holding period must be at least 1 month'); return false; }
            cfClearError(hold);
        }
        if (from === 4) {
            var desc = document.getElementById('cf_desc');
            var high = document.getElementById('cf_highlight');
            var ok = true;
            if (!desc.value.trim()) { cfShowError(desc, 'Fund description is required'); ok = false; }
            if (!high.value.trim()) { cfShowError(high, 'Key highlights are required'); ok = false; }
            return ok;
        }
        return true;
    }

    function cfGoTo(step) {
        if (step > currentStep && !cfValidateStep(currentStep)) return;

        // Hide current, show next
        document.querySelectorAll('.cf-step-panel').forEach(function (p) {
            p.style.display = 'none';
        });
        var panel = document.querySelector('.cf-step-panel[data-panel="' + step + '"]');
        if (panel) panel.style.display = 'block';

        // Update step indicators
        document.querySelectorAll('.cf-step').forEach(function (s) {
            var n = parseInt(s.getAttribute('data-step'));
            s.classList.toggle('active', n === step);
            s.classList.toggle('done',   n < step);
        });

        // Update separator colours via CSS cascade
        document.querySelectorAll('.cf-step-sep').forEach(function (sep, idx) {
            sep.style.background = (idx + 1) < step ? '#45644a' : '#e4dbc4';
        });

        currentStep = step;
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Sync summary on step 5
        if (step === 5) syncSummary();
    }

    // Expose globally so inline onclick works
    window.cfGoTo = cfGoTo;

    // Live preview updater
    var pvMap = {
        name:   function(v){ document.getElementById('pv_name').textContent = v || '-'; document.getElementById('sum_name').textContent = v || '-'; },
        objective: function(v){ document.getElementById('pv_objective').textContent = v || '-'; document.getElementById('sum_objective').textContent = v || '-'; },
        type:   function(v){ document.getElementById('pv_type_tag').textContent = v||'-'; document.getElementById('sum_type').textContent = v||'-'; },
        min:    function(v){ document.getElementById('pv_min').textContent = Number(v||0).toLocaleString() + ' SAR'; },
        max:    function(v){ document.getElementById('pv_max').textContent = Number(v||0).toLocaleString() + ' SAR'; },
        ret:    function(v){ document.getElementById('pv_ret').textContent = (v||0) + '%'; document.getElementById('sum_ret').textContent = (v||0)+'%'; },
        policy: function(v){ document.getElementById('pv_policy').textContent = v||'-'; document.getElementById('sum_policy').textContent = v||'-'; },
        hold:   function(v){ document.getElementById('pv_hold').textContent = (v||0)+' months'; document.getElementById('sum_hold').textContent = (v||0)+' months'; },
        risk:   function(v){
            var el = document.getElementById('pv_risk');
            el.className = 'cf-preview-risk ' + (v==='low'?'low':v==='high'?'high':'med');
            el.textContent = v.charAt(0).toUpperCase()+v.slice(1);
            document.getElementById('sum_risk').textContent = el.textContent;
        }
    };

    window.cfPreview = function(key, val){ if(pvMap[key]) pvMap[key](val); };

    function syncSummary () {
        var minEl = document.getElementById('cf_min');
        var maxEl = document.getElementById('cf_max');
        if (minEl && maxEl) {
            document.getElementById('sum_range').textContent =
                Number(minEl.value||0).toLocaleString() + ' - ' +
                Number(maxEl.value||0).toLocaleString() + ' SAR';
        }
    }

    cfGoTo(1);

    // ── Platform limits validation ──
    var SYS_MIN = <?= (int) $sysMinSub ?>;
    var SYS_MAX = <?= (int) $sysMaxSub ?>;

    window.cfValidateLimits = function () {
        var minEl   = document.getElementById('cf_min');
        var maxEl   = document.getElementById('cf_max');
        var minHint = document.getElementById('cf_min_hint');
        var maxHint = document.getElementById('cf_max_hint');
        var minVal  = parseFloat(minEl.value) || 0;
        var maxVal  = parseFloat(maxEl.value) || 0;
        var ok = true;

        // Reset
        minEl.classList.remove('cf-input-error');
        maxEl.classList.remove('cf-input-error');
        minHint.className = 'cf-hint';
        maxHint.className = 'cf-hint';
        minHint.textContent = 'Min allowed: ' + SYS_MIN.toLocaleString() + ' SAR';
        maxHint.textContent = 'Max allowed: ' + SYS_MAX.toLocaleString() + ' SAR';

        if (minVal > 0 && minVal < SYS_MIN) {
            minEl.classList.add('cf-input-error');
            minHint.className = 'cf-hint cf-hint-error';
            minHint.textContent = '✗ Cannot be less than platform minimum (' + SYS_MIN.toLocaleString() + ' SAR)';
            ok = false;
        }
        if (maxVal > 0 && maxVal > SYS_MAX) {
            maxEl.classList.add('cf-input-error');
            maxHint.className = 'cf-hint cf-hint-error';
            maxHint.textContent = '✗ Cannot exceed platform maximum (' + SYS_MAX.toLocaleString() + ' SAR)';
            ok = false;
        }
        if (minVal > 0 && maxVal > 0 && maxVal <= minVal) {
            maxEl.classList.add('cf-input-error');
            maxHint.className = 'cf-hint cf-hint-error';
            maxHint.textContent = '✗ Must be greater than minimum investment';
            ok = false;
        }
        return ok;
    };

    // cfGoTo already handles all validation including limits via cfValidateStep

})();
</script>

<?php
render_responsive_shell_end();
render_end();
?>






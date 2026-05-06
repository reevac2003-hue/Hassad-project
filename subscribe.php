<?php
/**
 * ملاحظات توثيقية للصفحة: subscribe.php
 * الغرض: تنفيذ الاشتراك في الصندوق وخطوة الدفع.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - all_funds(): جلب قائمة الصناديق (الكل أو المنشور فقط). القيمة الراجعة: array.
 * - bank_cards_for_student(): جلب البطاقات المرتبطة بالطالب. القيمة الراجعة: array.
 * - current_bank_account(): جلب البطاقة/الحساب البنكي للطالب. القيمة الراجعة: array.
 * - current_student(): جلب بيانات الطالب الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - fund_by_slug(): جلب صندوق باستخدام slug من الرابط. القيمة الراجعة: ?array.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - latest_investment_amount(): حساب آخر مبلغ استثماري للطالب داخل الصندوق. القيمة الراجعة: float.
 * - mask_card(): إخفاء جزء من رقم البطاقة عند العرض. القيمة الراجعة: string.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
if (!is_logged_in_as_student()) {
    set_flash('error', 'Access denied. Student login required.');
    redirect_to('login', ['role' => 'student']);
}

$slug = (string) ($_GET['slug'] ?? 'sukuk');
$fund = fund_by_slug($slug) ?? all_funds(false)[0] ?? null;
$student = current_student();
$studentId = (int) $student['StudentID'];
$bankCards = bank_cards_for_student($studentId);
$hasSavedCards = !empty($bankCards);
$savedCardCount = count($bankCards);
$bank = $bankCards[0] ?? current_bank_account($studentId);

if (!$fund) {
    render_head('Subscribe to Fund');
    render_responsive_shell_start('', true);
    echo '<div class="empty-state">Fund not found.</div>';
    render_responsive_shell_end();
    render_end();
    return;
}

if (strtolower((string) ($fund['FundAccountStatus'] ?? 'draft')) !== 'published') {
    set_flash('error', 'This fund is currently draft/hidden and cannot be subscribed to.');
    redirect_to('fund', ['slug' => (string) ($fund['slug'] ?? '')]);
}

$minAmount = (float) $fund['FundAmountMinimum'];
$maxAmount = (float) $fund['FundAmountMaximum'];
$defaultAmount = latest_investment_amount($studentId, (int) $fund['FundID']);
if ($defaultAmount <= 0) {
    $defaultAmount = $minAmount;
}

$expiryValue = '';
if (!empty($bank['CreditCardDayExpired'])) {
    $expiryValue = date('m/y', strtotime((string) $bank['CreditCardDayExpired']));
}

$riskLevel = strtolower((string) ($fund['RiskLevel'] ?? 'medium'));
$returnPct = rtrim(rtrim(number_format((float) $fund['ExpectedReturnPercentage'], 2), '0'), '.');

render_head('Subscribe - ' . $fund['FundTitle']);
render_responsive_shell_start('');
?>


<div class="sub-page">
    <div class="sub-banner">
        <div class="sub-banner-left">
            <a href="<?= e(route('fund', ['slug' => $fund['slug']])) ?>" class="sub-banner-back">Back to fund details</a>
            <h1>Subscribe to Fund</h1>
            <p>Complete your investment in <?= e($fund['FundTitle']) ?></p>
        </div>
        <div class="sub-banner-right">
            <div class="sub-banner-return-label">Expected Annual Return</div>
            <div class="sub-banner-return-val"><?= e($returnPct) ?>%</div>
            <div class="sub-banner-return-desc"><?= e($fund['ReturnPolicy'] ?? 'Quarterly') ?> distribution | <?= e((int) $fund['HoldingPeriod']) ?>-month lock-in</div>
        </div>
    </div>

    <form method="post" action="<?= e(action_url('subscribe')) ?>" id="subForm">
        <input type="hidden" name="fund_id" value="<?= e($fund['FundID']) ?>">

        <div class="sub-body">
            <div class="sub-form-area">
                <div class="sub-payment-note">
                    <div class="sub-payment-note-icon">CARD</div>
                    <div>
                        <strong>Direct Card Payment</strong>
                        <p>Your subscription amount will be charged directly to your linked card. Only one card is linked per client account.</p>
                    </div>
                </div>

                <div class="sub-card">
                    <div class="sub-card-header">
                        <div class="sub-card-icon">INV</div>
                        <div>
                            <h2>Investment Amount</h2>
                            <p>Enter how much you wish to invest</p>
                        </div>
                    </div>
                    <div class="sub-card-body">
                        <div class="sub-field">
                            <label class="sub-label">Amount (SAR)</label>
                            <div class="sub-input-wrap">
                                <input class="sub-input" type="number" name="amount" id="sub_amount" min="<?= e($minAmount) ?>" max="<?= e($maxAmount) ?>" step="0.01" value="<?= e($defaultAmount) ?>" oninput="updateEstimate(this.value)" required>
                                <span class="sub-input-badge">SAR</span>
                            </div>
                            <div class="sub-range-hint">
                                <span>Min: <?= e(format_sar($minAmount)) ?></span>
                                <span>Max: <?= e(format_sar($maxAmount)) ?></span>
                            </div>
                        </div>
                        <div class="sub-field">
                            <label class="sub-label">Contact Phone</label>
                            <input class="sub-input" type="tel" name="phone" value="<?= e($student['StudentPhoneNumber']) ?>" required placeholder="e.g. 0512345678" maxlength="10" pattern="\d{10}" inputmode="numeric">
                        </div>
                    </div>
                </div>

                <div class="sub-card">
                    <div class="sub-card-header">
                        <div class="sub-card-icon">PAY</div>
                        <div>
                            <h2>Payment Details</h2>
                            <p><?= $hasSavedCards ? 'Use your linked card from your account, then enter CVV to confirm payment.' : 'No linked card was found. Add a card from your account settings before subscribing.' ?></p>
                        </div>
                    </div>
                    <div class="sub-card-body">
                        <?php if (!$hasSavedCards): ?>
                        <div class="sub-payment-note">
                            <div class="sub-payment-note-icon">INFO</div>
                            <div>
                                <strong>Card required from account</strong>
                                <p>Add or update your linked card from Account Settings, then return here to complete the subscription.</p>
                                <p><a href="<?= e(route('edit-profile', ['error' => 'Please add your card first, then return to subscription.'])) ?>" class="sub-manage-card-link">Add Card Now</a></p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="sub-card-preview">
                            <div class="sub-card-chip"></div>
                            <div class="sub-card-num" id="previewNum"><?= e(mask_card((string) ($bank['CreditCardNumber'] ?? ''))) ?></div>
                            <div class="sub-card-bottom">
                                <div>
                                    <div class="sub-card-name-label">Card Holder</div>
                                    <div class="sub-card-name-val" id="previewName"><?= e(strtoupper((string) ($bank['CreditCardName'] ?? 'CARD HOLDER'))) ?></div>
                                </div>
                                <div>
                                    <div class="sub-card-exp-label">Expires</div>
                                    <div class="sub-card-exp-val" id="previewExp"><?= e($expiryValue ?: 'MM/YY') ?></div>
                                </div>
                            </div>
                            <div class="sub-card-brand">CARD</div>
                        </div>

                        <?php if ($hasSavedCards): ?>
                        <input type="hidden" name="selected_card" id="selectedCardInput" value="<?= e((string) ($bank['CreditCardNumber'] ?? '')) ?>">
                        <div class="sub-saved-cards" id="savedCardsWrap">
                            <?php foreach ($bankCards as $index => $savedCard): ?>
                            <?php
                                $savedExpiry = !empty($savedCard['CreditCardDayExpired']) ? date('m/y', strtotime((string) $savedCard['CreditCardDayExpired'])) : 'MM/YY';
                                $savedNumber = (string) ($savedCard['CreditCardNumber'] ?? '');
                            ?>
                            <label class="sub-saved-card<?= $index === 0 ? ' selected' : '' ?>">
                                <input type="radio" name="saved_card_choice" value="<?= e($savedNumber) ?>" data-card-number="<?= e(mask_card($savedNumber)) ?>" data-card-holder="<?= e(strtoupper((string) ($savedCard['CreditCardName'] ?? 'CARD HOLDER'))) ?>" data-card-expiry="<?= e($savedExpiry) ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                <div>
                                    <div class="sub-saved-card-title"><?= e(mask_card($savedNumber)) ?></div>
                                    <div class="sub-saved-card-meta"><?= e((string) ($savedCard['CreditCardName'] ?? 'Card Holder')) ?> | Expires <?= e($savedExpiry) ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="sub-field">
                            <label class="sub-label">CVV</label>
                            <input class="sub-input" type="password" name="cvv" value="" placeholder="Enter CVV" maxlength="3" pattern="\d{3}" inputmode="numeric" <?= $hasSavedCards ? 'required' : 'disabled' ?>>
                        </div>

                        <div class="sub-card-limit">Linked cards: <?= e((string) min($savedCardCount, 1)) ?>/1</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sub-card">
                    <div class="sub-card-body u-inline-85">
                        <label class="sub-check checked" id="termsCheckWrap">
                            <input type="checkbox" name="terms" value="1" checked required id="termsCheckInput">
                            <span class="sub-check-text">I have read and agree to the <a href="<?= e(route('terms')) ?>" target="_blank">Hassad platform terms and conditions</a> and acknowledge the investment risks associated with this fund.</span>
                        </label>
                        <input type="hidden" name="contract_accepted" id="contractAccepted" value="0">
                    </div>
                    <div class="sub-form-footer">
                        <a href="<?= e(route('fund', ['slug' => $fund['slug']])) ?>" class="sub-btn-back">Back</a>
                        <?php if (!$hasSavedCards): ?>
                        <a href="<?= e(route('edit-profile', ['error' => 'Please add your card first, then return to subscription.'])) ?>" class="sub-btn-submit">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                            Add Card First
                        </a>
                        <?php else: ?>
                        <button type="button" class="sub-btn-submit" id="openContractBtn" <?= $hasSavedCards ? '' : 'disabled' ?>>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Confirm Subscription
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sub-summary">
                <div class="sub-summary-card">
                    <div class="sub-summary-head">
                        <div class="sub-summary-type"><?= e($fund['InvestmentType'] ?? 'Investment Fund') ?></div>
                        <div class="sub-summary-name"><?= e($fund['FundTitle']) ?></div>
                        <div class="sub-summary-return-box">
                            <span class="sub-summary-return-label">Expected Return</span>
                            <span class="sub-summary-return-val"><?= e($returnPct) ?>%</span>
                        </div>
                    </div>

                    <div class="sub-summary-body">
                        <div class="sub-summary-row"><span class="sub-summary-key">Min. Investment</span><span class="sub-summary-val"><?= e(format_sar($minAmount)) ?></span></div>
                        <div class="sub-summary-row"><span class="sub-summary-key">Max. Investment</span><span class="sub-summary-val"><?= e(format_sar($maxAmount)) ?></span></div>
                        <div class="sub-summary-row"><span class="sub-summary-key">Lock-in Period</span><span class="sub-summary-val"><?= e((int) $fund['HoldingPeriod']) ?> months</span></div>
                        <div class="sub-summary-row"><span class="sub-summary-key">Distribution</span><span class="sub-summary-val"><?= e($fund['ReturnPolicy'] ?? 'Quarterly') ?></span></div>
                        <div class="sub-summary-row"><span class="sub-summary-key">Risk Level</span><span class="sub-summary-val"><span class="sub-risk-badge <?= e($riskLevel) ?>"><?= e(ucfirst($riskLevel)) ?></span></span></div>
                    </div>

                    <div class="sub-estimate">
                        <div class="sub-estimate-label">Return Estimate</div>
                        <div class="sub-estimate-row"><span class="sub-estimate-key">Your Investment</span><span class="sub-estimate-val" id="est_principal"><?= e(format_sar($defaultAmount)) ?></span></div>
                        <div class="sub-estimate-row"><span class="sub-estimate-key">Annual Return (<?= e($returnPct) ?>%)</span><span class="sub-estimate-val green" id="est_annual">-</span></div>
                        <hr class="sub-estimate-divider">
                        <div class="sub-estimate-row"><span class="sub-estimate-key u-inline-86">Est. Total at Maturity</span><span class="sub-estimate-val green" id="est_total" class="u-inline-88">-</span></div>
                    </div>

                    <div class="sub-summary-disclaimer">Note: return estimates are indicative and not guaranteed. Investments are subject to Sharia compliance and CMA regulations.</div>
                </div>
            </div>
        </div>
    </form>

    <div id="contractModal" class="u-inline-89">
        <div class="u-inline-90">
            <div class="u-inline-91">
                <strong class="u-inline-92">Subscription Contract</strong>
                <button type="button" id="closeContractBtn" class="u-inline-93">X</button>
            </div>
            <div class="u-inline-94">
                <p><strong>Parties:</strong> Fund Manager and Student Investor.</p>
                <p><strong>Fund:</strong> <?= e($fund['FundTitle']) ?> (ID <?= e((string) $fund['FundID']) ?>)</p>
                <p><strong>Purpose:</strong> Hassad acts as a tracking and subscription intermediary. Financial execution occurs through the official Saudi investment channel.</p>
                <p><strong>Student Commitments:</strong> provide valid payment details, review risk level and terms, and accept lock-in and distribution policy.</p>
                <p><strong>Manager Commitments:</strong> publish accurate fund data, manage fund lifecycle, and process participation workflow according to policy.</p>
                <p><strong>Amount Confirmation:</strong> the amount entered in this page is the intended tracked subscription amount for this fund.</p>
                <p><strong>Risk Notice:</strong> expected returns are not guaranteed and market risk remains with the investor.</p>
                <p><strong>Acceptance:</strong> selecting "Agree & Subscribe" means you accept this contract and proceed with subscription request.</p>
            </div>
            <div class="u-inline-95">
                <button type="button" id="cancelContractBtn" class="sub-btn-back u-inline-87">Cancel</button>
                <button type="button" id="agreeContractBtn" class="sub-btn-submit u-inline-87">Agree & Subscribe</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var returnRate = <?= (float) $fund['ExpectedReturnPercentage'] ?> / 100;
    var duration = <?= (int) ($fund['FundDuration'] ?? $fund['HoldingPeriod']) ?> / 12;

    function fmt(n) {
        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' SAR';
    }

    function syncSelectedCardStyles() {
        var items = document.querySelectorAll('.sub-saved-card');
        items.forEach(function (item) {
            var radio = item.querySelector('input[type="radio"]');
            item.classList.toggle('selected', !!(radio && radio.checked));
        });
    }

    function syncTermsStyle() {
        var wrap = document.getElementById('termsCheckWrap');
        var input = document.getElementById('termsCheckInput');
        if (wrap && input) {
            wrap.classList.toggle('checked', input.checked);
        }
    }

    window.updateEstimate = function (val) {
        var amount = parseFloat(val) || 0;
        var annual = amount * returnRate;
        var total = amount + (annual * duration);
        document.getElementById('est_principal').textContent = fmt(amount);
        document.getElementById('est_annual').textContent = fmt(annual);
        document.getElementById('est_total').textContent = fmt(total);
    };

    var savedCardRadios = document.querySelectorAll('input[name="saved_card_choice"]');
    var selectedCardInput = document.getElementById('selectedCardInput');

    function syncCardMode() {
        var active = document.querySelector('input[name="saved_card_choice"]:checked');
        syncSelectedCardStyles();
        if (!active || !selectedCardInput) {
            return;
        }
        selectedCardInput.value = active.value;
        document.getElementById('previewNum').textContent = active.dataset.cardNumber || '**** **** **** ****';
        document.getElementById('previewName').textContent = active.dataset.cardHolder || 'CARD HOLDER';
        document.getElementById('previewExp').textContent = active.dataset.cardExpiry || 'MM/YY';
    }

    savedCardRadios.forEach(function (radio) {
        radio.addEventListener('change', syncCardMode);
    });

    var termsCheckInput = document.getElementById('termsCheckInput');
    if (termsCheckInput) {
        termsCheckInput.addEventListener('change', syncTermsStyle);
    }

    var openContractBtn = document.getElementById('openContractBtn');
    var contractModal = document.getElementById('contractModal');
    var closeContractBtn = document.getElementById('closeContractBtn');
    var cancelContractBtn = document.getElementById('cancelContractBtn');
    var agreeContractBtn = document.getElementById('agreeContractBtn');
    var contractAccepted = document.getElementById('contractAccepted');
    var subForm = document.getElementById('subForm');

    function hideContractModal() {
        if (contractModal) {
            contractModal.style.display = 'none';
        }
    }

    if (openContractBtn && subForm) {
        openContractBtn.addEventListener('click', function () {
            if (!subForm.reportValidity()) {
                return;
            }
            if (contractModal) {
                contractModal.style.display = 'flex';
            }
        });
    }

    if (closeContractBtn) closeContractBtn.addEventListener('click', hideContractModal);
    if (cancelContractBtn) cancelContractBtn.addEventListener('click', hideContractModal);
    if (agreeContractBtn && subForm) {
        agreeContractBtn.addEventListener('click', function () {
            if (contractAccepted) {
                contractAccepted.value = '1';
            }
            subForm.submit();
        });
    }

    updateEstimate(<?= (float) $defaultAmount ?>);
    syncTermsStyle();
    syncCardMode();
})();
</script>

<?php
render_responsive_shell_end();
render_end();
?>





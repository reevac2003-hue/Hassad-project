<?php
/**
 * ملاحظات توثيقية للصفحة: register-student.php
 * الغرض: تسجيل طالب جديد.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 */
render_head('Create Student Account');
render_responsive_shell_start('Create Student Account');
?>
<form class="form-stack form-stack-compact" method="post" action="<?= e(action_url('register-student')) ?>">
    <div class="register-stepper" aria-label="Registration steps">
        <div class="register-step active" data-step-dot="1">1. Student Information</div>
        <div class="register-step" data-step-dot="2">2. Bank Card (Required)</div>
    </div>

    <section class="register-step-pane active" data-step-pane="1" aria-hidden="false">
        <div class="form-grid two-col">
            <div class="field">
                <label>First Name:</label>
                <input class="text-input" name="first_name" placeholder="Enter first name" required>
            </div>
            <div class="field">
                <label>Last Name:</label>
                <input class="text-input" name="last_name" placeholder="Enter last name" required>
            </div>
            <div class="field">
                <label>University ID:</label>
                <input class="text-input" name="student_id" placeholder="Enter university ID" required>
            </div>
            <div class="field">
                <label>Phone Number:</label>
                <input class="text-input" type="tel" name="phone" placeholder="Enter 10-digit phone number" required maxlength="10" pattern="\d{10}" inputmode="numeric">
            </div>
            <div class="field">
                <label>University / Campus:</label>
                <select class="select-input" name="campus" required>
                    <option value="" disabled selected>Select campus</option>
                    <option>Jubail Industrial College (JIC) - (Male &amp; Female: Al-Fayha / Sudair branches)</option>
                    <option>Jubail Technical Institute (JTI) - (Male &amp; Female)</option>
                    <option>Yanbu Industrial College (YIC) - (Male &amp; Female)</option>
                    <option>Yanbu Technical Institute (YTI) - (Male &amp; Female)</option>
                    <option>High Institute for Elastomer Technology (Yanbu)</option>
                </select>
            </div>
            <div class="field">
                <label>Academic Degree:</label>
                <select class="select-input" name="degree" required>
                    <option value="" disabled selected>Select degree</option>
                    <option>Intermediate Diploma</option>
                    <option>Bachelor's Degree</option>
                </select>
            </div>
            <div class="field">
                <label>Major / Field of Study:</label>
                <select class="select-input" name="major" required>
                    <option value="" disabled selected>Select major</option>
                    <optgroup label="Engineering &amp; Technology">
                        <option>Chemical Engineering</option>
                        <option>Chemical Engineering Technology</option>
                        <option>Mechanical Engineering</option>
                        <option>Mechanical Engineering Technology</option>
                        <option>Electrical Engineering</option>
                        <option>Electrical Power Technology</option>
                        <option>Instrumentation &amp; Control Engineering</option>
                        <option>Electronics &amp; Communication Engineering</option>
                        <option>Civil Engineering</option>
                        <option>Computer Engineering</option>
                        <option>Computer Science</option>
                        <option>Mechatronics Technology</option>
                        <option>Manufacturing Engineering Technology</option>
                        <option>Polymer / Elastomer Technology</option>
                        <option>Industrial Chemistry Technology</option>
                    </optgroup>
                    <optgroup label="Administrative &amp; Business Sciences">
                        <option>Accounting</option>
                        <option>Business Administration</option>
                        <option>Management Information Systems (MIS)</option>
                        <option>Human Resources Management (HRM)</option>
                        <option>Logistics and Supply Chain Management</option>
                        <option>Marketing</option>
                        <option>Office Management</option>
                        <option>Industrial Office Management Assistant</option>
                    </optgroup>
                    <optgroup label="Design, Arts &amp; Linguistics">
                        <option>Architecture</option>
                        <option>Interior Design</option>
                        <option>Interior Design Engineering</option>
                        <option>Applied Linguistics</option>
                        <option>Preparatory Programs (Arts track)</option>
                        <option>Preparatory Programs (Science track)</option>
                    </optgroup>
                    <optgroup label="Technical &amp; Vocational Skills (Diploma Level)">
                        <option>Process Operation</option>
                        <option>Chemical Analysis</option>
                        <option>Mechanical Maintenance Technician</option>
                        <option>Industrial Electrical Maintenance</option>
                        <option>Industrial Instrumentation &amp; Control</option>
                        <option>Computer-Aided Drafting &amp; Design (CADD)</option>
                        <option>Industrial Machining</option>
                        <option>Welding</option>
                        <option>Industrial Millwright</option>
                        <option>Pipefitting</option>
                        <option>Crane Operation</option>
                        <option>Forklift Operation</option>
                        <option>PC Support</option>
                        <option>Network Administration</option>
                    </optgroup>
                </select>
            </div>
            <div class="field input-with-icon">
                <label>Password:</label>
                <input class="text-input" type="password" id="password-input" name="password" placeholder="Enter password" required>
                <span class="input-icon">&#128065;</span>
            </div>
            <div class="field">
                <label class="helper-text">Password Requirements</label>
                <ul class="hint-list password-rules" id="password-rules">
                    <li data-rule="upper">Include at least one uppercase letter.</li>
                    <li data-rule="special">Include at least one special character.</li>
                    <li data-rule="digit">Include at least one number.</li>
                    <li data-rule="length">At least 8 characters.</li>
                    <li data-rule="match">Password and confirm password match.</li>
                </ul>
            </div>
            <div class="field input-with-icon">
                <label>Confirm Password:</label>
                <input class="text-input" type="password" id="confirm-password-input" name="confirm_password" placeholder="Confirm password" required>
                <span class="input-icon">&#128065;</span>
            </div>
        </div>
        <div class="auth-actions">
            <a class="small-btn" href="<?= e(route('home')) ?>">Cancel</a>
            <button class="cta-primary" type="button" id="step-next-btn">Next</button>
        </div>
    </section>

    <section class="register-step-pane" data-step-pane="2" aria-hidden="true" hidden>
        <p class="helper-text">Bank card details are required to complete account creation.</p>
        <div class="form-grid two-col">
            <div class="field">
                <label>Bank Account IBAN:</label>
                <input class="text-input" name="iban" placeholder="SAxxxxxxxxxxxxxxxxxxxxxx" required maxlength="24" pattern="SA\d{22}" title="IBAN must start with SA and include 22 digits after it">
            </div>
            <div class="field">
                <label>Card Number:</label>
                <input class="text-input" name="card_number" placeholder="14-digit card number" required maxlength="14" pattern="\d{14}" inputmode="numeric">
            </div>
            <div class="field">
                <label>Name on Card:</label>
                <input class="text-input" name="card_name" placeholder="Full card holder name" required>
            </div>
            <div class="field">
                <label>Expiry Date:</label>
                <input class="text-input" type="month" name="expiry" required>
            </div>
            <div class="field">
                <label>CVV:</label>
                <input class="text-input" type="password" name="cvv" placeholder="3 digits" required maxlength="3" pattern="\d{3}" inputmode="numeric">
            </div>
        </div>
        <div class="auth-actions register-step-actions">
            <button class="small-btn" type="button" id="step-back-btn">Back</button>
            <button class="cta-primary" type="submit">Create Account</button>
        </div>
    </section>

    <p class="divider-copy mb-0">Already have an account? <a class="inline-link" href="<?= e(route('login')) ?>">Login</a></p>
</form>
<script>
(function () {
    var step1 = document.querySelector('[data-step-pane="1"]');
    var step2 = document.querySelector('[data-step-pane="2"]');
    var dot1 = document.querySelector('[data-step-dot="1"]');
    var dot2 = document.querySelector('[data-step-dot="2"]');
    var nextBtn = document.getElementById('step-next-btn');
    var backBtn = document.getElementById('step-back-btn');

    function showStep(stepNumber) {
        var onFirst = stepNumber === 1;
        step1.classList.toggle('active', onFirst);
        step2.classList.toggle('active', !onFirst);
        step1.hidden = !onFirst;
        step2.hidden = onFirst;
        step1.setAttribute('aria-hidden', onFirst ? 'false' : 'true');
        step2.setAttribute('aria-hidden', onFirst ? 'true' : 'false');
        dot1.classList.toggle('active', onFirst);
        dot2.classList.toggle('active', !onFirst);
    }

    nextBtn.addEventListener('click', function () {
        var requiredStep1 = step1.querySelectorAll('input[required], select[required]');
        for (var i = 0; i < requiredStep1.length; i += 1) {
            if (!requiredStep1[i].reportValidity()) {
                return;
            }
        }
        var allRules = document.querySelectorAll('#password-rules li');
        for (var r = 0; r < allRules.length; r += 1) {
            if (!allRules[r].classList.contains('ok')) {
                return;
            }
        }
        var matchRule = document.querySelector('[data-rule="match"]');
        if (!matchRule || !matchRule.classList.contains('ok')) {
            return;
        }
        showStep(2);
    });

    backBtn.addEventListener('click', function () {
        showStep(1);
    });

    var passwordInput = document.getElementById('password-input');
    var confirmInput = document.getElementById('confirm-password-input');

    function setRuleState(ruleKey, passed) {
        var item = document.querySelector('[data-rule="' + ruleKey + '"]');
        if (!item) return;
        item.classList.toggle('ok', passed);
        item.classList.toggle('bad', !passed);
    }

    function updatePasswordRules() {
        var password = passwordInput.value || '';
        var confirm = confirmInput.value || '';

        setRuleState('upper', /[A-Z]/.test(password));
        setRuleState('special', /[^A-Za-z0-9]/.test(password));
        setRuleState('digit', /\d/.test(password));
        setRuleState('length', password.length >= 8);
        setRuleState('match', password.length > 0 && password === confirm);
    }

    passwordInput.addEventListener('input', updatePasswordRules);
    confirmInput.addEventListener('input', updatePasswordRules);
    updatePasswordRules();
    showStep(1);
})();
</script>
<?php
render_responsive_shell_end();
render_end();
?>



<?php
/**
 * ملاحظات توثيقية للصفحة: terms.php
 * الغرض: عرض الشروط والسياسات.
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
render_head('Terms & Conditions');
render_responsive_shell_start('Terms & Conditions', true);
?>

<div class="terms-page">
    <section class="terms-header">
        <h1>Terms and Conditions - Hassad Platform</h1>
        <p class="terms-subtitle">By using the Hassad platform and creating a new account, you agree to comply with the following terms and conditions.</p>
        <p class="terms-date">Last Updated: <?= date('F d, Y') ?></p>
    </section>

    <section class="terms-content">
        <article class="terms-section">
            <h2>1. Platform Definition</h2>
            <p>Hassad is an educational platform designed to enable students to learn about investment and manage their funds in a simple way. It acts as a technical intermediary to display investment opportunities without holding or managing funds directly.</p>
        </article>

        <article class="terms-section">
            <h2>2. Account Creation</h2>
            <p>Users must provide accurate and truthful information when registering and are fully responsible for the accuracy of the entered data and for keeping login credentials confidential.</p>
        </article>

        <article class="terms-section">
            <h2>3. Platform Usage</h2>
            <p>Users agree to use the platform for legitimate purposes only, and not to misuse it or attempt unauthorized access to any part of the system.</p>
        </article>

        <article class="terms-section">
            <h2>4. Investment and Risks</h2>
            <ul class="terms-list">
                <li>Users acknowledge that all investments involve risks.</li>
                <li>Returns are not guaranteed and investment values may rise or fall.</li>
                <li>Users may incur partial or total loss of capital.</li>
            </ul>
        </article>

        <article class="terms-section">
            <h2>5. Investment Authorization</h2>
            <p>When subscribing to any fund, users agree to grant the fund manager the authority to manage the investment and make investment decisions according to the fund's policy.</p>
        </article>

        <article class="terms-section">
            <h2>6. Returns and Profits</h2>
            <ul class="terms-list">
                <li>All displayed returns are indicative and not guaranteed.</li>
                <li>Actual profits are determined based on the actual performance of the investment.</li>
                <li>Profit distribution follows each fund's policy, after the specified period (e.g., 3 months, 6 months, or 1 year depending on the fund).</li>
            </ul>
        </article>

        <article class="terms-section">
            <h2>7. Fees and Deductions</h2>
            <p>Users agree to a 10% deduction from net profits for JIC according to platform policies.</p>
        </article>

        <article class="terms-section">
            <h2>8. Withdrawal Policy</h2>
            <ul class="terms-list">
                <li>Funds cannot be withdrawn until the end of the investment period specified for each fund, which varies according to each fund's policy (e.g., some funds prevent withdrawal for the first 2 months, others for the first 6 months).</li>
                <li>After the investment period ends, withdrawals are allowed according to each fund's terms.</li>
                <li>Profits are distributed according to the schedule set by each fund after the agreed period (every 3 months, 6 months, or 1 year).</li>
            </ul>
        </article>

        <article class="terms-section">
            <h2>9. Disclaimer</h2>
            <p>Hassad or its operators are not responsible for losses resulting from investment decisions or market fluctuations.</p>
        </article>

        <article class="terms-section">
            <h2>10. Amendments</h2>
            <p>Hassad reserves the right to modify these terms at any time, and users will be notified of any updates.</p>
        </article>

        <article class="terms-section">
            <h2>11. Account Suspension or Termination</h2>
            <p>Hassad may suspend or terminate any account in case of violation of the terms or suspicion of unlawful activity.</p>
        </article>

        <article class="terms-section">
            <h2>12. Applicable Law</h2>
            <p>These terms are governed by the laws of the Kingdom of Saudi Arabia, and disputes will be resolved through the competent authorities.</p>
        </article>
    </section>

    <section class="terms-acceptance">
        <p>By using the Hassad Platform, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.</p>
        <div class="acceptance-actions">
            <a href="<?= e(route('home')) ?>" class="btn-secondary">Back to Home</a>
            <?php if (!is_logged_in_as_student() && !is_logged_in_as_manager()): ?>
            <a href="<?= e(route('register-student')) ?>" class="cta-primary">Create Account</a>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php
render_responsive_shell_end();
render_end();
?>



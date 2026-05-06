<?php
/**
 * ملاحظات توثيقية للصفحة: contracts.php
 * الغرض: عرض العقود للطالب أو المدير بصيغة قراءة واضحة.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - contracts_for_manager(): جلب عقود تخص مديرًا معينًا. القيمة الراجعة: array.
 * - contracts_for_student(): جلب عقود الطالب بصيغة العرض. القيمة الراجعة: array.
 * - current_manager(): جلب بيانات المدير الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - current_student(): جلب بيانات الطالب الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
if (!is_logged_in_as_student() && !is_logged_in_as_manager()) {
    set_flash('error', 'Access denied. Student or Manager login required.');
    redirect_to('login');
}

$isStudent = is_logged_in_as_student();

if ($isStudent) {
    $student = current_student();
    $studentId = (int) ($student['StudentID'] ?? 0);
    if ($studentId <= 0) {
        set_flash('error', 'Unable to identify student account. Please sign in again.');
        redirect_to('login', ['role' => 'student']);
    }
    $contracts = array_values(array_filter(
        contracts_for_student($studentId),
        static fn(array $c): bool => (int) ($c['StudentID'] ?? 0) === $studentId
    ));
    $pageTitle = 'My Contracts';
} else {
    $manager = current_manager();
    $managerLicense = (int) ($manager['FundManagerNumberofLicense'] ?? 0);
    $contracts = contracts_for_manager($managerLicense);
    $pageTitle = 'Contracts';
}

$totalContracts = count($contracts);
$uniqueStudents = $isStudent
    ? 1
    : count(array_unique(array_map(static fn(array $c): int => (int) ($c['StudentID'] ?? 0), $contracts)));
$uniqueFunds = count(array_unique(array_map(static fn(array $c): int => (int) ($c['FundID'] ?? 0), $contracts)));

render_head($pageTitle);
render_responsive_shell_start('');
?>

<div class="clean-dashboard">
    <section class="report-header-clean">
        <h1 class="page-title-clean"><?= e($pageTitle) ?></h1>
        <p class="page-subtitle-clean">
            <?php if ($isStudent): ?>
                Contracts created from your subscriptions.
            <?php else: ?>
                All student contracts linked to your investment funds.
            <?php endif; ?>
        </p>
        <div class="contracts-page-tagline">Track contract performance, participants, and commitments with clear operational visibility.</div>
    </section>

    <section class="stats-section-clean">
        <div class="stats-grid<?= $isStudent ? ' stats-grid--student' : '' ?>">
            <div class="stat-card-clean">
                <span class="stat-icon-clean">Contracts</span>
                <div class="stat-content-clean">
                    
                    <span class="stat-value-clean"><?= $totalContracts ?></span>
                </div>
            </div>

            <?php if (!$isStudent): ?>
            <div class="stat-card-clean">
                <span class="stat-icon-clean">Students</span>
                <div class="stat-content-clean">

                    <span class="stat-value-clean"><?= $uniqueStudents ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="stat-card-clean">
                <span class="stat-icon-clean">Funds</span>
                <div class="stat-content-clean">
               
                    <span class="stat-value-clean"><?= $uniqueFunds ?></span>
                </div>
            </div>
        </div>
    </section>

    <section class="funds-section-clean">
        <h2 class="section-title-clean">Contract List</h2>

        <?php if (empty($contracts)): ?>
        <div class="empty-funds">
            <p>No contracts found.</p>
        </div>
        <?php else: ?>
        <div class="portfolio-table-clean">
            <table class="contracts-table <?= $isStudent ? 'contracts-table-student' : 'contracts-table-manager' ?>">
                <?php if ($isStudent): ?>
                <colgroup>
                    <col class="col-contract">
                    <col class="col-fund">
                    <col class="col-manager">
                    <col class="col-amount">
                    <col class="col-description">
                </colgroup>
                <?php else: ?>
                <colgroup>
                    <col class="col-contract">
                    <col class="col-fund">
                    <col class="col-amount">
                    <col class="col-student">
                    <col class="col-student-id">
                    <col class="col-manager">
                    <col class="col-license">
                    <col class="col-description">
                </colgroup>
                <?php endif; ?>
                <thead>
                    <tr>
                        <th>Contract #</th>
                        <th>Fund</th>
                        <?php if ($isStudent): ?>
                        <th>Created By</th>
                        <?php endif; ?>
                        <th class="th-amount">Amount</th>
                        <?php if (!$isStudent): ?>
                        <th>Student</th>
                        <th>Student ID</th>
                        <th>Manager</th>
                        <th>Manager License</th>
                        <?php endif; ?>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contracts as $contract): ?>
                    <?php $contractId = (int) ($contract['ContractID'] ?? 0); ?>
                    <tr>
                        <td>
                            <button
                                type="button"
                                class="contract-link-btn"
                                data-contract-toggle
                                aria-expanded="false"
                                aria-controls="contract-details-<?= $contractId ?>"
                            >
                                #<?= e((string) $contractId) ?>
                            </button>
                        </td>
                        <td><?= e((string) ($contract['FundTitle'] ?? 'N/A')) ?></td>
                        <?php if ($isStudent): ?>
                        <td><?= e((string) ($contract['ManagerName'] ?? 'N/A')) ?></td>
                        <?php endif; ?>
                        <td class="td-amount"><?= format_sar((float) ($contract['Amount'] ?? 0)) ?></td>
                        <?php if (!$isStudent): ?>
                        <td><?= e((string) ($contract['StudentName'] ?? 'N/A')) ?></td>
                        <td><?= e((string) ($contract['StudentID'] ?? 'N/A')) ?></td>
                        <td><?= e((string) ($contract['ManagerName'] ?? 'N/A')) ?></td>
                        <td><?= e((string) ($contract['ManagerLicense'] ?? 'N/A')) ?></td>
                        <?php endif; ?>
                        <td><?= e((string) ($contract['RolesDescription'] ?? '-')) ?></td>
                    </tr>
                    <tr id="contract-details-<?= $contractId ?>" class="contract-details-row" hidden>
                        <td colspan="<?= $isStudent ? '5' : '8' ?>">
                            <div class="contract-details-card">
                                <div class="contract-details-grid">
                                    <div>
                                        <span class="contract-detail-label">Contract ID</span>
                                        <strong>#<?= e((string) $contractId) ?></strong>
                                    </div>
                                    <div>
                                        <span class="contract-detail-label">Fund</span>
                                        <strong><?= e((string) ($contract['FundTitle'] ?? 'N/A')) ?></strong>
                                    </div>
                                    <?php if ($isStudent): ?>
                                    <div>
                                        <span class="contract-detail-label">Created By</span>
                                        <strong><?= e((string) ($contract['ManagerName'] ?? 'N/A')) ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!$isStudent): ?>
                                    <div>
                                        <span class="contract-detail-label">Student</span>
                                        <strong><?= e((string) ($contract['StudentName'] ?? 'N/A')) ?></strong>
                                    </div>
                                    <div>
                                        <span class="contract-detail-label">Student ID</span>
                                        <strong><?= e((string) ($contract['StudentID'] ?? 'N/A')) ?></strong>
                                    </div>
                                    <div>
                                        <span class="contract-detail-label">Manager</span>
                                        <strong><?= e((string) ($contract['ManagerName'] ?? 'N/A')) ?></strong>
                                    </div>
                                    <div>
                                        <span class="contract-detail-label">Manager License</span>
                                        <strong><?= e((string) ($contract['ManagerLicense'] ?? 'N/A')) ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="contract-detail-label">Investment Amount</span>
                                        <strong><?= format_sar((float) ($contract['Amount'] ?? 0)) ?></strong>
                                    </div>
                                </div>
                                <div class="contract-details-description">
                                    <span class="contract-detail-label">Description</span>
                                    <p><?= e((string) ($contract['RolesDescription'] ?? '-')) ?></p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>

<script>
(function () {
    var toggles = document.querySelectorAll('[data-contract-toggle]');
    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('aria-controls');
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) return;
            var isExpanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
            target.hidden = isExpanded;
        });
    });
})();
</script>

<?php
render_responsive_shell_end();
render_end();



<?php
/**
 * ملاحظات توثيقية للصفحة: jic-participants.php
 * الغرض: إدارة طلبات موافقة المشاركة (JIC/Manager).
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - action_url(): بناء رابط إجراء POST عبر action.php. القيمة الراجعة: string.
 * - all_participation_approvals(): جلب طلبات الموافقة على المشاركة. القيمة الراجعة: array.
 * - current_jic(): جلب بيانات JIC الحالي. القيمة الراجعة: array.
 * - current_manager(): جلب بيانات المدير الحالي من الجلسة/القاعدة. القيمة الراجعة: array.
 * - e(): تعقيم النص قبل الطباعة لمنع XSS. القيمة الراجعة: string.
 * - format_sar(): تنسيق المبلغ بصيغة SAR للعرض. القيمة الراجعة: string.
 * - is_logged_in_as_jic(): التحقق من أن الجلسة الحالية لـ JIC. القيمة الراجعة: bool.
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - render_end(): إغلاق الصفحة وتحميل JS العام. القيمة الراجعة: void.
 * - render_head(): بدء هيكل HTML وتحميل ملفات CSS. القيمة الراجعة: void.
 * - render_responsive_shell_end(): إغلاق الغلاف الموحد للواجهة. القيمة الراجعة: void.
 * - render_responsive_shell_start(): بدء الغلاف الموحد للواجهة (Header/Sidebar). القيمة الراجعة: void.
 * - route(): بناء رابط داخلي آمن للانتقال بين الصفحات. القيمة الراجعة: string.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
/**
 * ط·آµط¸ظ¾ط·آ­ط·آ© ط¸â€¦ط¸ث†ط·آ§ط¸ظ¾ط¸â€ڑط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط·آ´ط·آ§ط·آ±ط¸ئ’ط·آ© - JIC Participation Approvals Page
 * ---------------------------------------------------------
 * ط·ع¾ط·ع¾ط¸ظ¹ط·آ­ ط¸â€‍ط¸â‚¬ JIC ط¸â€¦ط·آ±ط·آ§ط·آ¬ط·آ¹ط·آ© ط¸ث†ط·آ§ط¸â€‍ط¸â€¦ط¸ث†ط·آ§ط¸ظ¾ط¸â€ڑط·آ© ط·آ¹ط¸â€‍ط¸â€° ط·آ·ط¸â€‍ط·آ¨ط·آ§ط·ع¾ ط¸â€¦ط·آ´ط·آ§ط·آ±ط¸ئ’ط·آ© ط·آ§ط¸â€‍ط·آ·ط¸â€‍ط·آ§ط·آ¨
 */

// ط·آ§ط¸â€‍ط·ع¾ط·آ­ط¸â€ڑط¸â€ڑ ط¸â€¦ط¸â€  ط·ع¾ط·آ³ط·آ¬ط¸ظ¹ط¸â€‍ ط·آ¯ط·آ®ط¸ث†ط¸â€‍ JIC ط·آ£ط¸ث† ط·آ§ط¸â€‍ط¸â€¦ط·آ¯ط¸ظ¹ط·آ±
if (!is_logged_in_as_jic() && !is_logged_in_as_manager()) {
    set_flash('error', 'Access denied. JIC or Manager login required.');
    redirect_to('login', ['role' => 'jic']);
}

$jic = is_logged_in_as_jic() ? current_jic() : ['JicUsername' => current_manager()['FundManagerNameFirst']];

// ط·آ¬ط¸â€‍ط·آ¨ ط·آ·ط¸â€‍ط·آ¨ط·آ§ط·ع¾ ط·آ§ط¸â€‍ط¸â€¦ط¸ث†ط·آ§ط¸ظ¾ط¸â€ڑط·آ©
$pendingApprovals = all_participation_approvals('Pending');
$approvedApprovals = all_participation_approvals('Approved');
$rejectedApprovals = all_participation_approvals('Rejected');

$activeFilter = $_GET['filter'] ?? 'pending';

// ط¸â€¦ط·آ¹ط·آ§ط¸â€‍ط·آ¬ط·آ© ط·آ±ط·آ³ط·آ§ط·آ¦ط¸â€‍ Flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

render_head('Participation Approvals');
render_responsive_shell_start('');
?>

<div class="participants-page">
    <!-- Header -->
    <div class="page-header">
        <a href="<?= e(route('home')) ?>" class="back-link">Back to Home</a>
        <h1>Student Participation Approvals</h1>
        <p>Review and approve student investment applications</p>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-item pending">
            <span class="stat-value"><?= count($pendingApprovals) ?></span>
            <span class="stat-label">Pending</span>
        </div>
        <div class="stat-item approved">
            <span class="stat-value"><?= count($approvedApprovals) ?></span>
            <span class="stat-label">Approved</span>
        </div>
        <div class="stat-item rejected">
            <span class="stat-value"><?= count($rejectedApprovals) ?></span>
            <span class="stat-label">Rejected</span>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-tabs">
        <a href="<?= e(route('jic-participants', ['filter' => 'pending'])) ?>" 
           class="filter-tab <?= $activeFilter === 'pending' ? 'active' : '' ?>">
            Pending (<?= count($pendingApprovals) ?>)
        </a>
        <a href="<?= e(route('jic-participants', ['filter' => 'approved'])) ?>" 
           class="filter-tab <?= $activeFilter === 'approved' ? 'active' : '' ?>">
            Approved (<?= count($approvedApprovals) ?>)
        </a>
        <a href="<?= e(route('jic-participants', ['filter' => 'rejected'])) ?>" 
           class="filter-tab <?= $activeFilter === 'rejected' ? 'active' : '' ?>">
            Rejected (<?= count($rejectedApprovals) ?>)
        </a>
    </div>

    <!-- Content -->
    <div class="content-section">
        <?php
        $displayApprovals = match($activeFilter) {
            'approved' => $approvedApprovals,
            'rejected' => $rejectedApprovals,
            default => $pendingApprovals,
        };
        ?>

        <?php if (empty($displayApprovals)): ?>
        <div class="empty-state">
            <div class="empty-icon">NONE</div>
            <h3>No <?= e(ucfirst($activeFilter)) ?> Requests</h3>
            <p>There are no <?= e(strtolower($activeFilter)) ?> participation requests at this time.</p>
        </div>
        <?php else: ?>
        <div class="approvals-list">
            <?php foreach ($displayApprovals as $approval): ?>
            <div class="approval-card">
                <div class="card-header">
                    <div class="student-info">
                        <span class="student-avatar">ST</span>
                        <div>
                            <h4><?= e(($approval['Student']['StudentNameFirst'] ?? 'Student') . ' ' . ($approval['Student']['StudentNameLast'] ?? '')) ?></h4>
                            <span class="student-id">ID: <?= e($approval['StudentID']) ?></span>
                        </div>
                    </div>
                    <span class="status-badge status-<?= strtolower($approval['ApprovalStatus'] ?? 'pending') ?>">
                        <?= e($approval['ApprovalStatus'] ?? 'Pending') ?>
                    </span>
                </div>

                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Fund</span>
                            <span class="info-value"><?= e($approval['Fund']['FundTitle'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Request Date</span>
                            <span class="info-value"><?= e($approval['RequestedAt'] ?? date('Y-m-d')) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Investment Amount</span>
                            <span class="info-value"><?= format_sar($approval['RequestedAmount'] ?? 0) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Campus</span>
                            <span class="info-value"><?= e($approval['Student']['StudentCampus'] ?? 'N/A') ?></span>
                        </div>
                    </div>

                    <?php if (!empty($approval['RejectionReason'])): ?>
                    <div class="rejection-reason">
                        <strong>Rejection Reason:</strong> <?= e($approval['RejectionReason']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($activeFilter === 'pending'): ?>
                <div class="card-actions">
                    <form action="<?= e(action_url('approve-participation')) ?>" method="POST" class="action-form">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="student_id" value="<?= e($approval['StudentID']) ?>">
                        <input type="hidden" name="fund_id" value="<?= e($approval['FundID']) ?>">
                        <button type="submit" class="btn-approve">Approve</button>
                    </form>
                    
                    <button type="button" class="btn-reject" onclick="showRejectModal(<?= e($approval['StudentID']) ?>, <?= e($approval['FundID']) ?>)">
                        Reject
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($activeFilter === 'approved' && !empty($approval['ApprovedAt'])): ?>
                <div class="card-footer">
                    <span>Approved on <?= e($approval['ApprovedAt']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal u-inline-75" hidden style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reject Participation Request</h3>
            <button class="modal-close" onclick="closeRejectModal()">&times;</button>
        </div>
        <form action="<?= e(action_url('reject-participation')) ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="student_id" id="rejectStudentId">
            <input type="hidden" name="fund_id" id="rejectFundId">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="rejection_reason">Reason for Rejection</label>
                    <textarea name="rejection_reason" id="rejection_reason" required 
                              placeholder="Please provide a reason for rejecting this request..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn-reject">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(studentId, fundId) {
    document.getElementById('rejectStudentId').value = studentId;
    document.getElementById('rejectFundId').value = fundId;
    document.getElementById('rejectModal').hidden = false;
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectModal').hidden = true;
    document.getElementById('rejectModal').style.display = 'none';
}

closeRejectModal();

// Close modal when clicking outside
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});
</script>


<?php
render_responsive_shell_end();
render_end();
?>






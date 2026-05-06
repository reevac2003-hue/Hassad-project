<?php
/**
 * ملاحظات توثيقية للصفحة: reports.php
 * الغرض: توجيه المستخدم إلى صفحة التقارير المناسبة لدوره.
 *
 * الدوال المستدعاة في هذه الصفحة ولماذا:
 * - is_logged_in_as_manager(): التحقق من أن الجلسة الحالية لمدير صندوق. القيمة الراجعة: bool.
 * - is_logged_in_as_student(): التحقق من أن الجلسة الحالية لطالب. القيمة الراجعة: bool.
 * - redirect_to(): إيقاف الصفحة الحالية وتحويل المستخدم لمسار مناسب. القيمة الراجعة: void.
 * - set_flash(): تخزين رسالة مؤقتة للواجهة (نجاح/خطأ). القيمة الراجعة: void.
 */
/**
 * Reports Redirect Page
 * --------------------------------
 * Redirects to the appropriate reports page based on user role
 */

// Check login status and redirect
if (is_logged_in_as_manager()) {
    redirect_to('manager-reports');
} elseif (is_logged_in_as_student()) {
    $student = current_student();
    $studentId = (int) ($student['StudentID'] ?? 0);
    $contracts = student_contracts($studentId);

    if (empty($contracts)) {
        set_flash('error', 'No subscribed funds found for your account yet.');
        redirect_to('student-dashboard');
    }

    $fundId = (int) ($contracts[0]['FundID'] ?? 0);
    $fund = $fundId > 0 ? fund_by_id($fundId) : null;
    $slug = (string) ($fund['slug'] ?? '');

    if ($slug === '') {
        set_flash('error', 'Unable to open report because the fund link is missing.');
        redirect_to('student-dashboard');
    }

    redirect_to('investment-report', ['slug' => $slug]);
} else {
    set_flash('error', 'Access denied. Please login first.');
    redirect_to('login');
}



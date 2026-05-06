<?php
/**
 * seed-withdrawal-test.php
 * ────────────────────────
 * اسكريبت مؤقت لإنشاء بيانات اختبارية تتيح تجربة صفحة اعتماد السحب.
 *
 * ماذا يفعل:
 *  1. يجلب أول صندوق منشور ويرجع تاريخ بدايته 2 سنة للوراء
 *     (بحيث تكون فترة الإمساك قد انتهت مهما كانت)
 *  2. يجلب أول طالب مسجّل
 *  3. يضيف 5,000 SAR إلى InvestmentWalletReturn للطالب
 *     (هذا هو الرصيد الذي يمكن سحبه)
 *  4. ينشئ سجل سحب بالحالة Pending إذا لم يوجد
 *
 * شغّله مرة واحدة من المتصفح:  http://localhost/hassad/seed-withdrawal-test.php
 * ثم احذفه أو غيّر اسمه بعد التجربة.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db_connection();
if (!$pdo) {
    die('خطأ: تعذر الاتصال بقاعدة البيانات.');
}

$output = [];

/* ── 1. أول صندوق منشور ── */
$fund = repo_fetch_one("SELECT * FROM Fund WHERE FundAccountStatus = 'published' ORDER BY FundID ASC LIMIT 1");
if (!$fund) {
    $fund = repo_fetch_one("SELECT * FROM Fund ORDER BY FundID ASC LIMIT 1");
}
if (!$fund) {
    die('لا يوجد أي صندوق في قاعدة البيانات. أنشئ صندوقاً أولاً.');
}
$fundId = (int) $fund['FundID'];

/* أرجع تاريخ بداية الصندوق سنتين للوراء */
$newStart = date('Y-m-d', strtotime('-2 years'));
$newEnd   = date('Y-m-d', strtotime('-6 months'));
$ok = repo_execute(
    'UPDATE Fund SET FundDateStart = ?, FundDateEnd = ?, HoldingPeriod = 1 WHERE FundID = ?',
    [$newStart, $newEnd, $fundId]
);
$output[] = $ok
    ? "✅ الصندوق #{$fundId} ({$fund['FundTitle']}): FundDateStart = {$newStart} | HoldingPeriod = 1 شهر"
    : "⚠️ فشل تحديث تاريخ الصندوق #{$fundId}";

/* ── 2. أول طالب مسجّل ── */
$student = repo_fetch_one("SELECT * FROM Student ORDER BY StudentID ASC LIMIT 1");
if (!$student) {
    die('لا يوجد أي طالب في قاعدة البيانات.');
}
$studentId = (int) $student['StudentID'];
$studentName = $student['StudentNameFirst'] . ' ' . $student['StudentNameLast'];

/* ── 3. أضف 5,000 SAR كأرباح إلى المحفظة ── */
$wallet = repo_fetch_one('SELECT * FROM InvestmentWallet WHERE StudentID = ?', [$studentId]);
if ($wallet) {
    $newReturn = (float)($wallet['InvestmentWalletReturn'] ?? 0) + 5000;
    repo_execute(
        'UPDATE InvestmentWallet SET InvestmentWalletReturn = ? WHERE StudentID = ?',
        [$newReturn, $studentId]
    );
    $output[] = "✅ تم إضافة 5,000 SAR أرباح للطالب {$studentName} (ID: {$studentId}) — الرصيد الجديد: " . number_format($newReturn, 2) . " SAR";
} else {
    /* أنشئ محفظة جديدة */
    repo_execute(
        'INSERT INTO InvestmentWallet (StudentID, InvestmentWalletTotalAmount, InvestmentWalletReturn, InvestmentWalletCredit) VALUES (?, 5000, 5000, 0)',
        [$studentId]
    );
    $output[] = "✅ أُنشئت محفظة جديدة للطالب {$studentName} (ID: {$studentId}) بـ 5,000 SAR أرباح";
}

/* ── 4. أنشئ طلب سحب بالحالة Pending إذا لم يوجد ── */
$existing = repo_fetch_one(
    "SELECT WithdrawalID FROM WithdrawalRequest WHERE StudentID = ? AND Status = 'Pending' LIMIT 1",
    [$studentId]
);
if (!$existing) {
    $inserted = repo_execute(
        "INSERT INTO WithdrawalRequest (StudentID, FundID, Amount, WithdrawalType, Status, RequestDate)
         VALUES (?, ?, 1000, 'Profit', 'Pending', NOW())",
        [$studentId, $fundId]
    );
    $output[] = $inserted
        ? "✅ تم إنشاء طلب سحب جديد (1,000 SAR) للطالب {$studentName} — ستراه الآن في لوحة المدير"
        : "⚠️ فشل إنشاء طلب السحب";
} else {
    $output[] = "ℹ️ يوجد بالفعل طلب سحب معلّق للطالب {$studentName} (ID: {$existing['WithdrawalID']})";
}

echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">
<title>بيانات اختبار السحب</title>
<style>
body{font-family:Arial,sans-serif;max-width:700px;margin:40px auto;background:#f5f5f0;padding:20px}
h1{color:#45644a}
ul{background:#fff;border-radius:10px;padding:20px 30px;list-style:none;border:1px solid #ddd}
li{padding:10px 0;border-bottom:1px solid #eee;font-size:15px}
li:last-child{border-bottom:none}
.done{margin-top:24px;padding:14px;background:#e8f5e9;border-radius:8px;color:#2e7d32;font-weight:bold}
a{color:#45644a;font-weight:bold}
</style></head><body>';
echo '<h1>✅ بيانات اختبار السحب جاهزة</h1>';
echo '<ul>';
foreach ($output as $line) {
    echo '<li>' . htmlspecialchars($line, ENT_QUOTES) . '</li>';
}
echo '</ul>';
echo '<div class="done">
  الخطوات التالية:<br><br>
  1. سجّل دخول كطالب (StudentID: ' . $studentId . ') وادخل صفحة <a href="withdraw.php">السحب</a> لإرسال طلب إضافي إذا أردت<br>
  2. سجّل دخول كمدير وادخل <a href="index.php?page=manager-dashboard">لوحة التحكم</a> — ستجد طلب السحب تحت قسم "Withdrawal Requests"<br>
  3. اضغط Approve أو Reject لاختبار الميزة<br><br>
  ⚠️ احذف هذا الملف بعد الاختبار: <code>seed-withdrawal-test.php</code>
</div>';
echo '</body></html>';

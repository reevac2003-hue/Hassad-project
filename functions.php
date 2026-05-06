<?php

declare(strict_types=1);

// Security helpers.

/**
 * دالة تحويل المخرجات إلى HTML آمن
 * --------------------------------
 * تُحوّل الأحرف الخاصة في القيمة إلى كيانات HTML لمنع هجمات XSS
 *
 * @param mixed $value القيمة المراد تهريبها
 * @return string النص بعد التهريب الآمن
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Routing helpers.

/**
 * دالة بناء رابط الصفحة
 * --------------------------------
 * تُنشئ رابطاً كاملاً لصفحة داخل التطبيق مع إمكانية إضافة معاملات في الرابط
 *
 * @param string $page  اسم الصفحة المطلوبة
 * @param array  $params معاملات إضافية تُضاف إلى الرابط
 * @return string الرابط الكامل للصفحة
 */
function route(string $page, array $params = []): string
{
    $config = app_config();
    $base = rtrim($config['base_url'] ?? '', '/');
    $query = array_merge(['page' => $page], $params);
    return $base . '/index.php?' . http_build_query($query);
}

/**
 * دالة بناء رابط الإجراء
 * --------------------------------
 * تُنشئ رابطاً كاملاً يشير إلى نقطة النهاية action.php لتنفيذ إجراء معين
 *
 * @param string $action اسم الإجراء المطلوب تنفيذه
 * @return string الرابط الكامل لنقطة النهاية
 */
function action_url(string $action): string
{
    $config = app_config();
    $base = rtrim($config['base_url'] ?? '', '/');
    return $base . '/action.php?action=' . urlencode($action);
}

/**
 * دالة الرابط الأساسي للتطبيق
 * --------------------------------
 * تُعيد الرابط الجذري للتطبيق كما هو مضبوط في الإعدادات
 *
 * @return string الرابط الأساسي منتهياً بشرطة مائلة /
 */
function url_base(): string
{
    $config = app_config();
    return rtrim($config['base_url'] ?? '', '/') . '/';
}

/**
 * دالة إعادة التوجيه إلى صفحة أخرى
 * --------------------------------
 * تُعيد توجيه المستخدم إلى صفحة داخلية وتُنهي تنفيذ السكريبت فوراً
 *
 * @param string $page   اسم الصفحة المراد التوجيه إليها
 * @param array  $params معاملات إضافية تُضاف إلى الرابط
 * @return void
 */
function redirect_to(string $page, array $params = []): void
{
    header('Location: ' . route($page, $params));
    exit;
}

// Formatting helpers.

/**
 * دالة تنسيق المبلغ بالريال السعودي
 * --------------------------------
 * تُنسّق المبلغ المالي وتُعيده بصيغة SAR مع خانتين عشريتين
 *
 * @param mixed $amount المبلغ المراد تنسيقه
 * @return string المبلغ منسّقاً (مثال: SAR 1,250.00)
 */
function format_sar(mixed $amount): string
{
    return 'SAR ' . number_format((float) $amount, 2);
}

/**
 * دالة إخفاء رقم الهاتف
 * --------------------------------
 * تُخفي الجزء الأوسط من رقم الهاتف لحماية خصوصية المستخدم
 *
 * @param string $phone رقم الهاتف الأصلي
 * @return string رقم الهاتف مع إخفاء الأحرف الوسطى (مثال: 055***6789)
 */
function mask_phone(string $phone): string
{
    $len = strlen($phone);
    if ($len < 6) {
        return $phone;
    }
    return substr($phone, 0, 3) . '***' . substr($phone, -4);
}

/**
 * دالة إخفاء رقم البطاقة البنكية أو الآيبان
 * --------------------------------
 * تُخفي الأرقام الوسطى من رقم البطاقة أو الآيبان وتُبقي أول وآخر أربعة أحرف
 *
 * @param string $card رقم البطاقة أو الآيبان الأصلي
 * @return string الرقم مع إخفاء الأرقام الوسطى (مثال: 1234 **** **** 5678)
 */
function mask_card(string $card): string
{
    $clean = preg_replace('/\s+/', '', $card);
    $len = strlen($clean);
    if ($len < 8) {
        return $card;
    }
    return substr($clean, 0, 4) . ' **** **** ' . substr($clean, -4);
}

/**
 * دالة تحديد فئة CSS لمستوى المخاطرة
 * --------------------------------
 * تُعيد اسم فئة CSS المناسبة بناءً على مستوى المخاطرة المُمرَّر
 *
 * @param string $level مستوى المخاطرة (low / medium / high)
 * @return string اسم فئة CSS المقابلة (risk-low / risk-medium / risk-high)
 */
function risk_class(string $level): string
{
    return match (strtolower($level)) {
        'low'    => 'risk-low',
        'medium' => 'risk-medium',
        'high'   => 'risk-high',
        default  => 'risk-medium',
    };
}

/**
 * دالة تكبير أول حرف من النص
 * --------------------------------
 * تُحوّل النص إلى حروف صغيرة ثم تُكبّر الحرف الأول بعد إزالة المسافات الزائدة
 *
 * @param string $text النص المراد معالجته
 * @return string النص مع تكبير الحرف الأول
 */
function capitalize(string $text): string
{
    return ucfirst(strtolower(trim($text)));
}

// Flash message helpers.

/**
 * دالة تخزين رسالة فلاش مؤقتة
 * --------------------------------
 * تحفظ رسالة مرة واحدة في الجلسة لتُعرض في الطلب التالي ثم تُحذف تلقائياً
 *
 * @param string $type    نوع الرسالة (success / error / info)
 * @param string $message نص الرسالة المراد عرضها
 * @return void
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * دالة عرض وحذف رسالة الفلاش
 * --------------------------------
 * تقرأ رسالة الفلاش المخزّنة في الجلسة وتُنتج كود HTML لعرضها ثم تحذفها
 *
 * @return string كود HTML للرسالة، أو نص فارغ إن لم توجد رسالة
 */
function flash_message(): string
{
    if (!isset($_SESSION['flash'])) {
        return '';
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = e($flash['type']);
    $msg  = e($flash['message']);
    return '<div class="toast ' . $type . '" data-live-toast>' . $msg . '</div>';
}

// Authentication helpers.

/**
 * دالة التحقق من تسجيل دخول الطالب
 * --------------------------------
 * تتحقق هل المستخدم الحالي مسجّل دخول كطالب
 *
 * @return bool true إذا كان مسجّل دخول كطالب
 */
function is_logged_in_as_student(): bool
{
    return ($_SESSION['auth']['role'] ?? '') === 'student';
}

/**
 * دالة التحقق من تسجيل دخول مدير الصندوق
 * --------------------------------
 * تتحقق هل المستخدم الحالي مسجّل دخول كمدير صندوق
 *
 * @return bool true إذا كان مسجّل دخول كمدير صندوق
 */
function is_logged_in_as_manager(): bool
{
    return ($_SESSION['auth']['role'] ?? '') === 'manager';
}

/**
 * دالة التحقق من تسجيل دخول المدير الإداري
 * --------------------------------
 * تتحقق هل المستخدم الحالي مسجّل دخول كمدير إداري (admin)
 *
 * @return bool true إذا كان مسجّل دخول كمدير إداري
 */
function is_logged_in_as_admin(): bool
{
    return ($_SESSION['auth']['role'] ?? '') === 'admin';
}

/**
 * دالة التحقق من تسجيل دخول الجهة الراعية JIC
 * --------------------------------
 * تتحقق هل المستخدم الحالي مسجّل دخول كجهة راعية
 *
 * @return bool true إذا كان مسجّل دخول كجهة راعية
 */
function is_logged_in_as_jic(): bool
{
    return ($_SESSION['auth']['role'] ?? '') === 'jic';
}

/**
 * دالة إلزام تسجيل دخول الطالب
 * --------------------------------
 * تتحقق من تسجيل دخول الطالب وتعيد توجيهه إذا لم يكن مسجّلاً
 */
function require_student_login(): void
{
    if (!is_logged_in_as_student()) {
        set_flash('error', 'Access denied. Student login required.');
        redirect_to('login', ['role' => 'student']);
    }
}

/**
 * دالة إلزام تسجيل دخول المدير
 * --------------------------------
 * تتحقق من تسجيل دخول مدير الصندوق وتعيد توجيهه إذا لم يكن مسجّلاً
 */
function require_manager_login(): void
{
    if (!is_logged_in_as_manager()) {
        set_flash('error', 'Access denied. Manager login required.');
        redirect_to('login', ['role' => 'manager']);
    }
}

/**
 * دالة إلزام تسجيل دخول المدير الإداري
 * --------------------------------
 * تتحقق من تسجيل دخول المدير الإداري وتعيد توجيهه إذا لم يكن مسجّلاً
 */
function require_admin_login(): void
{
    if (!is_logged_in_as_admin()) {
        set_flash('error', 'Access denied. Admin login required.');
        redirect_to('login', ['role' => 'admin']);
    }
}

/**
 * دالة إلزام تسجيل دخول الجهة الراعية
 * --------------------------------
 * تتحقق من تسجيل دخول JIC وتعيد توجيهه إذا لم يكن مسجّلاً
 */
function require_jic_login(): void
{
    if (!is_logged_in_as_jic()) {
        set_flash('error', 'Access denied. JIC login required.');
        redirect_to('login', ['role' => 'jic']);
    }
}



// ===================================================
// 6. دوال العرض والتصيير (Rendering)
// ===================================================

/**
 * دالة عرض رأس الصفحة HTML
 * --------------------------------
 * تُخرج وسوم HTML الافتتاحية وتحميل CSS والخطوط
 * تُستدعى في بداية كل صفحة
 *
 * @param string $title عنوان الصفحة
 */
function render_head(string $title = 'Hassad'): void
{
    $config = app_config();
    $appName = $config['app_name'] ?? 'Hassad';
    $base = rtrim($config['base_url'] ?? '', '/');
    $pageStyleFile = resolve_current_page_stylesheet();
    ?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/theme-colors.css">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/inline-utilities.css">
    <?php if ($pageStyleFile !== null): ?>
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/pages/<?= e($pageStyleFile) ?>">
    <?php endif; ?>
</head>
<body>
    <?= flash_message() ?>
    <?php
}

/**
 * دالة تحديد ملف CSS الخاص بالصفحة الحالية
 * --------------------------------
 * تعتمد على قيمة page في الرابط (مثال: search-funds => search-funds.css)
 * وتتحقق من وجود الملف داخل assets/css/pages قبل إرجاعه.
 *
 * @return string|null اسم الملف إذا كان موجوداً، أو null إن لم يوجد
 */
function resolve_current_page_stylesheet(): ?string
{
    $page = trim((string) ($_GET['page'] ?? ''));
    if ($page === '') {
        return null;
    }

    $safePage = preg_replace('/[^a-zA-Z0-9\-]/', '', $page);
    if ($safePage === '') {
        return null;
    }

    $fileName = $safePage . '.css';
    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $fileName;

    return is_file($fullPath) ? $fileName : null;
}

/**
 * دالة تحميل ملف CSS خاص بصفحة معينة
 * --------------------------------
 * تُضيف رابط CSS من مجلد assets/css/pages إن كان الملف موجوداً.
 * الهدف: فصل تنسيقات الصفحات عن ملفات PHP وتحسين الصيانة.
 *
 * @param string $fileName اسم ملف CSS داخل assets/css/pages
 */
function render_page_styles(string $fileName): void
{
    $config = app_config();
    $base = rtrim($config['base_url'] ?? '', '/');
    $safeName = trim(basename($fileName));
    if ($safeName === '') {
        return;
    }

    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $safeName;
    if (!is_file($fullPath)) {
        return;
    }
    ?>
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/pages/<?= e($safeName) ?>">
    <?php
}

/**
 * دالة عرض نهاية الصفحة HTML
 * --------------------------------
 * تُخرج وسوم HTML الختامية وتحميل JavaScript
 * تُستدعى في نهاية كل صفحة
 */
function render_end(): void
{
    $config = app_config();
    $base = rtrim($config['base_url'] ?? '', '/');
    ?>
    <script src="<?= e($base) ?>/assets/js/app.js"></script>
</body>
</html>
    <?php
}

/**
 * دالة بدء الهيكل الرئيسي للصفحة
 * --------------------------------
 * تعرض الهيكل العام للصفحة (القالب الخارجي)
 * تشمل: الهيدر، شريط العنوان، القائمة الجانبية
 *
 * @param string $title عنوان الصفحة في الهيدر
 * @param bool   $showBack عرض زر الرجوع
 */
function render_responsive_shell_start(string $title = '', bool $showBack = true): void
{
    $base = rtrim(app_config()['base_url'] ?? '', '/');
    $role = $_SESSION['auth']['role'] ?? '';
    $homeRoute = match ($role) {
        'manager' => route('manager-dashboard'),
        'student' => route('student-dashboard'),
        default => route('home'),
    };
    ?>
    <div class="preview-body desktop-preview">
    <div class="screen responsive-screen">
    <!-- Header -->
    <header class="desktop-header">
        <div class="desktop-top">
            <a class="header-brand" href="<?= e($homeRoute) ?>">
                <img src="<?= e($base) ?>/assets/images/logo.png" alt="Hassad Logo">
            </a>
            <div class="desktop-icons">
                <?php if ($role): ?>
                <a class="icon-link" href="<?= e($role === 'student' ? route('student-settings') : route('edit-profile')) ?>">
                    <span class="icon-btn">👤</span>
                </a>
                <?php endif; ?>
                <?php if ($showBack): ?>
                <a class="icon-link" href="javascript:history.back()">
                    <span class="icon-btn">←</span>
                </a>
                <?php endif; ?>
                <button class="icon-btn" data-menu-toggle aria-expanded="false">☰</button>
            </div>
        </div>
        <?php if ($title): ?>
        <div class="title-block">
            <h1><?= e($title) ?></h1>
            <span class="title-line"></span>
        </div>
        <?php endif; ?>
    </header>

    <!-- Side menu -->
    <nav class="floating-menu" data-menu>
        <?php render_menu_items($role); ?>
        <button data-menu-close>✕ Close Menu</button>
    </nav>

    <main>
    <div class="desktop-body">
    <?php
}

/**
 * دالة نهاية الهيكل الرئيسي للصفحة
 * --------------------------------
 * تُغلق وسوم HTML المفتوحة في render_responsive_shell_start
 */
function render_responsive_shell_end(): void
{
    ?>
    </div><!-- /.desktop-body -->
    </main>
    </div><!-- /.screen -->
    </div><!-- /.preview-body -->
    <?php
}

/**
 * دالة عرض عناصر القائمة حسب الدور
 * --------------------------------
 * تعرض روابط القائمة الجانبية المناسبة لدور المستخدم
 *
 * @param string $role دور المستخدم الحالي
 */
function render_menu_items(string $role): void
{
    // Inline SVG icons (18×18, currentColor)
    $svgHome     = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>';
    $svgContracts= '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>';
    $svgSearch   = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
    $svgWallet   = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M16 12h2"/><path d="M2 10h20"/></svg>';
    $svgReports  = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>';
    $svgCards    = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
    $svgSettings = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
    $svgLogout   = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    $svgCreate   = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';
    $svgAccess   = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>';
    $svgSms      = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    $svgReview   = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>';
    $svgProfile  = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    $svgTerms    = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
    $svgPeople   = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
    $svgLogin    = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>';
    $svgRegister = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>';

    $items = [];

    switch ($role) {
        case 'student':
            $items = [
                [$svgHome,      'Home',         route('student-dashboard')],
                [$svgContracts, 'Contracts',    route('contracts')],
                [$svgSearch,    'Search Funds', route('search-funds')],
                [$svgWallet,    'My Wallet',    route('wallet')],
                [$svgReports,   'Reports',      route('reports')],
                [$svgCards,     'Bank Cards',   route('bank-cards')],
                [$svgSettings,  'Settings',     route('student-settings')],
                [$svgLogout,    'Logout',       route('home')],
            ];
            break;
        case 'manager':
            $items = [
                [$svgHome,      'Home',         route('manager-dashboard')],
                [$svgContracts, 'Contracts',    route('contracts')],
                [$svgCreate,    'Create Fund',  route('create-fund')],
                [$svgSearch,    'Search Funds', route('search-funds')],
                [$svgReview,    'Approvals',    route('jic-participants')],
                [$svgReports,   'Reports',      route('manager-reports')],
                [$svgProfile,   'Edit Profile', route('edit-profile')],
                [$svgTerms,     'Terms',        route('terms')],
                [$svgLogout,    'Logout',       route('home')],
            ];
            break;
        case 'admin':
            $items = [
                [$svgHome,     'Home',           route('admin-dashboard')],
                [$svgCreate,   'Create User',    route('admin-dashboard', ['tab' => 'create-user'])],
                [$svgAccess,   'Access Levels',  route('admin-dashboard', ['tab' => 'access-levels'])],
                [$svgSettings, 'System Settings',route('system-settings')],
                [$svgSms,      'Send SMS',       route('send-notification')],
                [$svgLogout,   'Logout',         route('home')],
            ];
            break;
        case 'jic':
            $items = [
                [$svgHome,   'Dashboard',   route('home')],
                [$svgPeople, 'Participants',route('jic-participants')],
                [$svgLogout, 'Logout',      route('home')],
            ];
            break;
        default:
            $items = [
                [$svgLogin,    'Login',    route('login')],
                [$svgRegister, 'Register', route('register-student')],
                [$svgTerms,    'Terms',    route('terms')],
            ];
            break;
    }

    $base = rtrim(app_config()['base_url'] ?? '', '/');

    foreach ($items as [$icon, $label, $href]) {
        if ($label === 'Logout') {
            echo '<a href="' . e($base) . '/logout.php"><span class="menu-icon">' . $icon . '</span>' . e($label) . '</a>';
        } else {
            echo '<a href="' . e($href) . '"><span class="menu-icon">' . $icon . '</span>' . e($label) . '</a>';
        }
    }
}

/**
 * دالة عرض تذييل الصفحة
 * --------------------------------
 * تعرض قسم Footer مع معلومات الحقوق والروابط
 * تُستخدم في الصفحة الرئيسية
 */
function render_footer(): void
{
    $role = $_SESSION['auth']['role'] ?? '';
    $homeRoute = match ($role) {
        'manager' => route('manager-dashboard'),
        'student' => route('student-dashboard'),
        default => route('home'),
    };
    ?>
    <footer class="footer-bar-clean">
        <div class="footer-col-clean">
            <strong>Contact</strong>
            <a class="footer-link-clean" href="<?= e($homeRoute) ?>">Home</a>
        </div>
        <div class="footer-col-clean">
            <strong>Terms &amp; Conditions</strong>
            <a class="footer-link-clean" href="<?= e(route('terms')) ?>">View Terms</a>
        </div>
        <div class="footer-col-clean">
            <strong>Contact Us</strong>
            <a class="footer-link-clean" href="mailto:support@hassad.sa">support@hassad.sa</a>
        </div>
        <div class="footer-col-clean">
            <strong>Social Media</strong>
            <div class="footer-social" aria-label="Social links">
                <a class="footer-social-link" href="https://www.linkedin.com" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">in</a>
                <a class="footer-social-link" href="https://x.com" target="_blank" rel="noopener noreferrer" aria-label="X">X</a>
                <a class="footer-social-link" href="https://www.instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram">IG</a>
            </div>
        </div>
    </footer>
    <?php
}

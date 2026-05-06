<?php
/**
 * ملف تهيئة التطبيق (Bootstrap)
 * ================================
 * يقوم بتحميل جميع الملفات والإعدادات الأساسية للتطبيق
 * يبدأ الجلسة ويحمل ملفات الإعدادات والدوال
 * 
 * @description يُستدعى في بداية كل طلب HTTP لتهيئة البيئة
 */

declare(strict_types=1);

// بدء الجلسة إذا لم تكن مفعّلة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل إعدادات قاعدة البيانات
require_once __DIR__ . '/../config/database.php';

// تحميل الدوال المساعدة العامة
require_once __DIR__ . '/functions.php';

// تحميل طبقة البيانات (Repository)
require_once __DIR__ . '/repository.php';

// تنفيذ ترحيلات قاعدة البيانات وقت التشغيل (عند توفر اتصال MySQL)
if (function_exists('ensure_runtime_migrations')) {
    ensure_runtime_migrations();
}

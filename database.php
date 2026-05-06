<?php
/**
 * ملف إعدادات قاعدة البيانات
 * ================================
 * يحتوي على دوال الاتصال بقاعدة البيانات
 * والتحقق من وضع العرض التجريبي
 */

declare(strict_types=1);

/**
 * دالة جلب إعدادات التطبيق
 * --------------------------------
 * تقوم بتحميل وتخزين إعدادات التطبيق
 * 
 * @return array مصفوفة الإعدادات
 */
function app_config(): array
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/app.php';
    }
    return $config;
}

/**
 * دالة الاتصال بقاعدة البيانات
 * --------------------------------
 * تُنشئ اتصال PDO بقاعدة البيانات
 * تدعم MySQL و SQLite
 * 
 * @return PDO|null كائن الاتصال أو null إذا فشل
 */
function db_connection(): ?PDO
{
    static $pdo = false;

    if ($pdo !== false) {
        return $pdo;
    }

    $config = app_config()['db'] ?? [];
    $driver = strtolower((string) ($config['driver'] ?? 'mysql'));

    try {
        if ($driver === 'sqlite') {
            if (!extension_loaded('pdo_sqlite')) {
                $pdo = null;
                return $pdo;
            }

            $sqlitePath = (string) ($config['sqlite_path'] ?? (__DIR__ . '/../database/hassad.sqlite'));
            $dsn = 'sqlite:' . $sqlitePath;
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $pdo;
        }

        if (!extension_loaded('pdo_mysql')) {
            $pdo = null;
            return $pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            $config['database'] ?? 'hassad',
            $config['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO(
            $dsn,
            $config['username'] ?? 'root',
            $config['password'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable $exception) {
        $pdo = null;
    }

    return $pdo;
}



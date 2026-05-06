<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$page = $_GET['page'] ?? 'home';
$allowedPages = [
    // الصفحات الأساسية - Basic Pages
    'home',
    'register-student',
    'register-manager',
    'account-verified',
    'operation-result',
    'login',
    'terms',
    
    // صفحات الطالب - Student Pages
    'student-dashboard',
    'fund',
    'subscribe',
    'wallet',
    'withdraw',
    'search-funds',
    'self-ban',
    'student-settings',
    'contracts',
    
    // صفحات المدير - Manager Pages
    'manager-dashboard',
    'create-fund',
    'publish-fund',
    'edit-fund',
    'verify-fund-operation',
    
    // صفحات التقارير - Report Pages
    'reports',
    'manager-reports',
    'investment-report',
    
    // صفحات الإدارة - Admin Pages
    'admin-dashboard',
    'system-settings',
    'send-notification',
    
    // صفحات JIC - JIC Pages
    'jic-participants',
    
    // صفحات مشتركة - Common Pages
    'edit-profile',
    'bank-cards',
];

if (!in_array($page, $allowedPages, true)) {
    $page = 'home';
}

$role = $_SESSION['auth']['role'] ?? '';
if ($role === 'manager') {
    $managerBlockedPages = [
        'investment-report',
    ];

    if (in_array($page, $managerBlockedPages, true)) {
        set_flash('error', 'This page is not available for manager accounts.');
        redirect_to('manager-dashboard');
    }
}

require __DIR__ . '/pages/' . $page . '.php';

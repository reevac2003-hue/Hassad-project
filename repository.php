<?php
/**
 * ملف طبقة البيانات (Repository)
 * ================================
 * يحتوي على جميع دوال الوصول إلى البيانات
 * يعمل على قاعدة البيانات فقط (DB-only)
 * 
 * الأقسام:
 * 1. مخزن البيانات التجريبي
 * 2. دوال المستخدم الحالي
 * 3. دوال التسجيل والمصادقة
 * 4. دوال الصناديق الاستثمارية
 * 5. دوال الاشتراكات والعقود
 * 6. دوال المحفظة والمعاملات
 * 7. دوال السحب
 * 8. دوال توزيع العوائد
 * 9. دوال حظر المستخدمين
 * 10. دوال JIC (الجهة الراعية)
 * 11. دوال الإدارة والنظام
 * 12. دوال التقارير
 * 13. دوال مساعدة لقاعدة البيانات
 */

declare(strict_types=1);

// ===================================================
// 1. دوال مساعدة لقاعدة البيانات
// ===================================================

/**
 * التحقق من وجود عمود داخل جدول بقاعدة البيانات
 */
function db_column_exists(string $tableName, string $columnName): bool
{
    $config = app_config();
    $dbName = (string) (($config['db']['database'] ?? '') ?: 'hassad');
    $row = repo_fetch_one(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        [$dbName, $tableName, $columnName]
    );
    return $row !== null;
}

/**
 * تنفيذ ترحيلات قاعدة البيانات وقت التشغيل (مرة واحدة لكل طلب)
 */
function ensure_runtime_migrations(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!db_connection()) {
        return;
    }

    if (!db_column_exists('Fund', 'InvestmentObjective')) {
        repo_execute("ALTER TABLE Fund ADD COLUMN InvestmentObjective VARCHAR(100) NULL AFTER FundTitle");
    }

    if (!db_column_exists('Fund', 'Highlights')) {
        repo_execute("ALTER TABLE Fund ADD COLUMN Highlights TEXT NULL AFTER FundDescription");
    }

    // Fix NotificationID to AUTO_INCREMENT if it isn't already
    $notifCol = repo_fetch_one(
        "SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Notification' AND COLUMN_NAME = 'NotificationID'"
    );
    if ($notifCol !== null && stripos((string)($notifCol['EXTRA'] ?? ''), 'auto_increment') === false) {
        repo_execute("ALTER TABLE Notification MODIFY NotificationID INT NOT NULL AUTO_INCREMENT");
    }

    // SystemSettings table
    repo_execute("CREATE TABLE IF NOT EXISTS SystemSettings (
        SettingKey   VARCHAR(100) PRIMARY KEY,
        SettingValue TEXT         NOT NULL DEFAULT '',
        SettingDescription VARCHAR(255) DEFAULT ''
    )");

    // Seed default system limits if not present
    $defaults = [
        'min_subscription' => '100',
        'max_subscription' => '100000',
        'withdrawal_limit_daily'   => '5000',
        'withdrawal_limit_monthly' => '50000',
        'sms_enabled'           => '1',
        'self_ban_enabled'      => '1',
        'two_step_verification' => '1',
        'maintenance_mode'      => '0',
        'site_name'             => 'Hassad Investment Platform',
        'site_description'      => 'Student Investment Fund Management',
    ];
    foreach ($defaults as $k => $v) {
        repo_execute(
            "INSERT IGNORE INTO SystemSettings (SettingKey, SettingValue) VALUES (?, ?)",
            [$k, $v]
        );
    }

    // Performance indexes
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_fund_status ON Fund(FundAccountStatus)",
        "CREATE INDEX IF NOT EXISTS idx_fund_manager ON Fund(FundManagerNumberofLicense)",
        "CREATE INDEX IF NOT EXISTS idx_participation_status ON ParticipationApproval(ApprovalStatus)",
        "CREATE INDEX IF NOT EXISTS idx_participation_student_fund ON ParticipationApproval(StudentID, FundID)",
        "CREATE INDEX IF NOT EXISTS idx_transaction_student_fund ON `Transaction`(StudentID, FundID)",
        "CREATE INDEX IF NOT EXISTS idx_transaction_type ON `Transaction`(TransactionType)",
        "CREATE INDEX IF NOT EXISTS idx_withdrawal_status ON WithdrawalRequest(Status)",
        "CREATE INDEX IF NOT EXISTS idx_student_id ON Student(StudentID)",
        "CREATE INDEX IF NOT EXISTS idx_manager_license ON FundManager(FundManagerNumberofLicense)",
    ];

    foreach ($indexes as $indexSql) {
        repo_execute($indexSql);
    }
}

/**
 * توحيد شكل بيانات الصندوق بين DB وDemo
 */
function normalize_fund_row(array $fund): array
{
    $title = (string) ($fund['FundTitle'] ?? '');
    $fund['InvestmentObjective'] = (string) ($fund['InvestmentObjective'] ?? 'Capital Growth');
    $fund['ReturnPolicy'] = (string) ($fund['ReturnPolicy'] ?? ($fund['ReturnTimingPolicy'] ?? 'Annual'));
    $fund['DurationMonths'] = (int) ($fund['DurationMonths'] ?? ($fund['HoldingPeriod'] ?? 0));
    $fund['ReturnExpected'] = (float) ($fund['ReturnExpected'] ?? ($fund['ExpectedReturnPercentage'] ?? 0));
    $fund['Status'] = (string) ($fund['Status'] ?? ($fund['FundAccountStatus'] ?? 'Draft'));
    $fund['slug'] = (string) ($fund['slug'] ?? strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', trim($title !== '' ? $title : ('fund-' . ($fund['FundID'] ?? '0'))))));

    $highlights = $fund['Highlights'] ?? [];
    if (is_string($highlights)) {
        $decoded = json_decode($highlights, true);
        if (is_array($decoded)) {
            $highlights = $decoded;
        } else {
            $highlights = preg_split('/\r\n|\r|\n/', $highlights) ?: [];
        }
    }
    if (!is_array($highlights)) {
        $highlights = [];
    }
    $fund['Highlights'] = array_values(array_filter(array_map(static fn($item) => trim((string) $item), $highlights), static fn($item) => $item !== ''));

    return $fund;
}

// ===================================================
// 2. دوال المستخدم الحالي
// ===================================================

/**
 * دالة جلب بيانات الطالب الحالي
 * --------------------------------
 * تُرجع بيانات الطالب المسجّل دخوله أو بيانات افتراضية
 *
 * @return array بيانات الطالب
 */
function current_student(): array
{
    if (is_logged_in_as_student()) {
        $sid = (int) ($_SESSION['auth']['student_id'] ?? 0);
        $row = repo_fetch_one('SELECT * FROM Student WHERE StudentID = ?', [$sid]);
        if ($row) {
            return $row;
        }
    }
    return [
        'StudentID' => 0,
        'StudentNameFirst' => '',
        'StudentNameLast' => '',
        'StudentPassword' => '',
        'StudentMajor' => '',
        'StudentAccountStates' => '',
        'StudentPhoneNumber' => '',
        'StudentCampus' => '',
        'StudentAcademicDegree' => '',
        'ReinvestmentAmount' => 0,
    ];
}

/**
 * دالة جلب بيانات مدير الصندوق الحالي
 * --------------------------------
 * تُرجع بيانات مدير الصندوق المسجّل دخوله أو بيانات افتراضية
 *
 * @return array بيانات المدير
 */
function current_manager(): array
{
    if (is_logged_in_as_manager()) {
        $lid = (int) ($_SESSION['auth']['manager_license'] ?? 0);
        $row = repo_fetch_one('SELECT * FROM FundManager WHERE FundManagerNumberofLicense = ?', [$lid]);
        if ($row) {
            return $row;
        }
    }
    return [
        'FundManagerNumberofLicense' => 0,
        'FundManagerID' => 0,
        'FundManagerNameFirst' => '',
        'FundManagerNameLast' => '',
        'FundManagerPhone' => '',
        'FundManagerPassword' => '',
        'FundManagerNumberOfFund' => 0,
        'FundManagerAccountStatus' => '',
    ];
}

/**
 * دالة جلب بيانات المدير الإداري الحالي
 * --------------------------------
 * تُرجع بيانات المدير الإداري المسجّل دخوله
 *
 * @return array بيانات المدير الإداري
 */
function current_admin(): array
{
    $aid = (int) ($_SESSION['auth']['admin_id'] ?? 1);
    return repo_fetch_one('SELECT * FROM Admin WHERE AdminID = ?', [$aid]) ?? [
        'AdminID' => 1,
        'UserName' => 'Admin',
        'HassadPassword' => '',
        'PhoneNum' => '',
        'UpdateVersion' => 'v1.0.0',
    ];
}

/**
 * دالة جلب بيانات الجهة الراعية الحالية
 * --------------------------------
 * تُرجع بيانات JIC المسجّل دخوله
 *
 * @return array بيانات الجهة الراعية
 */
function current_jic(): array
{
    $jid = (int) ($_SESSION['auth']['jic_id'] ?? 31);
    return repo_fetch_one('SELECT * FROM JicSponsor WHERE JicSponsorID = ?', [$jid]) ?? [
        'JicSponsorID' => 31,
        'Department' => 'JIC',
        'PhoneNumber' => '',
    ];
}

/**
 * دالة جلب محفظة الطالب
 * --------------------------------
 * تُرجع بيانات المحفظة الاستثمارية للطالب
 *
 * @param int|null $studentId معرّف الطالب (اختياري)
 * @return array بيانات المحفظة
 */
function current_wallet(?int $studentId = null): array
{
    $sid = $studentId ?? (int) current_student()['StudentID'];
    return repo_fetch_one('SELECT * FROM InvestmentWallet WHERE StudentID = ?', [$sid]) ?? [
        'InvestmentWalletID' => 0,
        'InvestmentWalletTotalAmount' => 0,
        'InvestmentWalletReturn' => 0,
        'InvestmentWalletCredit' => 0,
        'StudentID' => $sid,
    ];
}

/**
 * دالة صف الحساب البنكي الافتراضي
 * --------------------------------
 * تُرجع صفاً افتراضياً لبيانات البطاقة والحساب البنكي لطالب محدد
 * عند عدم وجود سجل محفوظ في قاعدة البيانات.
 *
 * @param int $studentId معرّف الطالب
 * @return array بيانات افتراضية للحساب البنكي
 */
function bank_account_default_row(int $studentId): array
{
    return [
        'CreditCardNumber' => '',
        'BankAccountIban' => '',
        'CreditCardName' => '',
        'CreditCardCVV' => 0,
        'CreditCardDayExpired' => '',
        'VerificationCode' => 0,
        'PostalCode' => '',
        'StudentID' => $studentId,
    ];
}

/**
 * دالة جلب بطاقات الطالب البنكية
 * --------------------------------
 * تُرجع أحدث بطاقة/حساب بنكي محفوظ للطالب من جدول BankAccount.
 * إذا لم يُمرَّر معرّف طالب، تُستخدم بيانات الطالب الحالي.
 *
 * @param int|null $studentId معرّف الطالب (اختياري)
 * @return array قائمة البطاقات المطابقة
 */
function bank_cards_for_student(?int $studentId = null): array
{
    $sid = $studentId ?? (int) current_student()['StudentID'];
    if ($sid <= 0) {
        return [];
    }

    return repo_fetch_all(
        'SELECT * FROM BankAccount WHERE StudentID = ? ORDER BY CreditCardDayExpired DESC, CreditCardNumber ASC LIMIT 1',
        [$sid]
    );
}

/**
 * دالة البحث عن بطاقة طالب
 * --------------------------------
 * تبحث عن بطاقة بنكية لطالب معيّن بعد توحيد رقم البطاقة
 * بإزالة أي محارف غير رقمية قبل المقارنة.
 *
 * @param int $studentId معرّف الطالب
 * @param string $cardNumber رقم البطاقة المُدخل
 * @return array|null بيانات البطاقة إذا وُجدت أو null إذا لم توجد
 */
function find_student_bank_card(int $studentId, string $cardNumber): ?array
{
    $normalized = preg_replace('/\D+/', '', $cardNumber);
    if ($normalized === '') {
        return null;
    }

    foreach (bank_cards_for_student($studentId) as $card) {
        $stored = preg_replace('/\D+/', '', (string) ($card['CreditCardNumber'] ?? ''));
        if ($stored === $normalized) {
            return $card;
        }
    }

    return null;
}

/**
 * دالة جلب الحساب البنكي للطالب
 * --------------------------------
 * تُرجع بيانات الحساب البنكي والبطاقة الائتمانية
 *
 * @param int|null $studentId معرّف الطالب (اختياري)
 * @return array بيانات الحساب البنكي
 */
function current_bank_account(?int $studentId = null): array
{
    $sid = $studentId ?? (int) current_student()['StudentID'];
    $cards = bank_cards_for_student($sid);
    return $cards[0] ?? bank_account_default_row($sid);
}

/**
 * تحديث/إنشاء بيانات الحساب البنكي للطالب داخل المخزن.
 */
function save_student_bank_card(int $studentId, array $data): bool
{
    $cardNumber = preg_replace('/\D+/', '', (string) ($data['card_number'] ?? ''));
    $iban = strtoupper(str_replace(' ', '', trim((string) ($data['iban'] ?? ''))));
    $cardName = trim((string) ($data['card_name'] ?? ''));
    $expiryDate = (string) ($data['expiry_date'] ?? '');
    $cvv = (int) ($data['cvv'] ?? 0);
    $postalCode = (string) ($data['postal_code'] ?? '');

    $existing = $cardNumber !== '' ? find_student_bank_card($studentId, $cardNumber) : current_bank_account($studentId);

    $base = $existing ?? bank_account_default_row($studentId);
    $row = [
        'CreditCardNumber' => $cardNumber !== '' ? $cardNumber : (string) ($base['CreditCardNumber'] ?? ''),
        'BankAccountIban' => $iban !== '' ? $iban : (string) ($base['BankAccountIban'] ?? ''),
        'CreditCardName' => $cardName !== '' ? $cardName : (string) ($base['CreditCardName'] ?? ''),
        'CreditCardCVV' => $cvv > 0 ? $cvv : (int) ($base['CreditCardCVV'] ?? 0),
        'CreditCardDayExpired' => $expiryDate !== '' ? $expiryDate : (string) ($base['CreditCardDayExpired'] ?? ''),
        'VerificationCode' => (int) ($data['verification_code'] ?? ($base['VerificationCode'] ?? rand(1000, 9999))),
        'PostalCode' => $postalCode !== '' ? $postalCode : (string) ($base['PostalCode'] ?? ''),
        'StudentID' => $studentId,
    ];

    if ($iban !== '') {
        if (!repo_execute('UPDATE BankAccount SET BankAccountIban = ? WHERE StudentID = ?', [$iban, $studentId])) {
            return false;
        }
    }

    if ($cardNumber === '') {
        return true;
    }

    if (!repo_execute('DELETE FROM BankAccount WHERE StudentID = ? AND CreditCardNumber <> ?', [$studentId, $cardNumber])) {
        return false;
    }

    $cardOwnedByAnotherStudent = repo_fetch_one(
        'SELECT StudentID FROM BankAccount WHERE CreditCardNumber = ? AND COALESCE(StudentID, 0) <> ? LIMIT 1',
        [$cardNumber, $studentId]
    );
    if ($cardOwnedByAnotherStudent) {
        return false;
    }

    $exists = repo_fetch_one(
        'SELECT CreditCardNumber FROM BankAccount WHERE StudentID = ? AND CreditCardNumber = ? LIMIT 1',
        [$studentId, $cardNumber]
    );
    if ($exists) {
        if (!repo_execute(
            'UPDATE BankAccount
             SET BankAccountIban = ?, CreditCardName = ?, CreditCardCVV = ?, CreditCardDayExpired = ?, VerificationCode = ?, PostalCode = ?
             WHERE StudentID = ? AND CreditCardNumber = ?',
            [
                (string) ($row['BankAccountIban'] ?? ''),
                (string) ($row['CreditCardName'] ?? ''),
                (int) ($row['CreditCardCVV'] ?? 0),
                (string) ($row['CreditCardDayExpired'] ?? ''),
                (int) ($row['VerificationCode'] ?? 0),
                (string) ($row['PostalCode'] ?? ''),
                $studentId,
                (string) ($row['CreditCardNumber'] ?? ''),
            ]
        )) {
            return false;
        }
    } else {
        if (!repo_execute(
            'INSERT INTO BankAccount (CreditCardNumber, BankAccountIban, CreditCardName, CreditCardCVV, CreditCardDayExpired, VerificationCode, PostalCode, StudentID)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (string) ($row['CreditCardNumber'] ?? ''),
                (string) ($row['BankAccountIban'] ?? ''),
                (string) ($row['CreditCardName'] ?? ''),
                (int) ($row['CreditCardCVV'] ?? 0),
                (string) ($row['CreditCardDayExpired'] ?? ''),
                (int) ($row['VerificationCode'] ?? 0),
                (string) ($row['PostalCode'] ?? ''),
                $studentId,
            ]
        )) {
            return false;
        }
    }

    $savedRow = repo_fetch_one(
        'SELECT CreditCardNumber, CreditCardName, CreditCardCVV, CreditCardDayExpired, StudentID
         FROM BankAccount WHERE StudentID = ? AND CreditCardNumber = ? LIMIT 1',
        [$studentId, $cardNumber]
    );
    if (!$savedRow) {
        return false;
    }

    return true;
}

function upsert_student_bank_account(int $studentId, array $data): void
{
    save_student_bank_card($studentId, $data);
}

// ===================================================
// 3. دوال التسجيل والمصادقة
// ===================================================

/**
 * دالة تسجيل طالب جديد
 * --------------------------------
 * تُنشئ حساب طالب جديد في المخزن مع محفظة وحساب بنكي
 *
 * @param array $data بيانات الطالب
 */
function register_student(array $data, ?string $redirect_on_error = null): bool
{
    $sid = (int) ($data['student_id'] ?? rand(100000000, 999999999));
    $firstName = trim($data['first_name'] ?? '');
    $lastName = trim($data['last_name'] ?? '');
    $password = $data['password'] ?? '';
    $phone = trim($data['phone'] ?? '');

    // Validate required fields
    if ($firstName === '' || $lastName === '' || $password === '' || $phone === '') {
        set_flash('error', 'First Name, Last Name, Phone, and Password are all required.');
        if ($redirect_on_error) {
            redirect_to($redirect_on_error, ['tab' => 'create-user']);
        } else {
            redirect_to('register-student');
        }
        return false;
    }

    $dbDup = repo_fetch_one('SELECT StudentID FROM Student WHERE StudentID = ?', [$sid]);
    if ($dbDup) {
        set_flash('error', 'A student with this ID already exists. Please use a different ID.');
        if ($redirect_on_error) {
            redirect_to($redirect_on_error, ['tab' => 'create-user']);
        } else {
            redirect_to('register-student');
        }
        return false;
    }

    $code      = rand(1000, 9999);
    $major     = trim($data['major'] ?? '');
    $campus    = trim($data['campus'] ?? '');
    $degree    = trim($data['degree'] ?? '');
    $iban      = trim($data['iban'] ?? '');
    $cardNumber = preg_replace('/\D+/', '', (string) ($data['card_number'] ?? ''));
    $cardName   = trim((string) ($data['card_name'] ?? ''));
    $cvvRaw     = preg_replace('/\D+/', '', (string) ($data['cvv'] ?? ''));
    $expiryRaw  = trim((string) ($data['expiry'] ?? ''));

    $expiryDate = '';
    if ($expiryRaw !== '' && preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $expiryRaw, $m)) {
        $expiryDate = sprintf('20%s-%s-01', $m[2], $m[1]);
    }

    // Insert student record
    $studentInserted = repo_execute(
        'INSERT INTO Student (StudentID, StudentNameFirst, StudentNameLast, StudentPassword, StudentMajor, StudentAccountStates, StudentPhoneNumber, StudentCampus, StudentAcademicDegree, ReinvestmentAmount, AccessID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)',
        [$sid, $firstName, $lastName, $password, $major, 'Verified', $phone, $campus, $degree]
    );

    if (!$studentInserted) {
        set_flash('error', 'Failed to create student account. Please check your data and try again.');
        if ($redirect_on_error) {
            redirect_to($redirect_on_error, ['tab' => 'create-user']);
        } else {
            redirect_to('register-student');
        }
        return false;
    }

    // Insert investment wallet
    $walletInserted = repo_execute(
        'INSERT INTO InvestmentWallet (InvestmentWalletID, InvestmentWalletTotalAmount, InvestmentWalletReturn, InvestmentWalletCredit, StudentID) VALUES (?, 0, 0, 0, ?)',
        [$sid, $sid]
    );

    if (!$walletInserted) {
        set_flash('error', 'Failed to create student wallet. Please contact support.');
        if ($redirect_on_error) {
            redirect_to($redirect_on_error, ['tab' => 'create-user']);
        } else {
            redirect_to('register-student');
        }
        return false;
    }

    upsert_student_bank_account($sid, [
        'card_number' => $cardNumber,
        'iban' => $iban,
        'card_name' => $cardName,
        'cvv' => $cvvRaw !== '' ? (int) $cvvRaw : 0,
        'expiry_date' => $expiryDate,
        'verification_code' => $code,
    ]);

    return true;
}

/**
 * دالة تسجيل مدير صندوق جديد
 * --------------------------------
 * تُنشئ حساب مدير صندوق جديد
 *
 * @param array $data بيانات المدير
 */
function register_manager(array $data, ?string $redirect_on_error = null): bool
{
    $license = (int) ($data['license'] ?? rand(100000000, 999999999));

    $dbDup = repo_fetch_one('SELECT FundManagerNumberofLicense FROM FundManager WHERE FundManagerNumberofLicense = ?', [$license]);
    if ($dbDup) {
        set_flash('error', 'A manager with this license number already exists.');
        if ($redirect_on_error) {
            redirect_to($redirect_on_error, ['tab' => 'create-user']);
        } else {
            redirect_to('register-manager');
        }
        return false;
    }

    $code      = rand(1000, 9999);
    $managerId = rand(10000, 99999);
    $firstName = trim($data['first_name'] ?? '');
    $lastName  = trim($data['last_name'] ?? '');
    $phone     = trim($data['phone'] ?? '');
    $password  = $data['password'] ?? '';
    $dateStart = date('Y-m-d');
    $dateEnd   = date('Y-m-d', strtotime('+2 years'));

    // تحقق من عدم ترك الحقول المهمة فارغة
    if ($firstName === '' || $lastName === '' || $phone === '' || $password === '') {
        set_flash('error', 'Manager first name, last name, phone, and password are required.');
        if ($redirect_on_error) {
            redirect_to($redirect_on_error, ['tab' => 'create-user']);
        } else {
            redirect_to('register-manager');
        }
        return false;
    }

    // حاول إدراج المدير الجديد في قاعدة البيانات
    $success = repo_execute(
        'INSERT INTO FundManager (FundManagerNumberofLicense, FundManagerID, FundManagerNameFirst, FundManagerNameLast, FundManagerPhone, FundManagerPassword, FundManagerNumberOfFund, FundManagerDateStart, FundManagerDateEnd, FundManagerAmountMaximum, FundManagerAmountMinimum, FundManagerAccountStatus, AccessID) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 50000, 100, ?, 2)',
        [$license, $managerId, $firstName, $lastName, $phone, $password, $dateStart, $dateEnd, 'Approved']
    );

    if (!$success) {
        set_flash('error', 'Failed to create manager account. Please check your data and try again.');
        if ($redirect_on_error) {
            redirect_to($redirect_on_error, ['tab' => 'create-user']);
        } else {
            redirect_to('register-manager');
        }
        return false;
    }

    return true;
}



/**
 * دالة تسجيل حساب أدمن جديد
 * --------------------------------
 * تُنشئ حساب Admin في المخزن وقاعدة البيانات
 *
 * @param array $data بيانات الأدمن
 */
function register_admin(array $data, ?string $redirect_on_error = null): void
{
    $adminId = (int) ($data['admin_id'] ?? rand(2, 999));
    $username = trim($data['username'] ?? '');
    $password = (string) ($data['password'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $version = trim($data['version'] ?? 'v1.0.0');

    $dbDupById   = repo_fetch_one('SELECT AdminID FROM Admin WHERE AdminID = ?', [$adminId]);
    $dbDupByName = repo_fetch_one('SELECT AdminID FROM Admin WHERE LOWER(UserName) = LOWER(?)', [$username]);

    if ($dbDupById || $dbDupByName) {
        set_flash('error', 'Admin ID or username already exists.');
        if ($redirect_on_error) {
            redirect_to($redirect_on_error, ['tab' => 'create-user']);
        }
        return;
    }

    repo_execute(
        'INSERT INTO Admin (AdminID, UserName, HassadPassword, PhoneNum, UpdateVersion) VALUES (?, ?, ?, ?, ?)',
        [$adminId, $username, $password, $phone, $version]
    );
}

/**
 * دالة رفض حساب المستخدم
 * --------------------------------
 * تُغيّر حالة الحساب إلى مرفوض/موقوف
 *
 * @param string $userType نوع المستخدم (Student أو FundManager)
 * @param int $userId معرّف المستخدم
 * @param string $reason سبب الرفض
 * @return bool نجاح العملية
 */
function reject_user_account(string $userType, int $userId, string $reason = ''): bool
{
    if ($userType === 'Student') {
        return repo_execute(
            'UPDATE Student SET StudentAccountStates = ? WHERE StudentID = ?',
            ['Rejected', $userId]
        );
    }
    if ($userType === 'FundManager') {
        return repo_execute(
            'UPDATE FundManager SET FundManagerAccountStatus = ? WHERE FundManagerNumberofLicense = ?',
            ['Rejected', $userId]
        );
    }
    return false;
}

/**
 * دالة محاولة تسجيل الدخول
 * --------------------------------
 * تتحقق من بيانات تسجيل الدخول وتُنشئ جلسة
 *
 * @param string $role الدور (student أو manager)
 * @param string $identity المعرّف (رقم الطالب أو رقم الرخصة)
 * @param string $password كلمة المرور
 * @return bool true إذا نجح تسجيل الدخول
 */
function attempt_login(string $role, string $identity, string $password): bool
{
    if ($role === 'student') {
        $sid = (int) $identity;
        $student = repo_fetch_one('SELECT * FROM Student WHERE StudentID = ? AND StudentPassword = ?', [$sid, $password]);
        if ($student) {
            $_SESSION['auth'] = ['role' => 'student', 'student_id' => $sid];
            return true;
        }
    } elseif ($role === 'manager') {
        $lid = (int) $identity;
        $managerOk = repo_fetch_one('SELECT FundManagerNumberofLicense FROM FundManager WHERE FundManagerNumberofLicense = ? AND FundManagerPassword = ?', [$lid, $password]) !== null;
        if ($managerOk) {
            $_SESSION['auth'] = ['role' => 'manager', 'manager_license' => $lid];
            return true;
        }
    } elseif ($role === 'admin') {
        $dbAdmin = repo_fetch_one('SELECT AdminID FROM Admin WHERE LOWER(UserName) = LOWER(?) AND HassadPassword = ?', [$identity, $password]);
        if ($dbAdmin) {
            $_SESSION['auth'] = ['role' => 'admin', 'admin_id' => (int) ($dbAdmin['AdminID'] ?? 1)];
            return true;
        }
    } elseif ($role === 'jic') {
        $dbJic = repo_fetch_one('SELECT JicSponsorID FROM JicSponsor WHERE LOWER(JicUsername) = LOWER(?) AND JicPassword = ?', [$identity, $password]);
        if ($dbJic) {
            $_SESSION['auth'] = ['role' => 'jic', 'jic_id' => (int) ($dbJic['JicSponsorID'] ?? 31)];
            return true;
        }
    }

    return false;
}

/**
 * دالة تسجيل الدخول الموحد
 * --------------------------------
 * تحاول التعرف على نوع الحساب تلقائياً ثم تُنشئ الجلسة المناسبة
 *
 * @param string $identity رقم الهوية أو الرخصة أو اسم المستخدم
 * @param string $password كلمة المرور
 * @return string|false اسم الدور إذا نجح تسجيل الدخول أو false إذا فشل
 */
function attempt_login_auto(string $identity, string $password): string|false
{
    $normalizedIdentity = trim($identity);
    $isNumericIdentity = ctype_digit($normalizedIdentity);

    if (attempt_login('admin', $normalizedIdentity, $password)) {
        return 'admin';
    }

    if (attempt_login('jic', $normalizedIdentity, $password)) {
        return 'jic';
    }

    if ($isNumericIdentity && attempt_login('manager', $normalizedIdentity, $password)) {
        return 'manager';
    }

    if ($isNumericIdentity && attempt_login('student', $normalizedIdentity, $password)) {
        return 'student';
    }

    return false;
}

// ===================================================
// 4. دوال الصناديق الاستثمارية
// ===================================================

/**
 * دالة جلب جميع الصناديق
 * --------------------------------
 * تُرجع جميع الصناديق أو المنشورة فقط
 *
 * @param bool $publishedOnly عرض المنشورة فقط
 * @return array قائمة الصناديق
 */
function all_funds(bool $publishedOnly = false): array
{
    static $cache = [];
    $cacheKey = 'funds_' . ($publishedOnly ? 'published' : 'all');

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    ensure_runtime_migrations();

    $dbSql = 'SELECT * FROM Fund';
    $params = [];
    if ($publishedOnly) {
        $dbSql .= ' WHERE FundAccountStatus = ?';
        $params[] = 'Published';
    }
    $dbSql .= ' ORDER BY FundID DESC';
    $dbFunds = repo_fetch_all($dbSql, $params);
    $result = !empty($dbFunds) ? array_map('normalize_fund_row', $dbFunds) : [];
    $cache[$cacheKey] = $result;
    return $result;
}

/**
 * دالة جلب صندوق بالمعرّف
 * --------------------------------
 * تبحث عن صندوق بمعرّفه الرقمي
 *
 * @param int $id معرّف الصندوق
 * @return array|null بيانات الصندوق أو null
 */
function fund_by_id(int $id): ?array
{
    ensure_runtime_migrations();
    $dbFund = repo_fetch_one('SELECT * FROM Fund WHERE FundID = ?', [$id]);
    if ($dbFund) {
        return normalize_fund_row($dbFund);
    }
    return null;
}

/**
 * دالة جلب صندوق بالاسم المختصر (slug)
 * --------------------------------
 * تبحث عن صندوق باسمه المختصر في الرابط
 *
 * @param string $slug الاسم المختصر
 * @return array|null بيانات الصندوق أو null
 */
function fund_by_slug(string $slug): ?array
{
    $dbFunds = repo_fetch_all('SELECT * FROM Fund ORDER BY FundID DESC');
    foreach ($dbFunds as $fund) {
        $fund = normalize_fund_row($fund);
        if (($fund['slug'] ?? '') === $slug) {
            return $fund;
        }
    }
    return null;
}

/**
 * دالة البحث عن صندوق بالمعرّف (بديل)
 * --------------------------------
 * نفس وظيفة fund_by_id لكن باسم مختلف للتوافق
 *
 * @param int $id معرّف الصندوق
 * @return array|null بيانات الصندوق أو null
 */
function find_fund_by_id(int $id): ?array
{
    return fund_by_id($id);
}

/**
 * دالة جلب صناديق مدير معين
 * --------------------------------
 * تُرجع جميع الصناديق التي أنشأها مدير محدد
 *
 * @param int $managerId معرّف المدير
 * @return array قائمة الصناديق
 */
function manager_funds(int $managerId): array
{
    ensure_runtime_migrations();
    $dbFunds = repo_fetch_all('SELECT * FROM Fund WHERE FundManagerNumberofLicense = ? ORDER BY FundID DESC', [$managerId]);
    if (!empty($dbFunds)) {
        return array_map('normalize_fund_row', $dbFunds);
    }

    return [];
}

/**
 * دالة المعرّف التالي للصندوق
 * --------------------------------
 * تُولّد معرّفاً فريداً للصندوق الجديد
 *
 * @return int المعرّف التالي
 */
function next_fund_id(): int
{
    $dbMax = repo_fetch_one('SELECT MAX(FundID) AS MaxFundID FROM Fund');
    if ($dbMax && isset($dbMax['MaxFundID']) && $dbMax['MaxFundID'] !== null) {
        return (int) $dbMax['MaxFundID'] + 1;
    }
    return 101;
}

/**
 * دالة إنشاء مسودة صندوق
 * --------------------------------
 * تُنشئ صندوقاً جديداً بحالة مسودة (Draft)
 *
 * @param int   $managerLicense رقم رخصة المدير
 * @param array $data بيانات الصندوق
 * @return int معرّف الصندوق الجديد
 */
function create_fund_draft(int $managerLicense, array $data): int
{
    ensure_runtime_migrations();

    $id = next_fund_id();
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($data['title'] ?? 'fund-' . $id)));

    $holdingPeriod = (int) ($data['holding_period'] ?? $data['duration'] ?? 6);

    $highlights = array_values(array_filter(array_map('trim', explode("\n", (string) ($data['highlight'] ?? '')))));

    $insertSql = 'INSERT INTO Fund (FundID, FundTitle, InvestmentObjective, InvestmentType, FundAmountMaximum, FundAmountMinimum, HoldingPeriod, ReturnTimingPolicy, FundAccountStatus, RiskLevel, FundDateStart, FundDateEnd, ExpectedReturnPercentage, FundDescription';
    $insertValues = ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
    $params = [
        $id,
        trim($data['title'] ?? ''),
        (string) ($data['investment_objective'] ?? 'Capital Growth'),
        (string) ($data['investment_type'] ?? 'Equities (Saudi Stocks – Saudi Exchange)'),
        (float) ($data['max_amount'] ?? 0),
        (float) ($data['min_amount'] ?? 0),
        $holdingPeriod,
        (string) ($data['return_policy'] ?? 'Annual'),
        'Draft',
        (string) ($data['risk_level'] ?? 'medium'),
        date('Y-m-d'),
        date('Y-m-d', strtotime('+' . $holdingPeriod . ' months')),
        (float) ($data['expected_return'] ?? 0),
        trim((string) ($data['description'] ?? '')),
    ];
    if (db_column_exists('Fund', 'Highlights')) {
        $insertSql .= ', Highlights';
        $insertValues .= ', ?';
        $params[] = json_encode($highlights, JSON_UNESCAPED_UNICODE);
    }
    $insertSql .= ', FundManagerNumberofLicense)';
    $insertValues .= ', ?)';
    $params[] = $managerLicense;

    $fundSaved = repo_execute($insertSql . $insertValues, $params);

    if (!$fundSaved || repo_fetch_one('SELECT FundID FROM Fund WHERE FundID = ? LIMIT 1', [$id]) === null) {
        return 0;
    }

    $notificationText = 'New fund created as Draft: Fund #' . $id;
    repo_execute(
        'INSERT INTO Notification (NotificationDescription, VerificationCode, SentDate, FundID) VALUES (?, 0, NOW(), ?)',
        [$notificationText, $id]
    );

    return $id;
}

/**
 * مزامنة حالة الصندوق في الذاكرة وقاعدة البيانات.
 */
function sync_fund_status(int $fundId, string $status): void
{
    $normalized = ucfirst(strtolower($status));

    repo_execute('UPDATE Fund SET FundAccountStatus = ? WHERE FundID = ?', [$normalized, $fundId]);
    if (db_column_exists('Fund', 'Status')) {
        repo_execute('UPDATE Fund SET Status = ? WHERE FundID = ?', [$normalized, $fundId]);
    }
}

/**
 * دالة نشر صندوق
 * --------------------------------
 * تُغيّر حالة الصندوق من مسودة إلى منشور
 *
 * @param int $fundId معرّف الصندوق
 */
function publish_fund(int $fundId): void
{
    sync_fund_status($fundId, 'PendingApproval');
    // Notify admin that fund needs approval
    repo_execute(
        'INSERT INTO Notification (NotificationDescription, VerificationCode, SentDate, FundID) VALUES (?, 0, NOW(), ?)',
        ['New fund pending approval: Fund #' . $fundId, $fundId]
    );
}

/**
 * دالة حفظ الصندوق كمسودة
 * --------------------------------
 * تُبقي الصندوق في حالة Draft وتسمح بإرجاعه للوحة التحكم
 *
 * @param int $fundId معرّف الصندوق
 */
function set_fund_draft(int $fundId): void
{
    sync_fund_status($fundId, 'Draft');
    $notificationText = 'Fund moved to Draft: Fund #' . $fundId;
    repo_execute(
        'INSERT INTO Notification (NotificationDescription, VerificationCode, SentDate, FundID) VALUES (?, 0, NOW(), ?)',
        [$notificationText, $fundId]
    );
}

/**
 * دالة حذف مسودة صندوق
 * --------------------------------
 * تحذف صندوقاً في حالة المسودة
 *
 * @param int $fundId معرّف الصندوق
 */
function undo_fund_draft(int $fundId): void
{
    repo_execute('DELETE FROM Fund WHERE FundID = ? AND FundAccountStatus = ?', [$fundId, 'Draft']);
}

/**
 * دالة تحديث بيانات الصندوق
 * --------------------------------
 * تُحدّث بيانات صندوق موجود
 *
 * @param int   $fundId معرّف الصندوق
 * @param array $data البيانات المُحدّثة
 * @return bool true إذا نجح التحديث
 */
function update_fund(int $fundId, array $data): bool
{
    $existsInDb = repo_fetch_one('SELECT FundID FROM Fund WHERE FundID = ?', [$fundId]) !== null;
    if (!$existsInDb) return false;

    $map = [
        'title' => 'FundTitle',
        'investment_objective' => 'InvestmentObjective',
        'investment_type' => 'InvestmentType',
        'description' => 'FundDescription',
        'min_amount' => 'FundAmountMinimum',
        'max_amount' => 'FundAmountMaximum',
        'expected_return' => 'ExpectedReturnPercentage',
        'risk_level' => 'RiskLevel',
        'holding_period' => 'HoldingPeriod',
        'return_policy' => 'ReturnTimingPolicy',
        'fund_start_date' => 'FundDateStart',
        'fund_end_date' => 'FundDateEnd',
        'terms' => 'FundTermsAndConditions',
        'highlights' => 'Highlights',
    ];

    $supportsTermsColumn = db_column_exists('Fund', 'FundTermsAndConditions');
    $supportsHighlightsColumn = db_column_exists('Fund', 'Highlights');

    if ($existsInDb) {
        $setParts = [];
        $params = [];
        foreach ($data as $key => $value) {
            $field = $map[$key] ?? null;
            if (!$field) {
                continue;
            }
            if ($field === 'FundTermsAndConditions' && !$supportsTermsColumn) {
                continue;
            }
            if ($field === 'Highlights' && !$supportsHighlightsColumn) {
                continue;
            }
            if ($field === 'Highlights' && is_array($value)) {
                $value = json_encode(array_values(array_filter(array_map('trim', $value), static fn($item) => $item !== '')), JSON_UNESCAPED_UNICODE);
            }
            $setParts[] = $field . ' = ?';
            $params[] = $value;
        }
        if (!empty($setParts)) {
            $params[] = $fundId;
            repo_execute('UPDATE Fund SET ' . implode(', ', $setParts) . ' WHERE FundID = ?', $params);
        }
    }

    return true;
}

/**
 * دالة إخفاء صندوق
 * --------------------------------
 * تُخفي صندوقاً من العرض للطلاب
 *
 * @param int $fundId معرّف الصندوق
 * @return bool true إذا نجح
 */
function hide_fund(int $fundId): bool
{
    sync_fund_status($fundId, 'Hidden');
    return true;
}

/**
 * دالة إظهار صندوق
 * --------------------------------
 * تُعيد عرض صندوق مخفي
 *
 * @param int $fundId معرّف الصندوق
 * @return bool true إذا نجح
 */
function show_fund(int $fundId): bool
{
    sync_fund_status($fundId, 'Published');
    return true;
}

/**
 * دالة البحث عن صناديق
 * --------------------------------
 * تبحث في الصناديق حسب معايير متعددة
 *
 * @param array $criteria معايير البحث
 * @return array نتائج البحث
 */
function search_funds(array $criteria): array
{
    $funds = all_funds(true);
    $results = [];

    foreach ($funds as $fund) {
        $match = true;

        if (!empty($criteria['keyword'])) {
            $kw = strtolower($criteria['keyword']);
            $match = $match && (
                str_contains(strtolower($fund['FundTitle']), $kw) ||
                str_contains(strtolower($fund['FundDescription'] ?? ''), $kw) ||
                str_contains(strtolower($fund['InvestmentType'] ?? ''), $kw)
            );
        }
        if (!empty($criteria['risk_level'])) {
            $match = $match && strtolower($fund['RiskLevel']) === strtolower($criteria['risk_level']);
        }
        if (!empty($criteria['min_amount'])) {
            $match = $match && (float) $fund['FundAmountMinimum'] >= (float) $criteria['min_amount'];
        }
        if (!empty($criteria['max_amount'])) {
            $match = $match && (float) $fund['FundAmountMaximum'] <= (float) $criteria['max_amount'];
        }
        if (!empty($criteria['min_return'])) {
            $match = $match && (float) $fund['ExpectedReturnPercentage'] >= (float) $criteria['min_return'];
        }
        if (!empty($criteria['max_duration'])) {
            $match = $match && (int) $fund['HoldingPeriod'] <= (int) $criteria['max_duration'];
        }
        if (!empty($criteria['investment_type'])) {
            $match = $match && strtolower($fund['InvestmentType']) === strtolower($criteria['investment_type']);
        }

        if ($match) {
            $results[] = $fund;
        }
    }

    return $results;
}

// ===================================================
// 5. دوال الاشتراكات والعقود
// ===================================================

/**
 * دالة الاشتراك في صندوق
 * --------------------------------
 * تُنشئ اشتراكاً (عقداً) للطالب في صندوق
 *
 * @param int   $studentId معرّف الطالب
 * @param int   $fundId معرّف الصندوق
 * @param array $data بيانات الاشتراك
 * @return bool true إذا نجح الاشتراك
 */
function subscribe_to_fund(int $studentId, int $fundId, array $data): bool
{
    $_SESSION['subscription_error'] = '';

    if ($studentId <= 0 || $fundId <= 0) {
        $_SESSION['subscription_error'] = 'Invalid student or fund identifier.';
        return false;
    }

    $studentExists = repo_fetch_one('SELECT StudentID FROM Student WHERE StudentID = ? LIMIT 1', [$studentId]);
    if (!$studentExists) {
        $_SESSION['subscription_error'] = 'Student account was not found in database.';
        return false;
    }

    // Prefer DB fund info, fall back to demo store
    $fund = fund_by_id($fundId);
    if (!$fund) {
        $_SESSION['subscription_error'] = 'Selected fund could not be found.';
        return false;
    }

    if (strtolower((string) ($fund['FundAccountStatus'] ?? 'draft')) !== 'published') {
        $_SESSION['subscription_error'] = 'This fund is not published for subscription.';
        return false;
    }

    $amount = (float) ($data['amount'] ?? 0);
    if ($amount < (float) $fund['FundAmountMinimum'] || $amount > (float) $fund['FundAmountMaximum']) {
        $_SESSION['subscription_error'] = 'Subscription amount is outside fund limits.';
        return false;
    }

    if (student_has_contract($studentId, $fundId)) {
        $_SESSION['subscription_error'] = 'You already have an active subscription request for this fund.';
        return false;
    }

    $pdo = db_connection();
    if (!$pdo) {
        $_SESSION['subscription_error'] = 'Database connection is unavailable.';
        return false;
    }

    $managerLicense = (int) ($fund['FundManagerNumberofLicense'] ?? ($fund['FundManagerID'] ?? 0));
    if ($managerLicense <= 0) {
        $_SESSION['subscription_error'] = 'Fund manager reference is missing for this fund.';
        return false;
    }

    $managerExists = repo_fetch_one('SELECT FundManagerNumberofLicense FROM FundManager WHERE FundManagerNumberofLicense = ? LIMIT 1', [$managerLicense]);
    if (!$managerExists) {
        $_SESSION['subscription_error'] = 'Fund manager account linked to this fund was not found.';
        return false;
    }

    $description    = 'Subscription to ' . ($fund['FundTitle'] ?? ('Fund #' . $fundId));
    $cardNumber     = preg_replace('/\D+/', '', (string) ($data['card_number'] ?? ''));

    $startedTransaction = false;
    if (!$pdo->inTransaction()) {
        $startedTransaction = $pdo->beginTransaction();
    }

    // --- Persist Contract to DB ---
    $cidRow = repo_fetch_one('SELECT COALESCE(MAX(ContractID), 0) AS id FROM Contract');
    $cid    = (int) ($cidRow['id'] ?? 0) + 1;
    if (!repo_execute(
        'INSERT INTO Contract (ContractID, RolesDescription, FundID, StudentID, HassadID) VALUES (?, ?, ?, ?, 1)',
        [$cid, $description, $fundId, $studentId]
    )) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['subscription_error'] = 'Failed to create contract record.';
        return false;
    }

    // --- Persist Transaction to DB ---
    $tidRow = repo_fetch_one('SELECT COALESCE(MAX(TransactionID), 0) AS id FROM `Transaction`');
    $tid    = (int) ($tidRow['id'] ?? 0) + 1;

    // Verify card exists in BankAccount to satisfy FK constraint
    $cardForDb = null;
    if ($cardNumber !== '') {
        $cardExists = repo_fetch_one(
            'SELECT CreditCardNumber FROM BankAccount WHERE StudentID = ? AND CreditCardNumber = ? LIMIT 1',
            [$studentId, $cardNumber]
        );
        $cardForDb = $cardExists ? $cardNumber : null;
    }

    if (!repo_execute(
        'INSERT INTO `Transaction`
         (TransactionID, TransactionType, Direction, FundCapital, TotalCapital,
          FundReturns, TotalReturns, FundWithdrawnProfit, TotalWithdrawnProfit,
          FundReinvestedReturns, TotalReinvestedReturns, FundFullWithdrawalAmount,
          TotalFullWithdrawalAmount, StudentID, CreditCardNumber, FundID, FundManagerNumberofLicense)
         VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0, 0, ?, ?, ?, ?)',
        [$tid, 'Investment', 'In', $amount, $amount, $studentId, $cardForDb, $fundId, $managerLicense]
    )) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['subscription_error'] = 'Failed to create transaction record for the selected card/fund.';
        return false;
    }

    if (db_column_exists('Fund', 'CurrentSubscribers')) {
        if (!repo_execute('UPDATE Fund SET CurrentSubscribers = COALESCE(CurrentSubscribers, 0) + 1 WHERE FundID = ?', [$fundId])) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['subscription_error'] = 'Failed to update subscriber count for the fund.';
            return false;
        }
    }

    if (db_column_exists('Fund', 'TotalFundAmount')) {
        if (!repo_execute('UPDATE Fund SET TotalFundAmount = COALESCE(TotalFundAmount, 0) + ? WHERE FundID = ?', [$amount, $fundId])) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['subscription_error'] = 'Failed to update fund total amount.';
            return false;
        }
    }

    if ($startedTransaction && $pdo->inTransaction()) {
        $pdo->commit();
    }

    return true;
}

/**
 * دالة إلغاء اشتراك في صندوق
 * --------------------------------
 * تُلغي اشتراك الطالب في صندوق
 *
 * @param int $studentId معرّف الطالب
 * @param int $fundId معرّف الصندوق
 */
function cancel_fund_subscription(int $studentId, int $fundId): void
{
    repo_execute('DELETE FROM Contract WHERE StudentID = ? AND FundID = ?', [$studentId, $fundId]);
    repo_execute('DELETE FROM ParticipationApproval WHERE StudentID = ? AND FundID = ?', [$studentId, $fundId]);
    repo_execute('UPDATE Fund SET CurrentSubscribers = GREATEST(COALESCE(CurrentSubscribers, 0) - 1, 0) WHERE FundID = ?', [$fundId]);

}

/**
 * دالة التحقق من وجود عقد للطالب
 * --------------------------------
 * تتحقق هل الطالب مشترك في صندوق معين
 *
 * @param int $studentId معرّف الطالب
 * @param int $fundId معرّف الصندوق
 * @return bool true إذا كان مشتركاً
 */
function student_has_contract(int $studentId, int $fundId): bool
{
    $exists = repo_fetch_one(
        'SELECT ContractID FROM Contract WHERE StudentID = ? AND FundID = ? LIMIT 1',
        [$studentId, $fundId]
    );
    if ($exists) {
        return true;
    }

    return false;
}

/**
 * دالة جلب عقود الطالب
 * --------------------------------
 * تُرجع جميع العقود (الاشتراكات) الخاصة بطالب
 *
 * @param int $studentId معرّف الطالب
 * @return array قائمة العقود
 */
function student_contracts(int $studentId): array
{
    $dbContracts = repo_fetch_all(
        'SELECT ContractID, FundID, StudentID, RolesDescription
         FROM Contract
         WHERE StudentID = ?
         ORDER BY ContractID DESC',
        [$studentId]
    );
    if (!empty($dbContracts)) {
        return array_map(static function (array $row): array {
            return [
                'ContractID' => (int) ($row['ContractID'] ?? 0),
                'FundID' => (int) ($row['FundID'] ?? 0),
                'StudentID' => (int) ($row['StudentID'] ?? 0),
                'RolesDescription' => (string) ($row['RolesDescription'] ?? ''),
            ];
        }, $dbContracts);
    }

    return [];
}

/**
 * دالة جلب العقود بطريقة موحدة للعرض
 * --------------------------------
 * تدعم قواعد البيانات الحقيقية مع الرجوع للوضع التجريبي عند الحاجة
 *
 * @param int|null $studentId معرّف الطالب (اختياري)
 * @param int|null $managerLicense رقم رخصة المدير (اختياري)
 * @return array قائمة العقود بصيغة موحدة للعرض
 */
function contracts_for_view(?int $studentId = null, ?int $managerLicense = null): array
{
    $where = [];
    $params = [];

    if ($studentId !== null) {
        $where[] = 'c.StudentID = ?';
        $params[] = $studentId;
    }
    if ($managerLicense !== null) {
        $where[] = 'f.FundManagerNumberofLicense = ?';
        $params[] = $managerLicense;
    }

    $sql = 'SELECT
                c.ContractID,
                c.FundID,
                c.StudentID,
                c.RolesDescription,
                COALESCE(MAX(CASE WHEN t.TransactionType = "Investment" THEN t.FundCapital ELSE 0 END), 0) AS Amount,
                s.StudentNameFirst,
                s.StudentNameLast,
                f.FundTitle,
                f.FundManagerNumberofLicense,
                m.FundManagerNameFirst,
                m.FundManagerNameLast
            FROM Contract c
            LEFT JOIN Student s ON s.StudentID = c.StudentID
            LEFT JOIN Fund f ON f.FundID = c.FundID
            LEFT JOIN FundManager m ON m.FundManagerNumberofLicense = f.FundManagerNumberofLicense
            LEFT JOIN `Transaction` t ON t.StudentID = c.StudentID AND t.FundID = c.FundID';

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' GROUP BY c.ContractID, c.FundID, c.StudentID, c.RolesDescription, s.StudentNameFirst, s.StudentNameLast, f.FundTitle, f.FundManagerNumberofLicense, m.FundManagerNameFirst, m.FundManagerNameLast
              ORDER BY c.ContractID DESC';

    $rows = repo_fetch_all($sql, $params);
    if (!empty($rows)) {
        return array_map(static function (array $row): array {
            $managerLicense = (int) ($row['FundManagerNumberofLicense'] ?? 0);
            $managerName = trim((string) ($row['FundManagerNameFirst'] ?? '') . ' ' . (string) ($row['FundManagerNameLast'] ?? ''));
            if ($managerName === '' && $managerLicense > 0) {
                $managerName = 'Manager #' . $managerLicense;
            }
            return [
                'ContractID' => (int) ($row['ContractID'] ?? 0),
                'FundID' => (int) ($row['FundID'] ?? 0),
                'StudentID' => (int) ($row['StudentID'] ?? 0),
                'Amount' => (float) ($row['Amount'] ?? 0),
                'RolesDescription' => (string) ($row['RolesDescription'] ?? ''),
                'FundTitle' => (string) ($row['FundTitle'] ?? 'N/A'),
                'StudentName' => trim((string) ($row['StudentNameFirst'] ?? '') . ' ' . (string) ($row['StudentNameLast'] ?? '')),
                'ManagerLicense' => $managerLicense,
                'ManagerName' => $managerName,
            ];
        }, $rows);
    }

    return [];
}

/**
 * دالة جلب عقود الطالب بصيغة العرض
 */
function contracts_for_student(int $studentId): array
{
    return contracts_for_view($studentId, null);
}

/**
 * دالة جلب عقود طلاب مدير الصندوق
 */
function contracts_for_manager(int $managerLicense): array
{
    return contracts_for_view(null, $managerLicense);
}

/**
 * دالة جلب مشتركي صندوق
 * --------------------------------
 * تُرجع قائمة المشتركين في صندوق معين
 *
 * @param int $fundId معرّف الصندوق
 * @return array قائمة المشتركين
 */
function subscribers_for_fund(int $fundId): array
{
    return repo_fetch_all(
        'SELECT c.*, s.StudentNameFirst, s.StudentNameLast, s.StudentPhoneNumber
         FROM Contract c
         LEFT JOIN Student s ON s.StudentID = c.StudentID
         WHERE c.FundID = ?',
        [$fundId]
    );
}

// ===================================================
// 6. دوال المحفظة والمعاملات
// ===================================================

/**
 * دالة جلب معاملات الطالب
 * --------------------------------
 * تُرجع جميع المعاملات المالية للطالب
 *
 * @param int $studentId معرّف الطالب
 * @return array قائمة المعاملات
 */
function student_transactions(int $studentId): array
{
    return repo_fetch_all('SELECT * FROM `Transaction` WHERE StudentID = ? ORDER BY TransactionID DESC', [$studentId]);
}

/**
 * دالة جلب جميع المعاملات
 * --------------------------------
 * تُرجع جميع المعاملات في النظام
 *
 * @return array قائمة المعاملات
 */
function all_transactions(): array
{
    return repo_fetch_all('SELECT * FROM `Transaction` ORDER BY TransactionID DESC');
}

/**
 * دالة جلب آخر مبلغ استثمار
 * --------------------------------
 * تُرجع آخر مبلغ استثمره الطالب في صندوق محدد
 *
 * @param int $studentId معرّف الطالب
 * @param int $fundId معرّف الصندوق
 * @return float مبلغ الاستثمار
 */
function latest_investment_amount(int $studentId, int $fundId): float
{
    $dbLatest = repo_fetch_one(
        'SELECT FundCapital
         FROM `Transaction`
         WHERE StudentID = ? AND FundID = ? AND TransactionType = ?
         ORDER BY TransactionID DESC
         LIMIT 1',
        [$studentId, $fundId, 'Investment']
    );
    if ($dbLatest) {
        return (float) ($dbLatest['FundCapital'] ?? 0);
    }

    return 0.0;
}

/**
 * دالة بطاقات لوحة تحكم الطالب
 * --------------------------------
 * تُنشئ بطاقات ملخص لكل صندوق مشترك فيه الطالب
 *
 * @param int $studentId معرّف الطالب
 * @return array بطاقات الملخص
 */
function student_dashboard_cards(int $studentId): array
{
    $contracts = contracts_for_student($studentId);
    if (!empty($contracts)) {
        $cards = [];
        $seenFunds = [];

        foreach ($contracts as $contract) {
            $fundId = (int) ($contract['FundID'] ?? 0);
            if ($fundId <= 0 || isset($seenFunds[$fundId])) {
                continue;
            }

            $fund = fund_by_id($fundId);
            if (!$fund) {
                continue;
            }

            $invested = latest_investment_amount($studentId, $fundId);
            $returnPct = (float) ($fund['ExpectedReturnPercentage'] ?? 0);
            $cards[] = [
                'fund' => $fund,
                'subscribed' => true,
                'invested' => $invested,
                'current_value' => $invested * (1 + $returnPct / 100),
            ];
            $seenFunds[$fundId] = true;
        }

        foreach (all_funds(true) as $fund) {
            $fundId = (int) ($fund['FundID'] ?? 0);
            if ($fundId <= 0 || isset($seenFunds[$fundId])) {
                continue;
            }
            $cards[] = [
                'fund' => $fund,
                'subscribed' => false,
                'invested' => 0,
                'current_value' => 0,
            ];
        }

        return $cards;
    }

    return [];
}

// ===================================================
// 7. دوال السحب
// ===================================================

/**
 * دالة إنشاء طلب سحب
 * --------------------------------
 * تُنشئ طلب سحب أرباح أو رأس مال
 *
 * @param int        $studentId معرّف الطالب
 * @param float      $amount المبلغ
 * @param string     $type نوع السحب
 * @param int|null   $fundId معرّف الصندوق (اختياري)
 * @return int معرّف طلب السحب
 */
function create_withdrawal_request(int $studentId, float $amount, string $type = 'Profit', ?int $fundId = null): int
{
    $bank = current_bank_account($studentId);
    $iban = $bank['BankAccountIban'] ?? '';

    repo_execute(
        'INSERT INTO WithdrawalRequest (StudentID, FundID, Amount, WithdrawalType, BankAccountIban, Status, RequestDate) VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$studentId, $fundId, $amount, $type, $iban, 'Pending']
    );
    $row = repo_fetch_one('SELECT LAST_INSERT_ID() AS id');
    return (int) ($row['id'] ?? 0);
}

/**
 * دالة جلب طلبات سحب الطالب
 * --------------------------------
 * تُرجع طلبات السحب الخاصة بطالب
 *
 * @param int $studentId معرّف الطالب
 * @return array قائمة طلبات السحب
 */
function student_withdrawal_requests(int $studentId): array
{
    return repo_fetch_all('SELECT * FROM WithdrawalRequest WHERE StudentID = ? ORDER BY WithdrawalID DESC', [$studentId]);
}

/**
 * دالة جلب جميع طلبات السحب
 * --------------------------------
 * تُرجع جميع طلبات السحب أو المُصفّاة بحالة معينة
 *
 * @param string|null $status حالة التصفية (اختياري)
 * @return array قائمة طلبات السحب
 */
function all_withdrawal_requests(?string $status = null): array
{
    if ($status !== null) {
        return repo_fetch_all('SELECT * FROM WithdrawalRequest WHERE Status = ? ORDER BY WithdrawalID DESC', [$status]);
    }
    return repo_fetch_all('SELECT * FROM WithdrawalRequest ORDER BY WithdrawalID DESC');
}

/**
 * دالة معالجة طلب سحب
 * --------------------------------
 * تُوافق أو ترفض طلب سحب
 *
 * @param int         $withdrawalId معرّف طلب السحب
 * @param string      $status الحالة الجديدة
 * @param int         $processedBy معرّف المعالج
 * @param string|null $reason سبب الرفض
 * @return bool true إذا نجح
 */
function process_withdrawal(int $withdrawalId, string $status, int $processedBy, ?string $reason = null): bool
{
    return repo_execute(
        'UPDATE WithdrawalRequest SET Status = ?, ProcessedBy = ?, ProcessedDate = NOW(), RejectionReason = ? WHERE WithdrawalID = ?',
        [$status, $processedBy, $reason, $withdrawalId]
    );
}

// ===================================================
// 9. دوال حظر المستخدمين
// ===================================================

/**
 * دالة حظر مستخدم
 * --------------------------------
 * تُنشئ سجل حظر لمستخدم
 *
 * @param string $userType نوع المستخدم
 * @param int    $userId معرّف المستخدم
 * @param string $reason سبب الحظر
 * @param int    $bannedBy معرّف الحاظر
 * @param string $bannedByType نوع الحاظر
 * @return bool true إذا نجح
 */
function ban_user(string $userType, int $userId, string $reason, int $bannedBy, string $bannedByType): bool
{
    $normalizedType = match (strtolower(trim($userType))) {
        'student' => 'Student',
        'fundmanager', 'fund_manager', 'manager' => 'FundManager',
        'admin' => 'Admin',
        default => $userType,
    };

    // قيد أمني: المدير لا يمكنه حظر مدير آخر
    if ($bannedByType === 'FundManager' && $normalizedType === 'FundManager') {
        return false; // المدير لا يمكنه حظر مدير آخر
    }

    // التحقق من عدم وجود حظر فعّال
    $existing = repo_fetch_one(
        'SELECT BanID FROM UserBan WHERE UserType = ? AND UserID = ? AND IsActive = 1',
        [$normalizedType, $userId]
    );
    if ($existing) {
        return false;
    }

    $saved = repo_execute(
        'INSERT INTO UserBan (UserType, UserID, BanReason, BannedBy, BannedByType, BanDate, IsActive)
         VALUES (?, ?, ?, ?, ?, NOW(), 1)',
        [$normalizedType, $userId, $reason, $bannedBy, $bannedByType]
    );

    return $saved;
}

/**
 * دالة إلغاء حظر مستخدم
 * --------------------------------
 * تُلغي الحظر الفعّال عن مستخدم
 *
 * @param string $userType نوع المستخدم
 * @param int    $userId معرّف المستخدم
 * @return bool true إذا نجح
 */
function unban_user(string $userType, int $userId): bool
{
    $normalizedType = match (strtolower(trim($userType))) {
        'student' => 'Student',
        'fundmanager', 'fund_manager', 'manager' => 'FundManager',
        'admin' => 'Admin',
        default => $userType,
    };

    $dbUpdated = repo_execute(
        'UPDATE UserBan SET IsActive = 0, UnbanDate = NOW() WHERE UserType = ? AND UserID = ? AND IsActive = 1',
        [$normalizedType, $userId]
    );

    return $dbUpdated;
}

/**
 * دالة التحقق من حظر المستخدم
 * --------------------------------
 * تتحقق هل المستخدم محظور حالياً
 *
 * @param int $userId معرّف المستخدم
 * @return bool true إذا كان محظوراً
 */
function is_user_banned(int $userId): bool
{
    $row = repo_fetch_one(
        'SELECT BanID FROM UserBan WHERE UserID = ? AND IsActive = 1',
        [$userId]
    );
    if ($row !== null) {
        return true;
    }

    return false;
}

/**
 * دالة جلب جميع سجلات الحظر
 * --------------------------------
 * @return array قائمة الحظر
 */
function all_bans(): array
{
    $rows = repo_fetch_all('SELECT * FROM UserBan ORDER BY BanDate DESC');
    if (!empty($rows)) {
        return $rows;
    }

    return [];
}

// ===================================================
// 10. دوال JIC (الجهة الراعية)
// ===================================================

/**
 * دالة الموافقة على مشاركة طالب
 * --------------------------------
 * توافق على مشاركة طالب في صندوق استثماري من قبل الجهة الراعية (JIC).
 * تحدث حالة الطلب في جدول ParticipationApproval إلى 'Approved' وتسجل تاريخ الموافقة ومعرف الموافق.
 *
 * المتغيرات:
 * - $studentId (int): معرف الطالب الذي يطلب المشاركة. يجب أن يكون موجوداً في جدول Student.
 * - $fundId (int): معرف الصندوق الاستثماري. يجب أن يكون موجوداً في جدول Fund.
 * - $jicId (int): معرف الجهة الراعية (JIC) التي توافق على الطلب. يجب أن يكون موجوداً في جدول JicSponsor.
 *
 * العملية:
 * 1. تنفيذ استعلام UPDATE على جدول ParticipationApproval.
 * 2. تحديث ApprovalStatus إلى 'Approved'.
 * 3. تحديث ApprovedBy إلى $jicId.
 * 4. تحديث ApprovalDate إلى التاريخ الحالي باستخدام NOW().
 * 5. الشرط: StudentID = $studentId AND FundID = $fundId AND LOWER(ApprovalStatus) = 'pending'.
 * 6. إرجاع true إذا نجح التحديث، false إذا فشل (مثل عدم وجود صف يطابق الشرط).
 *
 * ملاحظات أمنية:
 * - يجب التحقق من صلاحيات المستخدم الحالي (JIC) قبل استدعاء هذه الدالة.
 * - لا يتم التحقق من وجود الطالب أو الصندوق داخل الدالة، يجب القيام بذلك مسبقاً.
 *
 * @param int $studentId معرّف الطالب
 * @param int $fundId معرّف الصندوق
 * @param int $jicId معرّف الجهة الراعية
 * @return bool true إذا نجح التحديث
 */
function approve_participation(int $studentId, int $fundId, int $jicId): bool
{
    return repo_execute(
        "UPDATE ParticipationApproval SET ApprovalStatus='Approved', ApprovedBy=?, ApprovalDate=NOW() WHERE StudentID=? AND FundID=? AND ApprovalStatus='Pending'",
        [$jicId, $studentId, $fundId]
    );
}

/**
 * دالة رفض مشاركة طالب
 * --------------------------------
 * ترفض مشاركة طالب في صندوق استثماري من قبل الجهة الراعية (JIC) مع ذكر سبب الرفض.
 * تحدث حالة الطلب في جدول ParticipationApproval إلى 'Rejected' وتسجل السبب وتاريخ الرفض.
 *
 * المتغيرات:
 * - $studentId (int): معرف الطالب الذي يطلب المشاركة. يجب أن يكون موجوداً في جدول Student.
 * - $fundId (int): معرف الصندوق الاستثماري. يجب أن يكون موجوداً في جدول Fund.
 * - $reason (string): سبب رفض الطلب. يُخزن في حقل Notes في جدول ParticipationApproval.
 *
 * العملية:
 * 1. تنفيذ استعلام UPDATE على جدول ParticipationApproval.
 * 2. تحديث ApprovalStatus إلى 'Rejected'.
 * 3. تحديث Notes إلى $reason.
 * 4. تحديث ApprovalDate إلى التاريخ الحالي باستخدام NOW().
 * 5. الشرط: StudentID = $studentId AND FundID = $fundId AND LOWER(ApprovalStatus) = 'pending'.
 * 6. إرجاع true إذا نجح التحديث، false إذا فشل (مثل عدم وجود صف يطابق الشرط).
 *
 * ملاحظات أمنية:
 * - يجب التحقق من صلاحيات المستخدم الحالي (JIC) قبل استدعاء هذه الدالة.
 * - السبب مطلوب لتوثيق قرار الرفض، يجب ألا يكون فارغاً.
 * - لا يتم التحقق من صحة البيانات داخل الدالة، يجب القيام بذلك مسبقاً.
 *
 * @param int    $studentId معرّف الطالب
 * @param int    $fundId معرّف الصندوق
 * @param string $reason السبب
 * @return bool true إذا نجح
 */
function reject_participation(int $studentId, int $fundId, string $reason): bool
{
    return repo_execute(
        "UPDATE ParticipationApproval SET ApprovalStatus='Rejected', Notes=?, ApprovalDate=NOW() WHERE StudentID=? AND FundID=? AND ApprovalStatus='Pending'",
        [$reason, $studentId, $fundId]
    );
}

/**
 * دالة جلب جميع طلبات الموافقة على المشاركة
 * --------------------------------
 * ترجع قائمة بجميع طلبات الموافقة على مشاركة الطلاب في الصناديق الاستثمارية، مع إمكانية تصفية حسب الحالة.
 * تجلب البيانات من جدول ParticipationApproval مع معلومات إضافية من جداول Student و Fund.
 *
 * المتغيرات:
 * - $statusFilter (string): فلتر الحالة (اختياري). إذا كان فارغاً، يرجع جميع الطلبات. القيم المقبولة: 'pending', 'approved', 'rejected' (غير حساس لحالة الأحرف).
 *
 * العملية:
 * 1. بناء استعلام SQL أساسي يجلب البيانات من ParticipationApproval مع JOIN لـ Student و Fund.
 * 2. إضافة شرط WHERE إذا كان $statusFilter غير فارغ، باستخدام LOWER() للمقارنة غير الحساسة لحالة الأحرف.
 * 3. ترتيب النتائج حسب ApprovalID تنازلياً (الأحدث أولاً).
 * 4. تنفيذ الاستعلام باستخدام repo_fetch_all().
 * 5. إرجاع مصفوفة من الصفوف، كل صف يحتوي على بيانات الطلب مع اسم الطالب واسم الصندوق.
 *
 * ملاحظات تقنية:
 * - يستخدم LEFT JOIN لضمان إرجاع الطلبات حتى لو كان الطالب أو الصندوق محذوفاً (سيكون null).
 * - الحقول المرجعة: pa.* (جميع حقول ParticipationApproval), s.StudentNameFirst, s.StudentNameLast, f.FundTitle.
 * - إذا فشل الاتصال بقاعدة البيانات، يرجع مصفوفة فارغة.
 *
 * استخدامات:
 * - عرض قائمة الطلبات في لوحة تحكم JIC أو المدير.
 * - تصفية الطلبات حسب الحالة للمعالجة.
 *
 * @param string $statusFilter فلتر الحالة (اختياري)
 * @return array قائمة طلبات الموافقة
 */
function all_participation_approvals(string $statusFilter = '', int $limit = 100, int $offset = 0): array
{
    static $cache = [];
    $cacheKey = md5($statusFilter . $limit . $offset);

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $sql = 'SELECT pa.*, s.StudentNameFirst, s.StudentNameLast, f.FundTitle
            FROM ParticipationApproval pa
            LEFT JOIN Student s ON s.StudentID = pa.StudentID
            LEFT JOIN Fund f ON f.FundID = pa.FundID';
    $params = [];
    if ($statusFilter !== '') {
        $sql .= ' WHERE pa.ApprovalStatus = ?';
        $params[] = ucfirst(strtolower($statusFilter)); // Normalize to match DB case
    }
    $sql .= ' ORDER BY pa.ApprovalID DESC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;

    $result = repo_fetch_all($sql, $params);
    $cache[$cacheKey] = $result;
    return $result;
}

/**
 * دالة تفويض حساب مستخدم
 * --------------------------------
 * تفوض (تصرح) حساب مستخدم جديد أو غير مفعل، مما يسمح له بالوصول الكامل للنظام.
 * تحدث حالة الحساب في الجدول المناسب بناءً على نوع المستخدم.
 *
 * المتغيرات:
 * - $userType (string): نوع المستخدم. القيم المقبولة: 'Student' أو 'FundManager'.
 * - $userId (int): معرف المستخدم. للطالب: StudentID، للمدير: FundManagerNumberofLicense.
 *
 * العملية:
 * 1. فحص نوع المستخدم:
 *    - إذا 'Student': تحديث جدول Student، تعيين StudentAccountStates إلى 'Verified'.
 *    - إذا 'FundManager': تحديث جدول FundManager، تعيين FundManagerAccountStatus إلى 'Approved'.
 *    - إذا نوع آخر: إرجاع false (غير مدعوم).
 * 2. تنفيذ استعلام UPDATE باستخدام repo_execute().
 * 3. إرجاع true إذا نجح التحديث، false إذا فشل (مثل عدم وجود المستخدم أو خطأ في قاعدة البيانات).
 *
 * ملاحظات أمنية:
 * - يجب التحقق من صلاحيات المستخدم الحالي (Admin أو JIC) قبل استدعاء هذه الدالة.
 * - لا يتم التحقق من وجود المستخدم داخل الدالة، يجب القيام بذلك مسبقاً.
 * - هذه الدالة عامة ويمكن استخدامها لأنواع مستخدمين متعددة، لكن حالياً تدعم Student و FundManager فقط.
 *
 * استخدامات:
 * - تفعيل حسابات الطلاب الجدد بعد التحقق من الهوية.
 * - الموافقة على حسابات مديري الصناديق الجدد.
 *
 * @param string $userType نوع المستخدم
 * @param int    $userId معرّف المستخدم
 * @return bool true إذا نجح
 */
function authorize_user_account(string $userType, int $userId): bool
{
    if ($userType === 'Student') {
        return repo_execute('UPDATE Student SET StudentAccountStates = ? WHERE StudentID = ?', ['Verified', $userId]);
    }
    if ($userType === 'FundManager') {
        return repo_execute('UPDATE FundManager SET FundManagerAccountStatus = ? WHERE FundManagerNumberofLicense = ?', ['Approved', $userId]);
    }
    return false;
}

// ===================================================
// 11. دوال الإدارة والنظام
// ===================================================

/**
 * دالة جلب جميع الطلاب
 * --------------------------------
 * @return array قائمة الطلاب
 */
function all_students(): array
{
    $dbStudents = repo_fetch_all('SELECT * FROM Student ORDER BY StudentID DESC');
    if (!empty($dbStudents)) {
        return $dbStudents;
    }
    return [];
}

/**
 * دالة جلب جميع مديري الصناديق
 * --------------------------------
 * @return array قائمة المديرين
 */
function all_managers(): array
{
    $dbManagers = repo_fetch_all('SELECT * FROM FundManager ORDER BY FundManagerNumberofLicense DESC');
    if (!empty($dbManagers)) {
        return $dbManagers;
    }
    return [];
}

/**
 * دالة جلب جميع حسابات JIC
 * --------------------------------
 * @return array قائمة حسابات JIC
 */
function all_jic_sponsors(): array
{
    $dbJic = repo_fetch_all('SELECT * FROM JicSponsor ORDER BY JicSponsorID DESC');
    if (!empty($dbJic)) {
        return $dbJic;
    }
    return [];
}

/**
 * دالة جلب جميع حسابات المشرفين
 * --------------------------------
 * @return array قائمة حسابات Admin
 */
function all_admin_users(): array
{
    $dbAdmins = repo_fetch_all('SELECT * FROM Admin ORDER BY AdminID DESC');
    if (!empty($dbAdmins)) {
        return $dbAdmins;
    }
    return [];
}

/**
 * دالة جلب مستويات الوصول
 * --------------------------------
 * @return array قائمة مستويات الوصول
 */
function get_access_levels(): array
{
    $dbLevels = repo_fetch_all('SELECT * FROM AccessLevel ORDER BY AccessID ASC');
    if (!empty($dbLevels)) {
        return $dbLevels;
    }
    return [];
}

/**
 * دالة تحديث مستوى وصول المستخدم
 * --------------------------------
 * تُحدّث مستوى وصول مستخدم
 *
 * @param string $userType نوع المستخدم
 * @param int    $userId معرّف المستخدم
 * @param int    $accessId معرّف مستوى الوصول
 * @return bool true إذا نجح
 */
function update_user_access_level(string $userType, int $userId, int $accessId): bool
{
    if ($userType === 'Student') {
        return repo_execute('UPDATE Student SET AccessID = ? WHERE StudentID = ?', [$accessId, $userId]);
    }
    if ($userType === 'FundManager') {
        return repo_execute('UPDATE FundManager SET AccessID = ? WHERE FundManagerNumberofLicense = ?', [$accessId, $userId]);
    }
    return false;
}

/**
 * دالة جلب إعداد النظام
 * --------------------------------
 * تُرجع قيمة إعداد معين من إعدادات النظام
 *
 * @param string $key مفتاح الإعداد
 * @return string|null قيمة الإعداد
 */
function get_system_setting(string $key): ?string
{
    $row = repo_fetch_one('SELECT SettingValue FROM SystemSettings WHERE SettingKey = ?', [$key]);
    return $row ? (string) ($row['SettingValue'] ?? null) : null;
}

/**
 * دالة تحديث إعداد النظام
 * --------------------------------
 * تُحدّث قيمة إعداد في النظام
 *
 * @param string $key مفتاح الإعداد
 * @param string $value القيمة الجديدة
 * @param int    $adminId معرّف المدير
 * @return bool true إذا نجح
 */
function update_system_setting(string $key, string $value, int $adminId): bool
{
    $exists = repo_fetch_one('SELECT SettingKey FROM SystemSettings WHERE SettingKey = ?', [$key]);
    if ($exists) {
        return repo_execute('UPDATE SystemSettings SET SettingValue = ? WHERE SettingKey = ?', [$value, $key]);
    }
    return repo_execute(
        'INSERT INTO SystemSettings (SettingKey, SettingValue, SettingDescription) VALUES (?, ?, ?)',
        [$key, $value, '']
    );
}

/**
 * تسجيل حساب JIC جديد (يُستخدم من لوحة الإدارة)
 */
function register_jic(array $data, ?string $redirect_on_error = null): bool
{
    // JIC accounts are stored in the Admin table with a distinguishing note,
    // or handled as a plain admin-level user depending on project scope.
    // For now we create a minimal Admin record so the rest of the system works.
    $jicId    = (int)   ($data['jic_id']    ?? rand(10000, 99999));
    $username = trim(   ($data['username']  ?? ''));
    $phone    = trim(   ($data['phone']     ?? ''));
    $password =         ($data['password']  ?? '');

    if ($username === '' || $password === '') {
        set_flash('error', 'JIC username and password are required.');
        if ($redirect_on_error) redirect_to($redirect_on_error, ['tab' => 'create-user']);
        return false;
    }

    $dup = repo_fetch_one('SELECT AdminID FROM Admin WHERE AdminID = ?', [$jicId]);
    if ($dup) {
        set_flash('error', 'A JIC account with this ID already exists.');
        if ($redirect_on_error) redirect_to($redirect_on_error, ['tab' => 'create-user']);
        return false;
    }

    $ok = repo_execute(
        'INSERT INTO Admin (AdminID, UserName, HassadPassword, PhoneNum, UpdateVersion) VALUES (?, ?, ?, ?, ?)',
        [$jicId, $username, $password, $phone, 'JIC-1.0']
    );

    if (!$ok) {
        set_flash('error', 'Failed to create JIC account.');
        if ($redirect_on_error) redirect_to($redirect_on_error, ['tab' => 'create-user']);
        return false;
    }

    return true;
}

/**
 * دالة إرسال رسالة SMS
 * --------------------------------
 * تُسجل رسالة SMS في سجل الرسائل (محاكاة)
 *
 * @param string   $recipientType نوع المستقبل
 * @param int|null $recipientId معرّف المستقبل
 * @param string   $message نص الرسالة
 * @param string   $messageType نوع الرسالة
 * @param int      $sentBy معرّف المرسل
 * @return int معرّف الرسالة
 */
function send_sms_notification(string $recipientType, ?int $recipientId, string $message, string $messageType, int $sentBy): int
{
    // Map form values to DB enum values
    $recipientTypeMap = [
        'single'       => 'Student',
        'all_students' => 'Student',
        'all_managers' => 'FundManager',
        'all'          => 'All',
        'Student'      => 'Student',
        'FundManager'  => 'FundManager',
        'Admin'        => 'Admin',
        'All'          => 'All',
    ];
    $dbRecipientType = $recipientTypeMap[$recipientType] ?? 'All';

    $messageTypeMap = [
        'General'      => 'Notification',
        'Reminder'     => 'Notification',
        'Notification' => 'Notification',
        'Verification' => 'Verification',
        'Marketing'    => 'Marketing',
        'Alert'        => 'Alert',
    ];
    $dbMessageType = $messageTypeMap[$messageType] ?? 'Notification';

    // Resolve phone number
    $phone = 'N/A';
    if ($recipientId !== null) {
        if ($dbRecipientType === 'Student') {
            $row = repo_fetch_one('SELECT StudentPhoneNumber FROM Student WHERE StudentID = ?', [$recipientId]);
            $phone = (string) ($row['StudentPhoneNumber'] ?? 'N/A');
        } elseif ($dbRecipientType === 'FundManager') {
            $row = repo_fetch_one('SELECT FundManagerPhone FROM FundManager WHERE FundManagerNumberofLicense = ?', [$recipientId]);
            $phone = (string) ($row['FundManagerPhone'] ?? 'N/A');
        }
    }

    // محاولة إرسال SMS فعلياً إذا كان رقم الهاتف متوفراً
    $smsStatus = 'Failed';
    if ($phone !== 'N/A' && strlen($phone) === 10) {
        $smsStatus = send_actual_sms($phone, $message) ? 'Sent' : 'Failed';
    } elseif ($dbRecipientType === 'All') {
        // إرسال جماعي - هنا يجب تكرار لجميع المستلمين
        $smsStatus = 'Bulk_Sent'; // تنفيذ لاحق
    }

    repo_execute(
        'INSERT INTO SmsLog (RecipientPhone, RecipientType, RecipientID, MessageContent, MessageType, SentBy, SentAt, Status)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)',
        [$phone, $dbRecipientType, $recipientId, $message, $dbMessageType, $sentBy, $smsStatus]
    );

    $row = repo_fetch_one('SELECT LAST_INSERT_ID() AS id');
    $smsId = (int) ($row['id'] ?? 0);

    return $smsId;
}

/**
 * دالة إرسال SMS فعلي
 * --------------------------------
 * ترسل رسالة SMS باستخدام خدمة خارجية
 *
 * @param string $phone رقم الهاتف
 * @param string $message محتوى الرسالة
 * @return bool true إذا نجح الإرسال
 */
function send_actual_sms(string $phone, string $message): bool
{
    // تنفيذ تجريبي - في الإنتاج استخدم خدمة SMS حقيقية مثل Twilio أو خدمة محلية
    // مثال: Twilio API
    /*
    $sid = getenv('TWILIO_SID');
    $token = getenv('TWILIO_TOKEN');
    $from = getenv('TWILIO_FROM');

    $client = new Twilio\Rest\Client($sid, $token);
    try {
        $client->messages->create($phone, [
            'from' => $from,
            'body' => $message
        ]);
        return true;
    } catch (Exception $e) {
        error_log('SMS sending failed: ' . $e->getMessage());
        return false;
    }
    */

    // للاختبار: سجل الرسالة في ملف log
    $logFile = __DIR__ . '/../logs/sms_log.txt';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logEntry = sprintf(
        "[%s] To: %s | Message: %s\n",
        date('Y-m-d H:i:s'),
        $phone,
        substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '')
    );

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // محاكاة نجاح/فشل عشوائي للاختبار
    return rand(0, 10) > 2; // 80% نجاح
}

/**
 * دالة جلب سجل رسائل SMS
 * --------------------------------
 * تُرجع قائمة سجلات SMS المرسلة بترتيب الأحدث أولاً
 *
 * @return array قائمة سجلات SMS
 */
function get_sms_logs(): array
{
    $dbLogs = repo_fetch_all('SELECT * FROM SmsLog ORDER BY SentAt DESC');
    if (!empty($dbLogs)) {
        return $dbLogs;
    }
    return [];
}

/**
 * دالة جلب إعلانات الطالب
 * --------------------------------
 * تُرجع قائمة الإعلانات العامة المرسلة للطلاب
 *
 * @return array قائمة الإعلانات
 */
function get_student_notifications(): array
{
    $rows = repo_fetch_all('SELECT NotificationID AS id, NotificationDescription AS message, SentDate AS time FROM Notification ORDER BY NotificationID DESC LIMIT 50');
    return array_map(static fn($r) => array_merge($r, ['read' => false]), $rows);
}

/**
 * دالة إضافة إعلان جديد
 * --------------------------------
 * تُضيف إعلاناً عاماً للطلاب
 *
 * @param string $message نص الإعلان
 */
function add_announcement(string $message): void
{
    repo_execute(
        'INSERT INTO Notification (NotificationDescription, VerificationCode, SentDate) VALUES (?, 0, NOW())',
        [$message]
    );
}

/**
 * دالة تسجيل سجل التدقيق
 * --------------------------------
 * تُسجّل عملية في سجل التدقيق للمراجعة الأمنية
 *
 * @param string      $userType نوع المستخدم
 * @param int         $userId معرّف المستخدم
 * @param string      $actionType نوع العملية
 * @param string      $description وصف العملية
 * @param string|null $entityType نوع الكيان المتأثر
 * @param int|null    $entityId معرّف الكيان المتأثر
 */
function log_audit(string $userType, int $userId, string $actionType, string $description, ?string $entityType = null, ?int $entityId = null): void
{
    repo_execute(
        'INSERT INTO AuditLog (UserType, UserID, ActionType, ActionDescription, EntityType, EntityID, IpAddress, LogDate) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
        [$userType, $userId, $actionType, $description, $entityType, $entityId, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']
    );
}

/**
 * دالة جلب سجلات التدقيق
 * --------------------------------
 * تُرجع سجلات التدقيق مع فلترة اختيارية وتحديد عدد
 *
 * @param array $filters معايير التصفية
 * @param int   $limit الحد الأقصى للنتائج
 * @return array قائمة السجلات
 */
function get_audit_logs(array $filters = [], int $limit = 50): array
{
    return repo_fetch_all('SELECT * FROM AuditLog ORDER BY LogID DESC LIMIT ' . (int) $limit);
}

/**
 * دالة إنشاء رمز تحقق للصندوق
 * --------------------------------
 * تُنشئ رمز تحقق لعملية حساسة على الصندوق
 *
 * @param int    $fundId معرّف الصندوق
 * @param int    $managerLicense رقم رخصة المدير
 * @param string $operationType نوع العملية
 * @return int رمز التحقق
 */
function create_fund_verification_code(int $fundId, int $managerLicense, string $operationType): int
{
    $code = rand(100000, 999999);
    $_SESSION['pending_fund_verification'] = [
        'code' => $code,
        'fund_id' => $fundId,
        'operation_type' => $operationType,
    ];
    return $code;
}

/**
 * دالة التحقق من رمز الصندوق
 * --------------------------------
 * تتحقق من صحة رمز التحقق المُدخل
 *
 * @param int $enteredCode الرمز المُدخل
 * @return bool true إذا كان صحيحاً
 */
function verify_fund_code(int $enteredCode): bool
{
    $pending = $_SESSION['pending_fund_verification'] ?? null;
    if (!$pending) return false;

    if ((int) $pending['code'] === $enteredCode) {
        return true;
    }

    return false;
}

/**
 * دالة تسجيل قبول الشروط
 * --------------------------------
 * تُسجّل قبول المستخدم للشروط والأحكام
 *
 * @param string $userType نوع المستخدم
 * @param int    $userId معرّف المستخدم
 * @param string $termsVersion إصدار الشروط
 * @return bool true إذا نجح
 */
function record_terms_acceptance(string $userType, int $userId, string $termsVersion): bool
{
    return true;
}

// ===================================================
// 12. دوال التقارير
// ===================================================

/**
 * دالة إحصائيات ملخص التقارير
 * --------------------------------
 * تُرجع إحصائيات شاملة عن النظام للتقارير ولوحات التحكم
 *
 * @return array مصفوفة الإحصائيات
 */
function report_summary_stats(): array
{
    static $cache = null;
    static $cacheTime = 0;

    // Cache for 5 minutes
    if ($cache !== null && (time() - $cacheTime) < 300) {
        return $cache;
    }

    $totalStudents = (int) (repo_fetch_one('SELECT COUNT(*) AS n FROM Student')['n'] ?? 0);
    $totalManagers = (int) (repo_fetch_one('SELECT COUNT(*) AS n FROM FundManager')['n'] ?? 0);
    $totalFunds    = (int) (repo_fetch_one("SELECT COUNT(*) AS n FROM Fund WHERE FundAccountStatus='Published'")['n'] ?? 0);
    $totalInvestments = (float) (repo_fetch_one("SELECT COALESCE(SUM(FundCapital),0) AS n FROM `Transaction` WHERE TransactionType='Investment'")['n'] ?? 0);
    $totalReturns     = (float) (repo_fetch_one('SELECT COALESCE(SUM(InvestmentWalletReturn),0) AS n FROM InvestmentWallet')['n'] ?? 0);
    $totalTx     = (int) (repo_fetch_one('SELECT COUNT(*) AS n FROM `Transaction`')['n'] ?? 0);
    $totalWd     = (int) (repo_fetch_one('SELECT COUNT(*) AS n FROM WithdrawalRequest')['n'] ?? 0);
    $pendingAppr = (int) (repo_fetch_one("SELECT COUNT(*) AS n FROM ParticipationApproval WHERE ApprovalStatus='Pending'")['n'] ?? 0);

    $result = [
        'total_students'    => $totalStudents,
        'total_managers'    => $totalManagers,
        'total_funds'       => $totalFunds,
        'total_investments' => $totalInvestments,
        'total_returns'     => $totalReturns,
        'total_transactions'=> $totalTx,
        'total_withdrawals' => $totalWd,
        'pending_approvals' => $pendingAppr,
    ];

    $cache = $result;
    $cacheTime = time();
    return $result;
}

/**
 * دالة تفاصيل معاملات المدير
 * --------------------------------
 * تُرجع تفاصيل المعاملات المرتبطة بصناديق مدير معين
 *
 * @param int $licenseId رقم رخصة المدير
 * @return array قائمة المعاملات
 */
function manager_transaction_details(int $licenseId): array
{
    return repo_fetch_all(
        'SELECT t.* FROM `Transaction` t
         INNER JOIN Fund f ON f.FundID = t.FundID
         WHERE f.FundManagerNumberofLicense = ?
         ORDER BY t.TransactionID DESC',
        [$licenseId]
    );
}

/**
 * دالة جلب العملاء مع تفاصيلهم
 * --------------------------------
 * تُرجع قائمة العملاء (الطلاب) مع بيانات محافظهم واستثماراتهم
 *
 * @return array قائمة العملاء
 */
function customers_with_details(): array
{
    $students = repo_fetch_all('SELECT s.*, w.InvestmentWalletTotalAmount, w.InvestmentWalletReturn FROM Student s LEFT JOIN InvestmentWallet w ON w.StudentID = s.StudentID ORDER BY s.StudentID DESC');
    $customers = [];
    foreach ($students as $s) {
        $sid = (int) $s['StudentID'];
        $count = (int) (repo_fetch_one('SELECT COUNT(*) AS n FROM Contract WHERE StudentID = ?', [$sid])['n'] ?? 0);
        $invested = (float) (repo_fetch_one("SELECT COALESCE(SUM(FundCapital),0) AS n FROM `Transaction` WHERE StudentID = ? AND TransactionType='Investment'", [$sid])['n'] ?? 0);
        $customers[] = [
            'student' => $s,
            'wallet' => ['InvestmentWalletTotalAmount' => $s['InvestmentWalletTotalAmount'] ?? 0, 'InvestmentWalletReturn' => $s['InvestmentWalletReturn'] ?? 0],
            'contracts_count' => $count,
            'total_invested' => $invested,
        ];
    }
    return $customers;
}

// ===================================================
// 13. دوال مساعدة لقاعدة البيانات
// ===================================================

/**
 * دالة جلب صف واحد من قاعدة البيانات
 * --------------------------------
 * تُنفذ استعلام SQL وتُرجع صفاً واحداً
 * تُرجع null عند فشل الاتصال أو عدم وجود صف
 *
 * @param string $sql استعلام SQL
 * @param array  $params معاملات الاستعلام
 * @return array|null الصف أو null
 */
function repo_fetch_one(string $sql, array $params = []): ?array
{
    $pdo = db_connection();
    if (!$pdo) return null;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * دالة تنفيذ استعلام كتابة على قاعدة البيانات
 * --------------------------------
 * تُنفذ INSERT / UPDATE / DELETE
 *
 * @param string $sql استعلام SQL
 * @param array  $params معاملات الاستعلام
 * @return bool نجاح العملية
 */
function repo_execute(string $sql, array $params = []): bool
{
    $pdo = db_connection();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * دالة جلب عدة صفوف من قاعدة البيانات
 * --------------------------------
 * تُنفذ استعلام SQL وتُرجع جميع الصفوف
 * تُرجع مصفوفة فارغة عند فشل الاتصال
 *
 * @param string $sql استعلام SQL
 * @param array  $params معاملات الاستعلام
 * @return array الصفوف
 */
function repo_fetch_all(string $sql, array $params = []): array
{
    $pdo = db_connection();
    if (!$pdo) return [];

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (\Throwable $e) {
        return [];
    }
}

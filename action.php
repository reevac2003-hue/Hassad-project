<?php
/**
 * ملف معالجة الإجراءات
 * ================================
 * يعالج جميع طلبات النماذج (POST)
 * 
 * الإجراءات المتاحة:
 * - register-student: تسجيل طالب جديد
 * - register-manager: تسجيل مدير صندوق جديد
 * - login: تسجيل الدخول
 * - subscribe: الاشتراك في صندوق
 * - cancel-subscription: إلغاء الاشتراك
 * - create-fund: إنشاء صندوق جديد
 * - publish-fund: نشر صندوق
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('home');
}

$parseExpiryToDate = static function (string $raw): string {
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m) === 1) {
        return sprintf('%s-%s-01', $m[1], $m[2]);
    }

    if (preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $value, $m) === 1) {
        return sprintf('20%s-%s-01', $m[2], $m[1]);
    }

    return '';
};

switch ($action) {
    case 'register-student':
        $password = trim((string) ($_POST['password'] ?? ''));
        $confirmPassword = trim((string) ($_POST['confirm_password'] ?? ''));
        if ($password === '' || $password !== $confirmPassword) {
            set_flash('error', 'Student passwords do not match.');
            redirect_to('register-student');
        }

        $hasUpper = preg_match('/[A-Z]/', $password) === 1;
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;
        $hasDigit = preg_match('/\d/', $password) === 1;
        $hasLength = strlen($password) >= 8;
        if (!($hasUpper && $hasSpecial && $hasDigit && $hasLength)) {
            set_flash('error', 'Password must be at least 8 characters and include uppercase, number, and special character.');
            redirect_to('register-student');
        }

        $phoneDigits = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
        $iban = strtoupper(str_replace(' ', '', trim((string) ($_POST['iban'] ?? ''))));
        $cardNumber = preg_replace('/\D+/', '', (string) ($_POST['card_number'] ?? ''));
        $cvv = preg_replace('/\D+/', '', (string) ($_POST['cvv'] ?? ''));
        $expiryRaw = trim((string) ($_POST['expiry'] ?? ''));
        $expiryDate = $parseExpiryToDate($expiryRaw);

        if (!preg_match('/^\d{10}$/', $phoneDigits)) {
            set_flash('error', 'Phone number must be exactly 10 digits.');
            redirect_to('register-student');
        }
        if (!preg_match('/^SA\d{22}$/', $iban)) {
            set_flash('error', 'IBAN is required and must be in format SA followed by 22 digits.');
            redirect_to('register-student');
        }
        if (!preg_match('/^\d{14}$/', $cardNumber)) {
            set_flash('error', 'Card number must be exactly 14 digits.');
            redirect_to('register-student');
        }
        if (!preg_match('/^\d{3}$/', $cvv)) {
            set_flash('error', 'CVV must be exactly 3 digits.');
            redirect_to('register-student');
        }
        if ($expiryDate === '') {
            set_flash('error', 'Expiry date is required and must be a valid date.');
            redirect_to('register-student');
        }

        register_student([
            'first_name' => (string) ($_POST['first_name'] ?? ''),
            'last_name' => (string) ($_POST['last_name'] ?? ''),
            'student_id' => (string) ($_POST['student_id'] ?? ''),
            'phone' => $phoneDigits,
            'campus' => (string) ($_POST['campus'] ?? ''),
            'degree' => (string) ($_POST['degree'] ?? ''),
            'major' => (string) ($_POST['major'] ?? ''),
            'iban' => $iban,
            'card_number' => $cardNumber,
            'card_name' => (string) ($_POST['card_name'] ?? ''),
            'expiry' => $expiryDate,
            'cvv' => $cvv,
            'password' => $password,
        ]);
        set_flash('success', 'Student account created successfully.');
        redirect_to('account-verified');
        break;

    case 'register-manager':
        $password = trim((string) ($_POST['password'] ?? ''));
        $confirmPassword = trim((string) ($_POST['confirm_password'] ?? ''));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $license = trim((string) ($_POST['license'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        
        // تحقق من عدم ترك حقول مهمة فارغة
        if ($firstName === '' || $lastName === '' || $license === '' || $phone === '' || $password === '') {
            set_flash('error', 'All fields are required.');
            redirect_to('register-manager');
        }
        
        // تحقق من تطابق كلمات المرور
        if ($password !== $confirmPassword) {
            set_flash('error', 'Fund manager passwords do not match.');
            redirect_to('register-manager');
        }
        
        // تحقق من متطلبات كلمة المرور
        $hasUpper = preg_match('/[A-Z]/', $password) === 1;
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;
        $hasDigit = preg_match('/\d/', $password) === 1;
        $hasLength = strlen($password) >= 8;
        if (!($hasUpper && $hasSpecial && $hasDigit && $hasLength)) {
            set_flash('error', 'Password must be at least 8 characters and include uppercase letter, number, and special character.');
            redirect_to('register-manager');
        }
        
        // تحقق من صيغة رقم الهاتف
        $phoneDigits = preg_replace('/\D+/', '', $phone);
        if (!preg_match('/^\d{10}$/', $phoneDigits)) {
            set_flash('error', 'Phone number must be exactly 10 digits.');
            redirect_to('register-manager');
        }
        
        // تحقق من صيغة رخصة المدير
        if (!preg_match('/^\d+$/', $license)) {
            set_flash('error', 'License number must contain only digits.');
            redirect_to('register-manager');
        }
        
        $created = register_manager([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'license' => $license,
            'phone' => $phone,
            'password' => $password,
            'contract_name' => (string) ($_POST['contract_name'] ?? 'manager-contract.pdf'),
        ]);

        if ($created) {
            set_flash('success', 'Fund manager account created successfully.');
            redirect_to('account-verified');
        }
        break;

    case 'login':
        $role = trim((string) ($_POST['role'] ?? 'auto'));
        $identity = trim((string) ($_POST['identity'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));

        $loggedInRole = $role === 'auto'
            ? attempt_login_auto($identity, $password)
            : (attempt_login($role, $identity, $password) ? $role : false);

        if ($loggedInRole === false) {
            set_flash('error', 'Invalid login credentials.');
            redirect_to('login');
        }

        set_flash('success', 'Login successful!');
        if ($loggedInRole === 'manager') {
            redirect_to('manager-dashboard');
        } elseif ($loggedInRole === 'admin') {
            redirect_to('admin-dashboard');
        } elseif ($loggedInRole === 'jic') {
            redirect_to('jic-participants');
        }
        redirect_to('student-dashboard');
        break;

    case 'topup-wallet':
        if (!is_logged_in_as_student()) {
            set_flash('error', 'Access denied. Student login required.');
            redirect_to('login', ['role' => 'student']);
        }
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            set_flash('error', 'Top-up amount must be greater than 0 SAR.');
            redirect_to('wallet');
        }
        $sid = (int) current_student()['StudentID'];
        $walletRow = repo_fetch_one('SELECT InvestmentWalletID FROM InvestmentWallet WHERE StudentID = ?', [$sid]);
        if ($walletRow) {
            repo_execute(
                'UPDATE InvestmentWallet
                 SET InvestmentWalletTotalAmount = COALESCE(InvestmentWalletTotalAmount, 0) + ?,
                     InvestmentWalletCredit = COALESCE(InvestmentWalletCredit, 0) + ?
                 WHERE StudentID = ?',
                [$amount, $amount, $sid]
            );
        } else {
            repo_execute(
                'INSERT INTO InvestmentWallet (InvestmentWalletID, InvestmentWalletTotalAmount, InvestmentWalletReturn, InvestmentWalletCredit, StudentID)
                 VALUES (?, ?, 0, ?, ?)',
                [$sid, $amount, $amount, $sid]
            );
        }
        set_flash('success', 'SAR ' . number_format($amount, 2) . ' added to your wallet successfully!');
        redirect_to('wallet');
        break;

    case 'subscribe':
        if (!is_logged_in_as_student()) {
            set_flash('error', 'Access denied. Student login required.');
            redirect_to('login', ['role' => 'student']);
        }
        $student = current_student();
        $studentId = (int) ($student['StudentID'] ?? 0);
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $fund = fund_by_id($fundId);
        if (!$fund) {
            set_flash('error', 'Fund not found.');
            redirect_to('student-dashboard');
        }
        if (strtolower((string) ($fund['FundAccountStatus'] ?? 'draft')) !== 'published') {
            set_flash('error', 'This fund is not available for subscription right now.');
            redirect_to('fund', ['slug' => (string) ($fund['slug'] ?? '')]);
        }
        if ($studentId <= 0) {
            set_flash('error', 'Unable to identify student account. Please sign in again.');
            redirect_to('login', ['role' => 'student']);
        }

        // Participation approvals are not used for subscriptions anymore.
        repo_execute('DELETE FROM ParticipationApproval WHERE StudentID = ? AND FundID = ?', [$studentId, $fundId]);

        if (student_has_contract($studentId, $fundId)) {
            set_flash('error', 'You already have an active subscription to this fund.');
            redirect_to('fund', ['slug' => (string) ($fund['slug'] ?? '')]);
        }
        if (!isset($_POST['terms'])) {
            set_flash('error', 'You need to accept the terms and conditions.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }

        if (!isset($_POST['contract_accepted']) || (string) $_POST['contract_accepted'] !== '1') {
            set_flash('error', 'You need to accept the subscription contract.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }

        $minAmount = (float) ($fund['FundAmountMinimum'] ?? 0);
        $maxAmount = (float) ($fund['FundAmountMaximum'] ?? 0);
        if ($amount < $minAmount || $amount > $maxAmount) {
            set_flash('error', 'Subscription amount must be between ' . number_format($minAmount, 2) . ' and ' . number_format($maxAmount, 2) . ' SAR.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }

        $availableCards = bank_cards_for_student($studentId);
        if (empty($availableCards)) {
            set_flash('error', 'Please add your linked card from Account Settings before subscribing.');
            redirect_to('edit-profile');
        }

        $selectedCardNumber = preg_replace('/\D+/', '', (string) ($_POST['selected_card'] ?? ''));
        $selectedCard = $selectedCardNumber !== '' ? find_student_bank_card($studentId, $selectedCardNumber) : null;

        $phoneDigits = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
        if (!preg_match('/^\d{10}$/', $phoneDigits)) {
            set_flash('error', 'Phone number must be exactly 10 digits.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }

        $iban = strtoupper(str_replace(' ', '', trim((string) ($_POST['iban'] ?? ''))));
        if ($iban !== '' && !preg_match('/^SA\d{22}$/', $iban)) {
            set_flash('error', 'IBAN format is invalid. Use SA followed by 22 digits, or leave it empty.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }

        $cvv = preg_replace('/\D+/', '', (string) ($_POST['cvv'] ?? ''));

        if ($cvv === '' || !preg_match('/^\d{3}$/', $cvv)) {
            set_flash('error', 'Please enter a valid 3-digit card CVV to confirm payment.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }

        if ($selectedCard === null) {
            set_flash('error', 'No linked card was selected. Please add or select your linked card from Account Settings.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }

        $storedCvv = preg_replace('/\D+/', '', (string) ($selectedCard['CreditCardCVV'] ?? ''));
        if (!preg_match('/^\d{3}$/', $storedCvv)) {
            set_flash('error', 'Your linked card CVV is missing or invalid. Please update your card from Account Settings.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }

        if (!hash_equals($storedCvv, $cvv)) {
            set_flash('error', 'The entered CVV does not match your linked wallet card.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }

        if (!save_student_bank_card($studentId, [
            'card_number' => (string) ($selectedCard['CreditCardNumber'] ?? ''),
            'card_name' => (string) ($selectedCard['CreditCardName'] ?? ''),
            'expiry_date' => (string) ($selectedCard['CreditCardDayExpired'] ?? ''),
            'cvv' => (int) $storedCvv,
            'iban' => $iban,
        ])) {
            set_flash('error', 'Unable to validate your linked card in the database. Please update card details from Account Settings.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }

        $bank = $selectedCard;

        if (!subscribe_to_fund($studentId, $fundId, [
            'amount' => $amount,
            'phone' => $phoneDigits,
            'card_number' => (string) ($bank['CreditCardNumber'] ?? ''),
            'card_name' => (string) ($bank['CreditCardName'] ?? ''),
            'expiry' => (string) ($bank['CreditCardDayExpired'] ?? ''),
            'cvv' => $cvv,
        ])) {
            $reason = trim((string) ($_SESSION['subscription_error'] ?? ''));
            unset($_SESSION['subscription_error']);
            set_flash('error', $reason !== '' ? $reason : 'Unable to complete subscription. Please verify amount and card details, then try again.');
            redirect_to('subscribe', ['slug' => $fund['slug']]);
        }
        set_flash('success', 'Subscription confirmed and charged to your selected card successfully.');
        redirect_to('fund', ['slug' => $fund['slug']]);
        break;

    case 'cancel-subscription':
        // التحقق من صلاحية الطالب
        if (!is_logged_in_as_student()) {
            set_flash('error', 'Access denied. Student login required.');
            redirect_to('login', ['role' => 'student']);
        }
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        $fund = fund_by_id($fundId);
        if (!$fund) {
            set_flash('error', 'Fund not found.');
            redirect_to('student-dashboard');
        }
        // Holding period guard
        $holdMonths = (int) ($fund['HoldingPeriod'] ?? 0);
        $startTs    = strtotime($fund['FundDateStart'] ?? 'now');
        $unlockTs   = strtotime("+{$holdMonths} months", $startTs);
        if ($holdMonths > 0 && time() < $unlockTs) {
            $unlockDate = date('d M Y', $unlockTs);
            set_flash('error', "You cannot cancel this subscription until the holding period ends on {$unlockDate}.");
            redirect_to('fund', ['slug' => $fund['slug'] ?? '']);
        }
        cancel_fund_subscription((int) current_student()['StudentID'], $fundId);
        set_flash('success', 'Subscription cancelled successfully.');
        redirect_to('operation-result', ['op' => 'cancel-subscription', 'slug' => (string) ($fund['slug'] ?? '')]);
        break;

    case 'create-fund':
        // التحقق من صلاحية المدير
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied. Manager login required.');
            redirect_to('login', ['role' => 'manager']);
        }
        $minAmount     = (float) ($_POST['min_amount']     ?? 0);
        $maxAmount     = (float) ($_POST['max_amount']     ?? 0);
        $holdingPeriod = (int)   ($_POST['holding_period'] ?? 6);

        // Load system limits
        $sysMin = (float) (get_system_setting('min_subscription') ?? 100);
        $sysMax = (float) (get_system_setting('max_subscription') ?? 100000);

        if ($maxAmount <= $minAmount) {
            set_flash('error', 'Maximum investment must be greater than minimum investment.');
            redirect_to('create-fund');
        }
        if ($minAmount < $sysMin) {
            set_flash('error', sprintf(
                'Minimum investment cannot be less than the platform minimum of %s SAR.',
                number_format($sysMin, 0)
            ));
            redirect_to('create-fund');
        }
        if ($maxAmount > $sysMax) {
            set_flash('error', sprintf(
                'Maximum investment cannot exceed the platform maximum of %s SAR.',
                number_format($sysMax, 0)
            ));
            redirect_to('create-fund');
        }
        $fundId = create_fund_draft((int) current_manager()['FundManagerNumberofLicense'], [
            'title' => (string) ($_POST['title'] ?? ''),
            'investment_objective' => (string) ($_POST['investment_objective'] ?? 'Capital Growth'),
            'investment_type' => (string) ($_POST['investment_type'] ?? 'Equities (Saudi Stocks – Saudi Exchange)'),
            'risk_level' => (string) ($_POST['risk_level'] ?? 'medium'),
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'duration' => $holdingPeriod,
            'holding_period' => $holdingPeriod,
            'expected_return' => (float) ($_POST['expected_return'] ?? 7),
            'return_policy' => (string) ($_POST['return_policy'] ?? 'Annual'),
            'description' => (string) ($_POST['description'] ?? ''),
            'highlight' => (string) ($_POST['highlight'] ?? ''),
        ]);
        if ($fundId <= 0) {
            set_flash('error', 'Unable to save the fund to the database. Please try again.');
            redirect_to('create-fund');
        }
        set_flash('success', 'Draft fund saved. Review it before publishing.');
        redirect_to('publish-fund', ['fund_id' => $fundId]);
        break;

    case 'publish-fund':
        // التحقق من صلاحية المدير
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied. Manager login required.');
            redirect_to('login', ['role' => 'manager']);
        }
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        publish_fund($fundId);
        set_flash('success', 'Fund submitted for admin approval.');
        redirect_to('manager-dashboard');
        break;

    case 'admin-approve-fund':
        if (!is_logged_in_as_admin()) {
            set_flash('error', 'Access denied. Admin login required.');
            redirect_to('login', ['role' => 'admin']);
        }
        $fundId   = (int) ($_POST['fund_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        if ($fundId > 0) {
            if ($decision === 'approve') {
                sync_fund_status($fundId, 'Published');
                repo_execute(
                    'INSERT INTO Notification (NotificationDescription, VerificationCode, SentDate, FundID) VALUES (?, 0, NOW(), ?)',
                    ['Fund approved and published: Fund #' . $fundId, $fundId]
                );
                set_flash('success', 'Fund approved and published successfully.');
            } elseif ($decision === 'reject') {
                sync_fund_status($fundId, 'Draft');
                repo_execute(
                    'INSERT INTO Notification (NotificationDescription, VerificationCode, SentDate, FundID) VALUES (?, 0, NOW(), ?)',
                    ['Fund rejected by admin: Fund #' . $fundId, $fundId]
                );
                set_flash('success', 'Fund rejected and returned to draft.');
            }
        }
        redirect_to('admin-dashboard', ['tab' => 'fund-approvals']);
        break;

    case 'undo-fund':
        // التحقق من صلاحية المدير
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied. Manager login required.');
            redirect_to('login', ['role' => 'manager']);
        }
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        undo_fund_draft($fundId);
        set_flash('success', 'Draft fund removed successfully.');
        redirect_to('operation-result', ['op' => 'undo-fund']);
        break;

    case 'save-fund-draft':
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied. Manager login required.');
            redirect_to('login', ['role' => 'manager']);
        }
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        if ($fundId <= 0) {
            set_flash('error', 'Fund not found.');
            redirect_to('manager-dashboard');
        }
        set_fund_draft($fundId);
        set_flash('success', 'Fund saved as draft.');
        redirect_to('manager-dashboard');
        break;

    case 'update-profile':
        $userType = (string) ($_POST['user_type'] ?? 'student');
        
        if ($userType === 'student' && is_logged_in_as_student()) {
            $studentId = (int) current_student()['StudentID'];
            repo_execute(
                'UPDATE Student
                 SET StudentNameFirst = ?, StudentNameLast = ?, StudentPhoneNumber = ?, StudentCampus = ?, StudentAcademicDegree = ?, StudentMajor = ?
                 WHERE StudentID = ?',
                [
                    trim((string) ($_POST['first_name'] ?? '')),
                    trim((string) ($_POST['last_name'] ?? '')),
                    trim((string) ($_POST['phone'] ?? '')),
                    trim((string) ($_POST['campus'] ?? '')),
                    trim((string) ($_POST['degree'] ?? '')),
                    trim((string) ($_POST['major'] ?? '')),
                    $studentId,
                ]
            );
            set_flash('success', 'Profile updated successfully.');
            redirect_to('edit-profile');
        } elseif ($userType === 'manager' && is_logged_in_as_manager()) {
            $license = (int) current_manager()['FundManagerNumberofLicense'];

            // تحقق إضافي من صحة الجلسة
            if ($license === 0) {
                set_flash('error', 'Session expired. Please login again.');
                redirect_to('login', ['role' => 'manager']);
            }

            // تشخيص: تحقق من صحة البيانات
            error_log("Manager update attempt - License: $license, FirstName: " . ($_POST['first_name'] ?? 'NULL') . ", LastName: " . ($_POST['last_name'] ?? 'NULL') . ", Phone: " . ($_POST['phone'] ?? 'NULL'));

            $updateResult = repo_execute(
                'UPDATE FundManager
                 SET FundManagerNameFirst = ?, FundManagerNameLast = ?, FundManagerPhone = ?
                 WHERE FundManagerNumberofLicense = ?',
                [
                    trim((string) ($_POST['first_name'] ?? '')),
                    trim((string) ($_POST['last_name'] ?? '')),
                    trim((string) ($_POST['phone'] ?? '')),
                    $license,
                ]
            );

            if ($updateResult) {
                set_flash('success', 'Profile updated successfully.');
                error_log("Manager profile updated successfully for license: $license");
            } else {
                set_flash('error', 'Failed to update profile. Please try again.');
                error_log("Manager profile update failed for license: $license");
            }
            redirect_to('edit-profile');
        }
        
        set_flash('error', 'Access denied.');
        redirect_to('login');
        break;

    case 'update-bank':
        if (!is_logged_in_as_student()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $studentId = (int) current_student()['StudentID'];

        $bank = current_bank_account($studentId);
        $iban = trim((string) ($_POST['iban'] ?? ''));
        $cardName = trim((string) ($_POST['card_name'] ?? ''));
        $cardNumber = preg_replace('/\D+/', '', (string) ($_POST['card_number'] ?? ''));
        $expiryRaw = trim((string) ($_POST['expiry'] ?? ''));
        $expiryInputDate = $parseExpiryToDate($expiryRaw);
        $cvvInput = preg_replace('/\D+/', '', (string) ($_POST['cvv'] ?? ''));

        $iban = strtoupper(str_replace(' ', '', $iban));
        if ($iban === '' || !preg_match('/^SA\d{22}$/', $iban)) {
            set_flash('error', 'IBAN is required and must be in format SA followed by 22 digits.');
            redirect_to('edit-profile');
        }

        if ($cardName === '') {
            set_flash('error', 'Card holder name is required.');
            redirect_to('edit-profile');
        }

        if (!preg_match('/^[\p{L}\s]{2,100}$/u', $cardName)) {
            set_flash('error', 'Card holder name must contain letters only.');
            redirect_to('edit-profile');
        }

        if (!preg_match('/^\d{14}$/', $cardNumber)) {
            set_flash('error', 'Card number is required and must be exactly 14 digits.');
            redirect_to('edit-profile');
        }

        if ($expiryRaw === '' || $expiryInputDate === '') {
            set_flash('error', 'Card expiry date is required and must be valid.');
            redirect_to('edit-profile');
        }

        if (!preg_match('/^\d{3}$/', $cvvInput)) {
            set_flash('error', 'CVV is required and must be exactly 3 digits.');
            redirect_to('edit-profile');
        }

        $effectiveCardNumber = $cardNumber;
        $effectiveExpiryDate = $expiryInputDate;
        $effectiveCvv = (int) $cvvInput;

        if ($effectiveCardNumber !== '' && $effectiveCvv <= 0) {
            set_flash('error', 'CVV is required when a linked card exists.');
            redirect_to('edit-profile');
        }

        if ($effectiveCardNumber !== '' && $effectiveExpiryDate === '') {
            set_flash('error', 'Card expiry date is required when a linked card exists.');
            redirect_to('edit-profile');
        }

        $saved = save_student_bank_card($studentId, [
            'iban' => $iban,
            'card_name' => $cardName,
            'card_number' => $effectiveCardNumber,
            'cvv' => $effectiveCvv,
            'expiry_date' => $effectiveExpiryDate,
            'verification_code' => (int) ($bank['VerificationCode'] ?? 0),
            'postal_code' => (string) ($bank['PostalCode'] ?? ''),
        ]);

        if (!$saved) {
            set_flash('error', 'Unable to save card details. Ensure the card number is unique and all required fields are valid.');
            redirect_to('edit-profile');
        }

        set_flash('success', 'Bank information updated successfully.');
        redirect_to('edit-profile');
        break;

    case 'change-password':
        $userType = (string) ($_POST['user_type'] ?? 'student');
        $currentPassword = trim((string) ($_POST['current_password'] ?? ''));
        $newPassword = trim((string) ($_POST['new_password'] ?? ''));
        $confirmPassword = trim((string) ($_POST['confirm_password'] ?? ''));
        
        if ($newPassword !== $confirmPassword) {
            set_flash('error', 'New passwords do not match.');
            redirect_to('edit-profile');
        }
        
        if (strlen($newPassword) < 8) {
            set_flash('error', 'Password must be at least 8 characters.');
            redirect_to('edit-profile');
        }
        
        if ($userType === 'student' && is_logged_in_as_student()) {
            $studentId = (int) current_student()['StudentID'];
            $row = repo_fetch_one('SELECT StudentPassword FROM Student WHERE StudentID = ?', [$studentId]);
            if (!$row || (string) ($row['StudentPassword'] ?? '') !== $currentPassword) {
                set_flash('error', 'Current password is incorrect.');
                redirect_to('edit-profile');
            }
            repo_execute('UPDATE Student SET StudentPassword = ? WHERE StudentID = ?', [$newPassword, $studentId]);
            set_flash('success', 'Password changed successfully.');
        } elseif ($userType === 'manager' && is_logged_in_as_manager()) {
            $license = (int) current_manager()['FundManagerNumberofLicense'];
            $row = repo_fetch_one('SELECT FundManagerPassword FROM FundManager WHERE FundManagerNumberofLicense = ?', [$license]);
            if (!$row || (string) ($row['FundManagerPassword'] ?? '') !== $currentPassword) {
                set_flash('error', 'Current password is incorrect.');
                redirect_to('edit-profile');
            }
            repo_execute('UPDATE FundManager SET FundManagerPassword = ? WHERE FundManagerNumberofLicense = ?', [$newPassword, $license]);
            set_flash('success', 'Password changed successfully.');
        }
        redirect_to('edit-profile');
        break;

    case 'delete-account':
        $userType = (string) ($_POST['user_type'] ?? 'student');
        
        if ($userType === 'student' && is_logged_in_as_student()) {
            $studentId = (int) current_student()['StudentID'];

            // Block deletion if student has active subscriptions
            $activeContracts = repo_fetch_one('SELECT COUNT(*) as cnt FROM Contract WHERE StudentID = ?', [$studentId]);
            if ((int)($activeContracts['cnt'] ?? 0) > 0) {
                set_flash('error', 'You have active fund subscriptions. Please cancel all subscriptions before deleting your account.');
                redirect_to('student-dashboard');
            }

            repo_execute('DELETE FROM StudentNotification WHERE StudentID = ?', [$studentId]);
            repo_execute('DELETE FROM ParticipationApproval WHERE StudentID = ?', [$studentId]);
            repo_execute('DELETE FROM Contract WHERE StudentID = ?', [$studentId]);
            repo_execute('DELETE FROM `Transaction` WHERE StudentID = ?', [$studentId]);
            repo_execute('DELETE FROM WithdrawalRequest WHERE StudentID = ?', [$studentId]);
            repo_execute('DELETE FROM InvestmentWallet WHERE StudentID = ?', [$studentId]);
            repo_execute('DELETE FROM BankAccount WHERE StudentID = ?', [$studentId]);
            repo_execute('DELETE FROM Student WHERE StudentID = ?', [$studentId]);
            
            set_flash('success', 'Your account has been deleted successfully.');
            redirect_to('home');
        } elseif ($userType === 'manager' && is_logged_in_as_manager()) {
            $license = (int) current_manager()['FundManagerNumberofLicense'];

            repo_execute('DELETE FROM FundManagerNotification WHERE FundManagerNumberofLicense = ?', [$license]);
            repo_execute('DELETE FROM FundVerification WHERE FundManagerLicense = ?', [$license]);
            repo_execute('DELETE pa FROM ParticipationApproval pa INNER JOIN Fund f ON f.FundID = pa.FundID WHERE f.FundManagerNumberofLicense = ?', [$license]);
            repo_execute('DELETE FROM `Transaction` WHERE FundManagerNumberofLicense = ?', [$license]);
            repo_execute('DELETE FROM Fund WHERE FundManagerNumberofLicense = ?', [$license]);
            repo_execute('DELETE FROM FundManager WHERE FundManagerNumberofLicense = ?', [$license]);
            
            set_flash('success', 'Your account has been deleted successfully.');
            redirect_to('home');
        }
        
        set_flash('error', 'Access denied.');
        redirect_to('login');
        break;

    // =============================================
    // إجراءات حظر المستخدمين - User Ban Actions
    // =============================================
    
    case 'ban-user':
        // التحقق من صلاحية المدير أو الأدمن أو JIC
        if (!is_logged_in_as_manager() && !is_logged_in_as_admin() && !is_logged_in_as_jic()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $targetUserType = (string) ($_POST['target_user_type'] ?? 'Student');
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $banReason = trim((string) ($_POST['ban_reason'] ?? ''));
        $returnTab = (string) ($_POST['return_tab'] ?? 'bans');
        if (!in_array($returnTab, ['users', 'bans'], true)) {
            $returnTab = 'bans';
        }
        
        // Determine who is banning
        $bannedBy = 0;
        $bannedByType = 'Admin';
        if (is_logged_in_as_manager()) {
            $bannedBy = (int) current_manager()['FundManagerNumberofLicense'];
            $bannedByType = 'FundManager';
        } elseif (is_logged_in_as_admin()) {
            $bannedBy = (int) current_admin()['AdminID'];
            $bannedByType = 'Admin';
        } elseif (is_logged_in_as_jic()) {
            $bannedBy = (int) current_jic()['JicSponsorID'];
            $bannedByType = 'JIC';
        }
        
        if (ban_user($targetUserType, $targetUserId, $banReason, $bannedBy, $bannedByType)) {
            log_audit($bannedByType, $bannedBy, 'BAN_USER', "Banned $targetUserType ID: $targetUserId. Reason: $banReason", $targetUserType, $targetUserId);
            set_flash('success', 'User has been banned successfully.');
        } else {
            // تحقق من سبب الفشل
            if ($bannedByType === 'FundManager' && $targetUserType === 'FundManager') {
                set_flash('error', 'Managers cannot ban other managers. Only admins can perform this action.');
            } else {
                set_flash('error', 'Failed to ban user. User may already be banned.');
            }
        }
        redirect_to('admin-dashboard', ['tab' => $returnTab]);
        break;

    case 'unban-user':
        if (!is_logged_in_as_manager() && !is_logged_in_as_admin() && !is_logged_in_as_jic()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $targetUserType = (string) ($_POST['target_user_type'] ?? 'Student');
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $returnTab = (string) ($_POST['return_tab'] ?? 'bans');
        if (!in_array($returnTab, ['users', 'bans'], true)) {
            $returnTab = 'bans';
        }
        
        if (unban_user($targetUserType, $targetUserId)) {
            set_flash('success', 'User ban has been lifted successfully.');
        } else {
            set_flash('error', 'Failed to unban user.');
        }
        redirect_to('admin-dashboard', ['tab' => $returnTab]);
        break;

    case 'self-ban':
        if (!is_logged_in_as_student()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $studentId = (int) current_student()['StudentID'];
        $reason = trim((string) ($_POST['reason'] ?? 'Self-requested account suspension'));
        
        if (ban_user('Student', $studentId, $reason, $studentId, 'Self')) {
            log_audit('Student', $studentId, 'SELF_BAN', "Student self-banned their account. Reason: $reason", 'Student', $studentId);
            unset($_SESSION['auth']);
            set_flash('success', 'Your account has been suspended. Contact support to reactivate.');
            redirect_to('home');
        } else {
            set_flash('error', 'Failed to suspend account.');
            redirect_to('student-dashboard');
        }
        break;

    // =============================================
    // إجراءات سحب الأرباح - Withdrawal Actions
    // =============================================
    
    case 'withdraw-profit':
        if (!is_logged_in_as_student()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $studentId = (int) current_student()['StudentID'];
        $amount = (float) ($_POST['amount'] ?? 0);
        $withdrawalType = (string) ($_POST['withdrawal_type'] ?? 'Profit');
        $fundId = !empty($_POST['fund_id']) ? (int) $_POST['fund_id'] : null;
        
        $wallet = current_wallet($studentId);
        $availableReturn = (float) $wallet['InvestmentWalletReturn'];
        
        if ($amount <= 0) {
            set_flash('error', 'Please enter a valid amount.');
            redirect_to('wallet');
        }
        
        if ($withdrawalType === 'Profit' && $amount > $availableReturn) {
            set_flash('error', 'Insufficient profit balance. Available: SAR ' . number_format($availableReturn, 2));
            redirect_to('wallet');
        }
        
        $withdrawalId = create_withdrawal_request($studentId, $amount, $withdrawalType, $fundId);
        log_audit('Student', $studentId, 'WITHDRAWAL_REQUEST', "Requested withdrawal of SAR $amount ($withdrawalType)", 'WithdrawalRequest', $withdrawalId);
        set_flash('success', 'Withdrawal request submitted successfully. You will be notified once processed.');
        redirect_to('wallet');
        break;

    case 'process-withdrawal':
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $withdrawalId = (int) ($_POST['withdrawal_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'Approved');
        $rejectionReason = trim((string) ($_POST['rejection_reason'] ?? ''));
        
        $processedBy = 0;
        if (is_logged_in_as_manager()) {
            $processedBy = (int) current_manager()['FundManagerNumberofLicense'];
        }
        
        if (process_withdrawal($withdrawalId, $status, $processedBy, $rejectionReason ?: null)) {
            set_flash('success', "Withdrawal request has been $status.");
        } else {
            set_flash('error', 'Failed to process withdrawal request.');
        }
        redirect_to('manager-dashboard');
        break;

    // =============================================
    // إجراءات تحديث الصندوق - Fund Update Actions
    // =============================================
    
    case 'update-fund':
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        $fund = fund_by_id($fundId);
        
        if (!$fund) {
            set_flash('error', 'Fund not found.');
            redirect_to('manager-dashboard');
        }
        
        // Check if manager owns this fund
        $manager = current_manager();
        if ((int)$fund['FundManagerNumberofLicense'] !== (int)$manager['FundManagerNumberofLicense']) {
            set_flash('error', 'You do not have permission to edit this fund.');
            redirect_to('manager-dashboard');
        }
        
        $subscriptionStart = trim((string) ($_POST['subscription_start'] ?? ''));
        $subscriptionEnd = trim((string) ($_POST['subscription_end'] ?? ''));
        $fundStart = trim((string) ($_POST['fund_start'] ?? ''));
        $fundEnd = trim((string) ($_POST['fund_end'] ?? ''));

        $updMinAmount = (float) ($_POST['min_amount'] ?? 0);
        $updMaxAmount = (float) ($_POST['max_amount'] ?? 0);

        if ($updMinAmount > 0 || $updMaxAmount > 0) {
            $sysMin = (float) (get_system_setting('min_subscription') ?? 100);
            $sysMax = (float) (get_system_setting('max_subscription') ?? 100000);

            if ($updMaxAmount > 0 && $updMinAmount > 0 && $updMaxAmount <= $updMinAmount) {
                set_flash('error', 'Maximum investment must be greater than minimum investment.');
                redirect_to('edit-fund', ['id' => $fundId]);
            }
            if ($updMinAmount > 0 && $updMinAmount < $sysMin) {
                set_flash('error', sprintf('Minimum investment cannot be less than the platform minimum of %s SAR.', number_format($sysMin, 0)));
                redirect_to('edit-fund', ['id' => $fundId]);
            }
            if ($updMaxAmount > 0 && $updMaxAmount > $sysMax) {
                set_flash('error', sprintf('Maximum investment cannot exceed the platform maximum of %s SAR.', number_format($sysMax, 0)));
                redirect_to('edit-fund', ['id' => $fundId]);
            }
        }

        $updateData = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'min_amount' => $updMinAmount,
            'max_amount' => $updMaxAmount,
            'expected_return' => (float) ($_POST['expected_return'] ?? 0),
            'risk_level' => (string) ($_POST['risk_level'] ?? ''),
            'holding_period' => (int) ($_POST['holding_period'] ?? 0),
            'return_policy' => (string) ($_POST['return_policy'] ?? ''),
            'fund_start_date' => $fundStart !== '' ? $fundStart : $subscriptionStart,
            'fund_end_date' => $fundEnd !== '' ? $fundEnd : $subscriptionEnd,
            'terms' => trim((string) ($_POST['terms'] ?? '')),
        ];
        
        // Process highlights
        if (!empty($_POST['highlight'])) {
            $updateData['highlights'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim($_POST['highlight'])) ?: [])));
        }
        
        // Remove empty values
        $updateData = array_filter($updateData, fn($v) => $v !== '' && $v !== 0 && $v !== 0.0);
        
        if (update_fund($fundId, $updateData)) {
            log_audit('FundManager', (int)$manager['FundManagerNumberofLicense'], 'UPDATE_FUND', "Updated fund: {$fund['FundTitle']}", 'Fund', $fundId);
            set_flash('success', 'Fund updated successfully.');
        } else {
            set_flash('error', 'Failed to update fund.');
        }
        redirect_to('edit-fund', ['id' => $fundId]);
        break;

    case 'hide-fund':
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        $fund = fund_by_id($fundId);
        
        if (!$fund) {
            set_flash('error', 'Fund not found.');
            redirect_to('manager-dashboard');
        }
        
        $manager = current_manager();
        if ((int)$fund['FundManagerNumberofLicense'] !== (int)$manager['FundManagerNumberofLicense']) {
            set_flash('error', 'You do not have permission to hide this fund.');
            redirect_to('manager-dashboard');
        }
        
        if (hide_fund($fundId)) {
            log_audit('FundManager', (int)$manager['FundManagerNumberofLicense'], 'HIDE_FUND', "Hidden fund: {$fund['FundTitle']}", 'Fund', $fundId);
            set_flash('success', 'Fund has been hidden from students.');
        } else {
            set_flash('error', 'Failed to hide fund.');
        }
        redirect_to('manager-dashboard');
        break;

    case 'show-fund':
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        $fund = fund_by_id($fundId);
        
        if (!$fund) {
            set_flash('error', 'Fund not found.');
            redirect_to('manager-dashboard');
        }
        
        $manager = current_manager();
        if ((int)$fund['FundManagerNumberofLicense'] !== (int)$manager['FundManagerNumberofLicense']) {
            set_flash('error', 'You do not have permission to show this fund.');
            redirect_to('manager-dashboard');
        }
        
        if (show_fund($fundId)) {
            set_flash('success', 'Fund is now visible to students.');
        } else {
            set_flash('error', 'Failed to show fund.');
        }
        redirect_to('manager-dashboard');
        break;

    // =============================================
    // إجراءات JIC - JIC Actions
    // =============================================
    
    case 'login-jic':
        $jicId = trim((string) ($_POST['jic_id'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));

        if (attempt_login('jic', $jicId, $password)) {
            set_flash('success', 'JIC login successful!');
            redirect_to('jic-participants');
        }
        
        set_flash('error', 'Invalid JIC credentials.');
        redirect_to('login', ['role' => 'jic']);
        break;

    case 'approve-participation':
        if (!is_logged_in_as_jic() && !is_logged_in_as_manager()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        $approvedById = is_logged_in_as_jic()
            ? (int) current_jic()['JicSponsorID']
            : (int) current_manager()['FundManagerNumberofLicense'];
        $approverType = is_logged_in_as_jic() ? 'JIC' : 'Manager';
        
        if (approve_participation($studentId, $fundId, $approvedById)) {
            log_audit($approverType, $approvedById, 'APPROVE_PARTICIPATION', "Approved student $studentId participation in fund $fundId", 'Student', $studentId);
            set_flash('success', 'Student participation approved successfully.');
        } else {
            set_flash('error', 'Failed to approve participation.');
        }
        if (is_logged_in_as_manager()) {
            redirect_to('jic-participants');
        }
        redirect_to('jic-participants');
        break;

    case 'reject-participation':
        if (!is_logged_in_as_jic() && !is_logged_in_as_manager()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? $_POST['rejection_reason'] ?? 'No reason provided'));
        
        if (reject_participation($studentId, $fundId, $reason)) {
            set_flash('success', 'Student participation rejected.');
        } else {
            set_flash('error', 'Failed to reject participation.');
        }
        if (is_logged_in_as_manager()) {
            redirect_to('jic-participants');
        }
        redirect_to('jic-participants');
        break;

    case 'authorize-account':
        if (!is_logged_in_as_jic() && !is_logged_in_as_manager()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $userType = (string) ($_POST['user_type'] ?? 'Student');
        $userId = (int) ($_POST['user_id'] ?? 0);
        
        if (authorize_user_account($userType, $userId)) {
            set_flash('success', 'User account has been authorized successfully.');
        } else {
            set_flash('error', 'Failed to authorize account.');
        }
        
        if (is_logged_in_as_jic()) {
            redirect_to('jic-participants');
        }
        redirect_to('jic-participants');
        break;

    case 'reject-account':
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }

        $userType = (string) ($_POST['user_type'] ?? 'Student');
        $userId = (int) ($_POST['user_id'] ?? 0);
        $reason = trim((string) ($_POST['reject_reason'] ?? 'Request rejected'));
        $actorType = 'Manager';
        $actorId = (int) current_manager()['FundManagerNumberofLicense'];

        if (reject_user_account($userType, $userId, $reason)) {
            log_audit($actorType, $actorId, 'REJECT_ACCOUNT', "Rejected $userType account $userId: $reason", $userType, $userId);
            set_flash('success', 'Account has been rejected.');
        } else {
            set_flash('error', 'Failed to reject account.');
        }
        redirect_to('admin-dashboard', ['tab' => 'users']);
        break;

    case 'admin-create-user':
        if (!is_logged_in_as_admin()) {
            set_flash('error', 'Access denied. Admin login required.');
            redirect_to('login');
        }

        $role = (string) ($_POST['role'] ?? 'Student');
        $password = trim((string) ($_POST['password'] ?? ''));
        $confirmPassword = trim((string) ($_POST['confirm_password'] ?? ''));
        $userId = trim((string) ($_POST['user_id'] ?? ''));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $redirectPage = 'admin-dashboard';
        $actorType = 'Admin';
        $actorId = (int) (current_admin()['AdminID'] ?? 1);

        // Validate user_id exists
        if ($userId === '') {
            $fieldName = $role === 'Student' ? 'Student ID' : ($role === 'FundManager' ? 'License Number' : 'User ID');
            set_flash('error', $fieldName . ' is required.');
            redirect_to($redirectPage, ['tab' => 'create-user']);
        }

        // Validate first name
        if ($firstName === '') {
            set_flash('error', 'First Name is required.');
            redirect_to($redirectPage, ['tab' => 'create-user']);
        }

        // Check passwords match
        if ($password === '' || $password !== $confirmPassword) {
            set_flash('error', 'Passwords must match and cannot be empty.');
            redirect_to($redirectPage, ['tab' => 'create-user']);
        }

        // Validate password complexity
        $hasUpper = preg_match('/[A-Z]/', $password) === 1;
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;
        $hasDigit = preg_match('/\d/', $password) === 1;
        $hasLength = strlen($password) >= 8;
        if (!($hasUpper && $hasSpecial && $hasDigit && $hasLength)) {
            set_flash('error', 'Password must be at least 8 characters with uppercase letter, number, and special character.');
            redirect_to($redirectPage, ['tab' => 'create-user']);
        }

        if ($role === 'Student') {
            $created = register_student([
                'first_name' => (string) ($_POST['first_name'] ?? ''),
                'last_name' => (string) ($_POST['last_name'] ?? ''),
                'student_id' => (string) ($_POST['user_id'] ?? ''),
                'phone' => (string) ($_POST['phone'] ?? ''),
                'campus' => (string) ($_POST['campus'] ?? 'Main Campus'),
                'degree' => (string) ($_POST['degree'] ?? 'Bachelor'),
                'major' => (string) ($_POST['major'] ?? ''),
                'iban' => (string) ($_POST['iban'] ?? ''),
                'password' => $password,
            ], $redirectPage);
            
            if (!$created) {
                return;
            }
            
            // Admin-created accounts are auto-verified
            $sid = (int) ($_POST['user_id'] ?? 0);
            authorize_user_account('Student', $sid);
            $accessId = (int) ($_POST['access_id'] ?? 1);
            if ($accessId > 0) {
                update_user_access_level('Student', $sid, $accessId);
            }
            log_audit($actorType, $actorId, 'CREATE_USER', "Created Student account $sid", 'Student', $sid);
            set_flash('success', 'Student account created and activated.');
        } elseif ($role === 'FundManager') {
            $created = register_manager([
                'first_name' => (string) ($_POST['first_name'] ?? ''),
                'last_name' => (string) ($_POST['last_name'] ?? ''),
                'license' => (string) ($_POST['user_id'] ?? ''),
                'phone' => (string) ($_POST['phone'] ?? ''),
                'password' => $password,
                'contract_name' => (string) ($_POST['contract_name'] ?? 'admin-created.pdf'),
            ], $redirectPage);
            
            if (!$created) {
                return;
            }
            
            $lid = (int) ($_POST['user_id'] ?? 0);
            authorize_user_account('FundManager', $lid);
            $accessId = (int) ($_POST['access_id'] ?? 2);
            if ($accessId > 0) {
                update_user_access_level('FundManager', $lid, $accessId);
            }
            log_audit($actorType, $actorId, 'CREATE_USER', "Created FundManager account $lid", 'FundManager', $lid);
            set_flash('success', 'Fund Manager account created and activated.');
        } elseif ($role === 'JIC') {
            register_jic([
                'jic_id' => (int) ($_POST['user_id'] ?? 0),
                'username' => (string) ($_POST['first_name'] ?? ''),
                'password' => $password,
                'department' => (string) ($_POST['department'] ?? 'JIC'),
                'phone' => (string) ($_POST['phone'] ?? ''),
            ], $redirectPage);
            $jicId = (int) ($_POST['user_id'] ?? 0);
            log_audit($actorType, $actorId, 'CREATE_USER', "Created JIC account $jicId", 'JIC', $jicId);
            set_flash('success', 'JIC Sponsor account created and activated.');
        } elseif ($role === 'Admin') {
            register_admin([
                'admin_id' => (int) ($_POST['user_id'] ?? 0),
                'username' => (string) ($_POST['first_name'] ?? ''),
                'password' => $password,
                'phone' => (string) ($_POST['phone'] ?? ''),
                'version' => 'v1.0.0',
            ], $redirectPage);
            $adminId = (int) ($_POST['user_id'] ?? 0);
            log_audit($actorType, $actorId, 'CREATE_USER', "Created Admin account $adminId", 'Admin', $adminId);
            set_flash('success', 'Admin account created and activated.');
        } else {
            set_flash('error', 'Invalid role selected.');
            redirect_to($redirectPage, ['tab' => 'create-user']);
        }

        redirect_to($redirectPage, ['tab' => 'users']);
        break;

    // =============================================
    // إجراءات Admin - Admin Actions
    // =============================================
    
    case 'login-admin':
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));
        
        // Simple admin login (in demo mode)
        if ($username === 'admin' && $password === 'admin-demo') {
            $_SESSION['auth'] = ['role' => 'admin', 'admin_id' => 1];
            set_flash('success', 'Admin login successful!');
            redirect_to('admin-dashboard');
        }
        
        set_flash('error', 'Invalid admin credentials.');
        redirect_to('login', ['role' => 'admin']);
        break;

    case 'send-sms':
        if (!is_logged_in_as_admin()) {
            set_flash('error', 'Access denied. Admin login required.');
            redirect_to('login', ['role' => 'admin']);
        }
        
        $recipientType = (string) ($_POST['recipient_type'] ?? 'all');
        $recipientId = !empty($_POST['recipient_id']) ? (int) $_POST['recipient_id'] : null;
        
        // For single recipient by phone, find student/manager ID
        if ($recipientType === 'single') {
            $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
            if ($phone !== '') {
                $foundStudent = repo_fetch_one('SELECT StudentID FROM Student WHERE StudentPhoneNumber = ? LIMIT 1', [$phone]);
                $foundManager = repo_fetch_one('SELECT FundManagerNumberofLicense FROM FundManager WHERE FundManagerPhone = ? LIMIT 1', [$phone]);
                if ($foundStudent) {
                    $recipientId = (int) $foundStudent['StudentID'];
                } elseif ($foundManager) {
                    $recipientId = (int) $foundManager['FundManagerNumberofLicense'];
                }
            }
        }
        $message = trim((string) ($_POST['message'] ?? ''));
        $messageType = (string) ($_POST['message_type'] ?? 'Notification');
        
        if (empty($message)) {
            set_flash('error', 'Please enter a message.');
            redirect_to('send-notification');
        }
        
        $adminId = (int) current_admin()['AdminID'];
        $smsId = send_sms_notification($recipientType, $recipientId, $message, $messageType, $adminId);
        
        log_audit('Admin', $adminId, 'SEND_SMS', "Sent $messageType SMS to $recipientType" . ($recipientId ? " ID: $recipientId" : ""), 'SmsLog', $smsId);
        set_flash('success', 'SMS notification sent successfully.');
        redirect_to('send-notification');
        break;

    case 'update-system':
        if (!is_logged_in_as_admin()) {
            set_flash('error', 'Access denied. Admin login required.');
            redirect_to('login', ['role' => 'admin']);
        }

        $adminId = (int) current_admin()['AdminID'];
        $updatedCount = 0;

        // Read from settings[] array (matches system-settings.php form)
        $posted = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];

        // Allowed keys and their validation rules [type, min, max]
        $allowed = [
            'site_name'              => 'string',
            'site_description'       => 'string',
            'min_subscription'       => 'posnum',
            'max_subscription'       => 'posnum',
            'withdrawal_limit_daily' => 'posnum',
            'withdrawal_limit_monthly' => 'posnum',
            'sms_enabled'            => 'bool',
            'self_ban_enabled'       => 'bool',
            'two_step_verification'  => 'bool',
            'maintenance_mode'       => 'bool',
        ];

        $errors = [];

        // Cross-validate min < max
        $newMin = isset($posted['min_subscription']) ? (float) $posted['min_subscription'] : null;
        $newMax = isset($posted['max_subscription']) ? (float) $posted['max_subscription'] : null;
        if ($newMin !== null && $newMax !== null && $newMin >= $newMax) {
            $errors[] = 'Minimum subscription must be less than maximum subscription.';
        }

        if (empty($errors)) {
            foreach ($allowed as $key => $type) {
                if (!array_key_exists($key, $posted)) {
                    // checkboxes send nothing when unchecked — default bool to '0'
                    if ($type === 'bool') {
                        update_system_setting($key, '0', $adminId);
                        $updatedCount++;
                    }
                    continue;
                }
                $raw = trim((string) $posted[$key]);
                if ($type === 'posnum') {
                    $val = (float) $raw;
                    if ($val < 0) continue;
                    $raw = (string) $val;
                } elseif ($type === 'bool') {
                    $raw = $raw === '1' ? '1' : '0';
                }
                if ($raw === '') continue;
                if (update_system_setting($key, $raw, $adminId)) {
                    $updatedCount++;
                }
            }
            log_audit('Admin', $adminId, 'UPDATE_SYSTEM', "Updated $updatedCount system settings");
            set_flash('success', "Settings saved successfully. ($updatedCount keys updated)");
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect_to('system-settings');
        break;

    case 'update-access-level':
        if (!is_logged_in_as_admin()) {
            set_flash('error', 'Access denied. Admin login required.');
            redirect_to('login', ['role' => 'admin']);
        }
        
        $userType = (string) ($_POST['user_type'] ?? 'Student');
        $userId = (int) ($_POST['user_id'] ?? 0);
        $accessId = (int) ($_POST['access_id'] ?? 1);
        
        if (update_user_access_level($userType, $userId, $accessId)) {
            log_audit('Admin', (int)current_admin()['AdminID'], 'UPDATE_ACCESS_LEVEL', "Changed access level for $userType $userId to AccessID: $accessId", $userType, $userId);
            set_flash('success', 'Access level updated successfully.');
        } else {
            set_flash('error', 'Failed to update access level.');
        }
        redirect_to('admin-dashboard', ['tab' => 'access-levels']);
        break;

    case 'generate-report':
        if (!is_logged_in_as_admin()) {
            set_flash('error', 'Access denied. Admin login required.');
            redirect_to('login', ['role' => 'admin']);
        }
        $reportType = (string) ($_POST['report_type'] ?? 'summary');
        $dateFrom = trim((string) ($_POST['date_from'] ?? ''));
        $dateTo = trim((string) ($_POST['date_to'] ?? ''));
        
        // Store report parameters in session for display
        $_SESSION['generated_report'] = [
            'type' => $reportType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        
        log_audit('Admin', (int)current_admin()['AdminID'], 'GENERATE_REPORT', "Generated $reportType report");
        set_flash('success', 'Report generated successfully.');
        redirect_to('admin-dashboard', ['tab' => 'overview']);
        break;

    // =============================================
    // إجراءات التحقق الثنائي للصندوق - Fund Verification Actions
    // =============================================
    
    case 'request-fund-verification':
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $fundId = (int) ($_POST['fund_id'] ?? 0);
        $operationType = (string) ($_POST['operation_type'] ?? 'Update');
        
        $manager = current_manager();
        $code = create_fund_verification_code($fundId, (int)$manager['FundManagerNumberofLicense'], $operationType);
        
        // In real system, send SMS. For demo, show the code
        set_flash('info', "Verification code: $code (In production, this would be sent via SMS)");
        redirect_to('verify-fund-operation', ['fund_id' => $fundId, 'operation' => $operationType]);
        break;

    case 'verify-fund-operation':
        if (!is_logged_in_as_manager()) {
            set_flash('error', 'Access denied.');
            redirect_to('login');
        }
        
        $enteredCode = (int) ($_POST['verification_code'] ?? 0);
        
        if (verify_fund_code($enteredCode)) {
            set_flash('success', 'Verification successful. You may now proceed with the operation.');
            redirect_to('manager-dashboard');
        } else {
            set_flash('error', 'Invalid or expired verification code.');
            redirect_to('manager-dashboard');
        }
        break;

    // =============================================
    // إجراءات الشروط والأحكام - Terms Actions
    // =============================================
    
    case 'accept-terms':
        $userType = '';
        $userId = 0;
        
        if (is_logged_in_as_student()) {
            $userType = 'Student';
            $userId = (int) current_student()['StudentID'];
        } elseif (is_logged_in_as_manager()) {
            $userType = 'FundManager';
            $userId = (int) current_manager()['FundManagerNumberofLicense'];
        } else {
            set_flash('error', 'Please login first.');
            redirect_to('login');
        }
        
        $termsVersion = (string) ($_POST['terms_version'] ?? 'v1.0');
        
        if (record_terms_acceptance($userType, $userId, $termsVersion)) {
            log_audit($userType, $userId, 'ACCEPT_TERMS', "Accepted terms version: $termsVersion");
            set_flash('success', 'Terms and conditions accepted successfully.');
        } else {
            set_flash('error', 'Failed to record terms acceptance.');
        }
        
        $returnTo = (string) ($_POST['return_to'] ?? 'home');
        redirect_to($returnTo);
        break;

    // =============================================
    // إجراءات البحث - Search Actions
    // =============================================
    
    case 'search-funds':
        $criteria = [
            'keyword' => trim((string) ($_POST['keyword'] ?? $_GET['keyword'] ?? '')),
            'risk_level' => trim((string) ($_POST['risk_level'] ?? $_GET['risk_level'] ?? '')),
            'min_amount' => trim((string) ($_POST['min_amount'] ?? $_GET['min_amount'] ?? '')),
            'max_amount' => trim((string) ($_POST['max_amount'] ?? $_GET['max_amount'] ?? '')),
            'min_return' => trim((string) ($_POST['min_return'] ?? $_GET['min_return'] ?? '')),
            'max_duration' => trim((string) ($_POST['max_duration'] ?? $_GET['max_duration'] ?? '')),
            'investment_type' => trim((string) ($_POST['investment_type'] ?? $_GET['investment_type'] ?? '')),
        ];
        
        // Store search criteria in session
        $_SESSION['fund_search_criteria'] = $criteria;
        $_SESSION['fund_search_results'] = search_funds($criteria);
        
        redirect_to('search-funds');
        break;

    default:
        redirect_to('home');
}

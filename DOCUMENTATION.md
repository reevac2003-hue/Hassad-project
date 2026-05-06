# 📚 توثيق مشروع حصاد (Hassad Documentation)

## 🏗️ نظرة عامة على المشروع

**حصاد** هو منصة إدارة صناديق استثمارية مصممة للمؤسسات التعليمية. يتيح للطلاب الاستثمار في صناديق مدارة بشكل احترافي، مع لوحات تحكم مخصصة لكل دور.

---

## 📁 هيكل المشروع

```
hassad/
├── index.php                    # الموجه الرئيسي (Router)
├── action.php                   # معالج الإجراءات (POST Handler)
├── logout.php                   # تسجيل الخروج
├── config/
│   ├── app.php                  # إعدادات التطبيق
│   └── database.php             # اتصال قاعدة البيانات
├── includes/
│   ├── bootstrap.php            # ملف التهيئة الرئيسي
│   ├── functions.php            # الدوال المساعدة (~30 دالة)
│   └── repository.php           # طبقة البيانات (~60+ دالة)
├── assets/
│   ├── css/
│   │   ├── styles.css           # نظام التصميم الكامل
│   │   └── theme-colors.css     # متغيرات الألوان
│   ├── js/
│   │   └── app.js               # التفاعلية (JavaScript)
│   └── images/                  # الصور
├── pages/                       # صفحات النظام
│   ├── home.php                 # الصفحة الرئيسية
│   ├── login.php                # تسجيل الدخول
│   ├── register-student.php     # تسجيل طالب
│   ├── register-manager.php     # تسجيل مدير صندوق
│   ├── account-verified.php     # تأكيد التحقق
│   ├── student-dashboard.php    # لوحة تحكم الطالب
│   ├── manager-dashboard.php    # لوحة تحكم المدير
│   ├── admin-dashboard.php      # لوحة تحكم الإداري
│   ├── jic-participants.php     # مشاركات JIC
│   ├── fund.php                 # تفاصيل الصندوق
│   ├── wallet.php               # المحفظة
│   └── ...                      # باقي الصفحات
└── database/
    ├── schema.sql               # جداول قاعدة البيانات
    └── seed.sql                 # بيانات تجريبية
```

---

## 🔐 الأدوار والصلاحيات

| الدور | الوصف | لوحة التحكم |
|-------|-------|-------------|
| **Student** (طالب) | تصفح الصناديق، الاشتراك، إدارة المحفظة | `student-dashboard.php` |
| **Fund Manager** (مدير صندوق) | إنشاء وإدارة الصناديق، توزيع العوائد | `manager-dashboard.php` |
| **Admin** (مدير إداري) | إدارة المنصة، الصلاحيات، التقارير | `admin-dashboard.php` |
| **JIC** (جهة راعية) | مراقبة المشاركات والخصومات | `jic-participants.php` |

---

## 🔄 الوضع التجريبي (Demo Mode)

المشروع يعمل مع قاعدة بيانات MySQL عبر PDO فقط.

**بيانات الدخول:**
| الدور | المعرّف | كلمة المرور |
|-------|---------|-------------|
| طالب | `441434525` | `Welcome!23` |
| مدير صندوق | `414343525` | `Manager!23` |
| مدير إداري | - | `admin-demo` |
| JIC | `jic-admin` | `jic-demo` |

---

## 📖 توثيق الدوال

### 1. دوال الأمان (`functions.php`)

#### `e($value): string`
**الوصف:** تأمين المخرجات من هجمات XSS  
**المدخلات:** `$value` - أي قيمة  
**المخرجات:** نص مُؤمّن بتحويل الأحرف الخاصة إلى HTML entities  
**مثال:** `e('<script>') → '&lt;script&gt;'`

---

### 2. دوال التوجيه والروابط (`functions.php`)

#### `route($page, $params): string`
**الوصف:** بناء رابط URL لصفحة معينة  
**المدخلات:** `$page` - اسم الصفحة، `$params` - معاملات إضافية  
**المخرجات:** رابط كامل  
**مثال:** `route('fund', ['slug' => 'sukuk']) → '/hassad/index.php?page=fund&slug=sukuk'`

#### `action_url($action): string`
**الوصف:** بناء رابط لإجراء POST  
**المدخلات:** `$action` - اسم الإجراء  
**المخرجات:** رابط الإجراء  
**مثال:** `action_url('login') → '/hassad/action.php?action=login'`

#### `url_base(): string`
**الوصف:** الرابط الأساسي للموقع  
**المخرجات:** رابط مع `/` في النهاية

#### `redirect_to($page, $params): void`
**الوصف:** إعادة توجيه المستخدم لصفحة أخرى  
**ملاحظة:** تُنهي تنفيذ السكريبت بعد التوجيه

---

### 3. دوال التنسيق (`functions.php`)

#### `format_sar($amount): string`
**الوصف:** تنسيق المبلغ بالريال السعودي  
**مثال:** `format_sar(1500.50) → 'SAR 1,500.50'`

#### `mask_phone($phone): string`
**الوصف:** إخفاء الأرقام الوسطى لحماية الخصوصية  
**مثال:** `mask_phone('0562313577') → '056***3577'`

#### `mask_card($card): string`
**الوصف:** إخفاء أرقام البطاقة البنكية  
**مثال:** `mask_card('4277889912341111') → '4277 **** **** 1111'`

#### `risk_class($level): string`
**الوصف:** كلاس CSS لمستوى المخاطرة  
**القيم:** `low → risk-low` | `medium → risk-medium` | `high → risk-high`

#### `capitalize($text): string`
**الوصف:** تحويل أول حرف لكبير

---

### 4. دوال رسائل Flash (`functions.php`)

#### `set_flash($type, $message): void`
**الوصف:** تخزين رسالة مؤقتة في الجلسة  
**الأنواع:** `success`, `error`, `info`, `warning`

#### `flash_message(): string`
**الوصف:** جلب وعرض رسالة Flash كـ HTML toast  
**ملاحظة:** تحذف الرسالة بعد عرضها (مرة واحدة فقط)

---

### 5. دوال المصادقة (`functions.php`)

#### `is_logged_in_as_student(): bool`
**الوصف:** التحقق من تسجيل دخول الطالب

#### `is_logged_in_as_manager(): bool`
**الوصف:** التحقق من تسجيل دخول مدير الصندوق

#### `is_logged_in_as_admin(): bool`
**الوصف:** التحقق من تسجيل دخول المدير الإداري

#### `is_logged_in_as_jic(): bool`
**الوصف:** التحقق من تسجيل دخول JIC

#### `require_student_login(): void`
**الوصف:** إلزام تسجيل الدخول كطالب أو إعادة التوجيه

#### `require_manager_login(): void`
**الوصف:** إلزام تسجيل الدخول كمدير صندوق أو إعادة التوجيه

#### `require_admin_login(): void`
**الوصف:** إلزام تسجيل الدخول كمدير إداري أو إعادة التوجيه

#### `require_jic_login(): void`
**الوصف:** إلزام تسجيل الدخول كـ JIC أو إعادة التوجيه

#### `is_demo_mode(): bool`
**الوصف:** التحقق من الوضع التجريبي

---

### 6. دوال العرض (Rendering) (`functions.php`)

#### `render_head($title): void`
**الوصف:** عرض رأس HTML مع CSS والخطوط  
**المدخلات:** `$title` - عنوان الصفحة

#### `render_end(): void`
**الوصف:** عرض نهاية HTML مع JavaScript

#### `render_responsive_shell_start($title, $showBack): void`
**الوصف:** عرض الهيكل العام (هيدر + قائمة جانبية)

#### `render_responsive_shell_end(): void`
**الوصف:** إغلاق الهيكل العام

#### `render_menu_items($role): void`
**الوصف:** عرض عناصر القائمة حسب دور المستخدم

#### `render_footer(): void`
**الوصف:** عرض تذييل الصفحة

---

---

### 8. دوال المستخدم الحالي (`repository.php`)

#### `current_student(): array`
**الوصف:** جلب بيانات الطالب المسجّل دخوله  
**الحقول:** `StudentID`, `StudentNameFirst`, `StudentNameLast`, `StudentMajor`, `StudentPhoneNumber`, `StudentAccountStates`, `StudentCampus`, `StudentAcademicDegree`, `ReinvestmentAmount`

#### `current_manager(): array`
**الوصف:** جلب بيانات مدير الصندوق المسجّل دخوله  
**الحقول:** `FundManagerNumberofLicense`, `FundManagerID`, `FundManagerNameFirst`, `FundManagerNameLast`, `FundManagerPhone`, `FundManagerAccountStatus`

#### `current_admin(): array`
**الوصف:** جلب بيانات المدير الإداري

#### `current_jic(): array`
**الوصف:** جلب بيانات الجهة الراعية JIC

#### `current_wallet($studentId): array`
**الوصف:** جلب المحفظة الاستثمارية للطالب  
**الحقول:** `InvestmentWalletTotalAmount`, `InvestmentWalletReturn`, `InvestmentWalletCredit`

#### `current_bank_account($studentId): array`
**الوصف:** جلب بيانات الحساب البنكي والبطاقة الائتمانية

---

### 9. دوال التسجيل والمصادقة (`repository.php`)

#### `register_student($data): void`
**الوصف:** إنشاء حساب طالب جديد مع محفظة وحساب بنكي  
**المدخلات:** `student_id`, `first_name`, `last_name`, `password`, `phone`, `major`, `campus`, `degree`, `iban`

#### `register_manager($data): void`
**الوصف:** إنشاء حساب مدير صندوق جديد  
**المدخلات:** `license`, `first_name`, `last_name`, `password`, `phone`

#### `pending_verify_data(): ?array`
**الوصف:** جلب بيانات عملية التحقق المعلّقة

#### `verify_pending(): void`
**الوصف:** تفعيل الحساب بعد التحقق من الرمز

#### `attempt_login($role, $identity, $password): bool`
**الوصف:** محاولة تسجيل الدخول والتحقق من البيانات  
**الأدوار:** `student`, `manager`

---

### 10. دوال الصناديق الاستثمارية (`repository.php`)

#### `all_funds($publishedOnly): array`
**الوصف:** جلب جميع الصناديق (أو المنشورة فقط)

#### `fund_by_id($id): ?array`
**الوصف:** البحث عن صندوق بالمعرّف الرقمي

#### `fund_by_slug($slug): ?array`
**الوصف:** البحث عن صندوق بالاسم المختصر في الرابط

#### `find_fund_by_id($id): ?array`
**الوصف:** بديل لـ `fund_by_id` للتوافق

#### `manager_funds($managerId): array`
**الوصف:** جلب صناديق مدير محدد  
**ملاحظة:** تبحث بـ `FundManagerNumberofLicense` و `FundManagerID`

#### `next_fund_id(): int`
**الوصف:** توليد معرّف فريد للصندوق الجديد

#### `create_fund_draft($managerLicense, $data): int`
**الوصف:** إنشاء مسودة صندوق جديد  
**المدخلات:** `title`, `investment_type`, `max_amount`, `min_amount`, `duration`, `return_policy`, `risk_level`, `expected_return`, `description`, `highlight`

#### `publish_fund($fundId): void`
**الوصف:** تغيير حالة الصندوق من مسودة إلى منشور

#### `undo_fund_draft($fundId): void`
**الوصف:** حذف صندوق في حالة المسودة فقط

#### `update_fund($fundId, $data): bool`
**الوصف:** تحديث بيانات صندوق موجود

#### `hide_fund($fundId): bool`
**الوصف:** إخفاء صندوق من العرض

#### `show_fund($fundId): bool`
**الوصف:** إعادة عرض صندوق مخفي

#### `search_funds($criteria): array`
**الوصف:** البحث في الصناديق بمعايير متعددة  
**المعايير:** `keyword`, `risk_level`, `min_amount`, `max_amount`, `min_return`, `max_duration`, `investment_type`

---

### 11. دوال الاشتراكات والعقود (`repository.php`)

#### `subscribe_to_fund($studentId, $fundId, $amount): void`
**الوصف:** تسجيل اشتراك طالب في صندوق  
**ملاحظة:** تُنشئ عقد ومعاملة مالية وتخصم من المحفظة

#### `cancel_fund_subscription($studentId, $fundId): void`
**الوصف:** إلغاء اشتراك طالب من صندوق  
**ملاحظة:** تُعيد المبلغ للمحفظة وتحذف العقد

#### `student_has_contract($studentId, $fundId): bool`
**الوصف:** التحقق من وجود عقد اشتراك

#### `student_contracts($studentId): array`
**الوصف:** جلب جميع عقود الطالب

#### `subscribers_for_fund($fundId): array`
**الوصف:** جلب المشتركين في صندوق محدد

---

### 12. دوال المحفظة والمعاملات (`repository.php`)

#### `student_transactions($studentId): array`
**الوصف:** جلب معاملات طالب محدد

#### `all_transactions(): array`
**الوصف:** جلب جميع المعاملات في النظام

#### `latest_investment_amount($studentId, $fundId): float`
**الوصف:** جلب آخر مبلغ استثمار

#### `student_dashboard_cards($studentId): array`
**الوصف:** بناء بطاقات لوحة التحكم (كل صندوق مع بيانات الاستثمار)

---

### 13. دوال السحب (`repository.php`)

#### `create_withdrawal_request($studentId, $amount, $method): void`
**الوصف:** إنشاء طلب سحب جديد

#### `student_withdrawal_requests($studentId): array`
**الوصف:** جلب طلبات سحب طالب

#### `all_withdrawal_requests(): array`
**الوصف:** جلب جميع طلبات السحب

#### `process_withdrawal($requestId, $action, $adminNote): bool`
**الوصف:** معالجة طلب سحب (قبول/رفض)

---

### 14. دوال توزيع العوائد (`repository.php`)

#### `create_return_distribution($fundId, $amount, $method): void`
**الوصف:** إنشاء سجل توزيع عوائد

#### `distribute_returns($fundId, $amount): void`
**الوصف:** توزيع العوائد على المشتركين بالتساوي

#### `all_return_distributions(): array`
**الوصف:** جلب جميع سجلات التوزيع

---

### 15. دوال حظر المستخدمين (`repository.php`)

#### `ban_user($userType, $userId, $reason, $duration): void`
**الوصف:** حظر مستخدم  
**المدخلات:** `$userType` (Student/Manager)، `$userId`، `$reason`، `$duration` (بالأيام)

#### `unban_user($banId): void`
**الوصف:** رفع الحظر عن مستخدم

#### `is_user_banned($userType, $userId): bool`
**الوصف:** التحقق من حالة الحظر

#### `all_bans(): array`
**الوصف:** جلب جميع سجلات الحظر

---

### 16. دوال JIC - الجهة الراعية (`repository.php`)

#### `approve_participation($studentId, $fundId): void`
**الوصف:** الموافقة على طلب مشاركة

#### `reject_participation($studentId, $fundId, $reason): void`
**الوصف:** رفض طلب مشاركة مع السبب

#### `all_participation_approvals($statusFilter): array`
**الوصف:** جلب طلبات الموافقة (مع تصفية اختيارية)  
**الفلاتر:** `Pending`, `Approved`, `Rejected`, أو فارغ للكل

#### `get_student_deductions(): array`
**الوصف:** جلب خصومات الطلاب

---

### 17. دوال الإدارة والنظام (`repository.php`)

#### `authorize_user_account($userType, $userId): bool`
**الوصف:** تفويض حساب مستخدم

#### `all_students(): array`
**الوصف:** جلب جميع الطلاب المسجلين

#### `all_managers(): array`
**الوصف:** جلب جميع مديري الصناديق

#### `get_access_levels(): array`
**الوصف:** جلب مستويات الوصول والصلاحيات

#### `update_user_access_level($userId, $newLevel): bool`
**الوصف:** تحديث مستوى صلاحية مستخدم

#### `get_system_setting($key): ?string`
**الوصف:** جلب إعداد نظام معين

#### `update_system_setting($key, $value): void`
**الوصف:** تحديث إعداد في النظام

#### `send_sms_notification($phone, $message): void`
**الوصف:** إرسال إشعار SMS (محاكاة)

#### `get_sms_logs(): array`
**الوصف:** جلب سجل الرسائل المرسلة

#### `log_audit($action, $details): void`
**الوصف:** تسجيل عملية في سجل المراجعة

#### `get_audit_logs(): array`
**الوصف:** جلب سجل المراجعة

---

### 18. دوال التقارير (`repository.php`)

#### `report_summary_stats(): array`
**الوصف:** إحصائيات ملخصة للتقارير (عدد الطلاب، المديرين، الصناديق، المعاملات)

#### `manager_transaction_details($managerId): array`
**الوصف:** تفاصيل معاملات مدير محدد

#### `customers_with_details(): array`
**الوصف:** قائمة العملاء (الطلاب) مع تفاصيل المحفظة والصناديق

---

### 19. دوال مساعدة لقاعدة البيانات (`repository.php`)

#### `create_fund_verification_code($fundId, $operationType): string`
**الوصف:** إنشاء رمز تحقق لعملية على صندوق

#### `verify_fund_code($fundId, $code): bool`
**الوصف:** التحقق من رمز عملية الصندوق

#### `record_terms_acceptance($studentId, $version): void`
**الوصف:** تسجيل موافقة الطالب على الشروط

#### `repo_fetch_one($table, $conditions): ?array`
**الوصف:** جلب سجل واحد من قاعدة البيانات (للوضع الحقيقي)

#### `repo_fetch_all($table, $conditions): array`
**الوصف:** جلب جميع السجلات المطابقة

---

## 🎨 نظام التصميم

### لوحة الألوان (6 ألوان فقط)
| اللون | الكود | الاستخدام |
|-------|-------|-----------|
| أخضر داكن | `#18442A` | اللون الرئيسي، الهيدر، الأزرار |
| أخضر فاتح | `#45644A` | اللون الثانوي، التدرجات |
| بيج دافئ | `#E4DBC4` | لمسات دافئة، خلفيات بطاقات |
| كريمي | `#F3EDE3` | خلفية الصفحات |
| أبيض | `#FFFFFF` | البطاقات والمحتوى |
| أسود | `#000000` | النصوص |

### الخط
- **Tajawal** - يدعم العربية والإنجليزية
- الأحجام: 16px أساسي، نسبة 1.25 (Major Third)

### نقاط الاستجابة (Breakpoints)
- **موبايل:** < 480px
- **تابلت:** 481px - 768px
- **سطح مكتب:** 769px - 992px
- **شاشة كبيرة:** > 992px

---

## ⚡ إجراءات action.php

| الإجراء | الوصف |
|---------|-------|
| `register-student` | تسجيل طالب جديد |
| `register-manager` | تسجيل مدير صندوق |
| `login` | تسجيل الدخول |
| `verify` | التحقق من رمز OTP |
| `subscribe` | الاشتراك في صندوق |
| `cancel-subscription` | إلغاء الاشتراك |
| `create-fund` | إنشاء مسودة صندوق |
| `publish-fund` | نشر صندوق |
| `update-fund` | تحديث صندوق |
| `hide-fund` | إخفاء صندوق |
| `show-fund` | إظهار صندوق |
| `undo-draft` | حذف مسودة |
| `withdraw` | طلب سحب |
| `distribute-returns` | توزيع عوائد |
| `ban-user` | حظر مستخدم |
| `unban-user` | رفع الحظر |
| `self-ban` | حظر ذاتي |
| `approve-participation` | قبول مشاركة |
| `reject-participation` | رفض مشاركة |
| `update-settings` | تحديث الإعدادات |
| `send-sms` | إرسال إشعار |
| `update-profile` | تحديث الملف الشخصي |
| `search-funds` | البحث في الصناديق |
| `accept-terms` | قبول الشروط |
| `reset-demo` | إعادة تعيين البيانات التجريبية |

---

## 🗄️ جداول قاعدة البيانات

| الجدول | الوصف |
|--------|-------|
| `Admin` | المديرون الإداريون |
| `Student` | الطلاب |
| `FundManager` | مديرو الصناديق |
| `Fund` | الصناديق الاستثمارية |
| `BankAccount` | الحسابات البنكية |
| `InvestmentWallet` | المحافظ الاستثمارية |
| `Contract` | العقود (اشتراكات الطلاب) |
| `Transaction` | المعاملات المالية |
| `Notification` | الإشعارات |
| `JicSponsor` | الجهات الراعية |
| `UserBan` | سجلات الحظر |
| `SystemSettings` | إعدادات النظام |
| `SmsLog` | سجل الرسائل |
| `ReturnDistribution` | توزيعات العوائد |
| `WithdrawalRequest` | طلبات السحب |
| `ParticipationApproval` | موافقات المشاركة |
| `StudentDeduction` | خصومات الطلاب |
| `FundVerification` | رموز التحقق |
| `AuditLog` | سجل المراجعة |
| `TermsAcceptance` | قبول الشروط |

---

## 🚀 التشغيل

1. ضع المشروع في `htdocs/hassad`
2. شغّل XAMPP (Apache + MySQL)
3. افتح المتصفح: `http://localhost/hassad/`
4. النظام يعمل في الوضع التجريبي تلقائياً بدون قاعدة بيانات
5. لاستخدام قاعدة بيانات: أنشئ قاعدة `hassad_db` ونفّذ `schema.sql` ثم `seed.sql`

---

## 🧭 تحديث مهم: مصدر بيانات التقارير

### سياسة التقارير بعد التحديث
- **جميع صفحات التقارير الجديدة تعمل بسياسة DB-First**:
    - إذا كان اتصال قاعدة البيانات متاحاً (`db_connection()`): يتم الجلب مباشرة من جداول MySQL.
    - إذا كان الاتصال غير متاح: يتم التحويل تلقائياً إلى بيانات الوضع التجريبي (Demo fallback).

### الصفحات التي تم تحديثها لتكون DB-First
- `pages/manager-reports.php`
    - معاملات المدير: من جدول `Transaction` حسب `FundManagerNumberofLicense`.
    - صناديق المدير: من جدول `Fund`.
    - العملاء في التقرير: من `Student` مع `InvestmentWallet` وتجميعات `Contract/Transaction`.
    - إحصائيات النظام داخل التقرير: من جداول `Student`, `FundManager`, `Fund`, `Transaction`, `InvestmentWallet`.

- `pages/investment-report.php`
    - تقرير استثمار الطالب من بيانات فعلية عبر `Fund`, `Contract`, `Transaction`.
    - يقرأ تفاصيل الاشتراك وحركة العمليات لصندوق محدد.
    - يدعم fallback للصندوق المشترك الأخير عند غياب `slug`.

### ملاحظة هندسية
- تم الإبقاء على fallback للوضع التجريبي لضمان استمرار عمل النظام حتى عند غياب الاتصال بقاعدة البيانات.

---

## 🗂️ ملاحظات عربية عند كل أداة/صفحة (النظام كامل)

### ملفات الجذر (Core Entry)
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `index.php` | الموجّه الرئيسي للنظام، يحدد الصفحة بناءً على `page` مع قائمة صفحات مسموحة. |
| `action.php` | نقطة تنفيذ جميع إجراءات POST (تسجيل، اشتراك، نشر، سحب، إلخ). |
| `logout.php` | ينهي الجلسة ويعيد المستخدم لصفحة الدخول. |

### الإعدادات والبنية
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `config/app.php` | إعدادات عامة للتطبيق (اسم المنصة، روابط، مفاتيح أساسية). |
| `config/database.php` | إعداد اتصال PDO بقاعدة البيانات وإرجاع الاتصال عند النجاح. |
| `includes/bootstrap.php` | تهيئة شاملة: جلسات، تحميل الإعدادات، توحيد بيئة التشغيل. |
| `includes/functions.php` | دوال العرض، الأمان، الفلاش، المسارات، التحقق من تسجيل الدخول. |
| `includes/repository.php` | طبقة الوصول للبيانات (DB + Demo) ودوال الأعمال الأساسية. |

### صفحات الحساب والدخول
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `pages/login.php` | تسجيل الدخول حسب الدور (طالب/مدير/إداري/JIC). |
| `pages/account-verified.php` | شاشة نجاح التحقق وتفعيل الحساب. |
| `pages/edit-profile.php` | تحديث بيانات الملف الشخصي للمستخدم المسجل. |
| `pages/terms.php` | عرض الشروط وسياسة القبول للمستخدمين. |
| `pages/self-ban.php` | تنفيذ الحظر الذاتي للحساب مع كود تأكيد. |

### لوحات التحكم
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `pages/home.php` | الصفحة العامة الرئيسية وعرض الصناديق المتاحة. |
| `pages/student-dashboard.php` | لوحة الطالب (محفظة، اشتراكات، إشعارات، روابط سريعة). |
| `pages/manager-dashboard.php` | لوحة مدير الصندوق (الصناديق، المتابعون، عمليات الإدارة). |
| `pages/admin-dashboard.php` | لوحة الإدارة العامة (إحصائيات، متابعة النظام، إجراءات إدارية). |

### الصناديق والاستثمار
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `pages/fund.php` | صفحة تفاصيل الصندوق الواحد ومؤشرات المخاطرة/العائد. |
| `pages/create-fund.php` | إنشاء صندوق جديد كمسودة لمدير الصندوق. |
| `pages/edit-fund.php` | تعديل بيانات صندوق موجود قبل/بعد النشر حسب الصلاحية. |
| `pages/publish-fund.php` | نشر الصندوق وإتاحته للاشتراك. |
| `pages/search-funds.php` | بحث متعدد المعايير في الصناديق. |
| `pages/subscribe.php` | تنفيذ اشتراك الطالب في صندوق محدد. |
| `pages/verify-fund-operation.php` | تحقق أمني لعمليات حساسة على الصندوق (OTP/Code). |

### المحفظة والبنوك والسحب
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `pages/wallet.php` | عرض رصيد المحفظة والعوائد والائتمان والحركات. |
| `pages/bank-cards.php` | إدارة بيانات البطاقات البنكية المرتبطة بحساب الطالب. |
| `pages/withdraw.php` | تقديم طلبات السحب (ربح/كامل/جزئي). |

### التقارير
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `pages/reports.php` | تحويل ذكي لصفحة التقارير المناسبة حسب الدور. |
| `pages/manager-reports.php` | تقارير المدير المفصلة (Executive + Filters + DB-First). |
| `pages/investment-report.php` | تقرير استثمار تفصيلي لصندوق/طالب محدد. |

### إدارة المستخدمين والصلاحيات
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `pages/register-student.php` | إنشاء حساب طالب جديد مع بيانات أساسية. |
| `pages/register-manager.php` | إنشاء حساب مدير صندوق وربط صلاحياته. |
| `pages/system-settings.php` | إعدادات النظام العامة (تشغيل/قيم/نسخ/سلوك). |

### صفحات JIC والمراجعة
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `pages/jic-participants.php` | إدارة طلبات مشاركة الطلاب (قبول/رفض). |
| `pages/send-notification.php` | إرسال إشعارات داخلية للمستخدمين أو شرائح محددة. |

### صفحات نتائج وتشغيل
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `pages/operation-result.php` | شاشة نتائج تنفيذ العمليات (نجاح/فشل/تفاصيل). |

### ملفات الواجهة الأمامية
| الأداة/الصفحة | الملاحظة بالعربي |
|---|---|
| `assets/css/styles.css` | نظام التصميم الرئيسي، مكوّنات الواجهات، responsive وprint. |
| `assets/css/theme-colors.css` | تعريف متغيرات الألوان والثيم. |
| `assets/js/app.js` | سلوكيات JavaScript للتفاعل والواجهات. |

---

## ✅ ملاحظات تشغيل وضبط جودة
- عند اختبار التقارير، تأكد من وجود بيانات فعلية في جداول: `Transaction`, `Fund`, `Contract`, `InvestmentWallet`.
- في حالة ظهور نتائج فارغة مع وجود مستخدمين، تحقق من العلاقات بين `StudentID`, `FundID`, `FundManagerNumberofLicense`.
- عند العمل على بيئة بدون DB، النظام سيعمل عبر Demo fallback تلقائياً.

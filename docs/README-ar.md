# دليل عربي: Simple Page Builder

هذا المكوّن الإضافي لوردبريس يتيح إنشاء صفحات بكميات كبيرة عبر واجهة REST API آمنة، باستخدام مفاتيح API (أو JWT اختياريًا)، مع تحديد معدّل الطلبات، وتسجيل كامل للنشاط، وإرسال Webhook موقّع بعد النجاح، ولوحة إدارة سهلة تحت أدوات → Page Builder.

## ما الذي يقدّمه المكوّن؟
- واجهة REST لإنشاء عدة صفحات في طلب واحد: `POST /wp-json/pagebuilder/v1/create-pages`.
- المصادقة: إما مفتاح + سر API (الوضع الافتراضي) أو JWT (اختياري).
- تحديد المعدّل لكل مفتاح (افتراضي 100 طلب/ساعة، قابل للتعديل).
- سجلات تفصيلية لكل طلب + تتبّع الصفحات المنشأة + سجلات تسليم Webhook.
- Webhook عند النجاح مع ترويسة توقيع HMAC-SHA256: `X-Webhook-Signature` ومحاولات إعادة.
- واجهة إدارة (أدوات → Page Builder): إدارة المفاتيح، السجلات، الصفحات المنشأة، الإعدادات، توثيق API.

## الاستخدام السريع
1) توليد مفتاح جديد: أدوات → Page Builder → تبويب API Keys → Generate.
2) (اختياري) فعّل JWT من تبويب Settings إذا أردت استخدام Bearer بدل رؤوس مفاتيح API.
3) أرسل طلب POST إلى: `https://your-site.com/wp-json/pagebuilder/v1/create-pages`
   - وضع API Key: أرسل الترويسات: `X-SPB-API-Key` و`X-SPB-API-Secret`.
   - وضع JWT: أرسل الترويسة: `Authorization: Bearer <jwt>` (يوقّع بـ Webhook Secret ويحتوي `ak_fp = sha256(API Key)`).
4) جسم الطلب JSON:
```json
{
  "pages": [
    { "title": "About Us", "content": "<p>About...</p>", "slug": "about" },
    { "title": "Contact", "content": "Contact details..." }
  ]
}
```
5) عند نجاح الإنشاء، يُرسل Webhook إلى عنوانك المحدد في الإعدادات مع توقيع `X-Webhook-Signature`.

## هيكل المجلدات
```
simple-page-builder/
├─ simple-page-builder.php                 (الملف الرئيسي/التمهيد)
├─ includes/
│  ├─ helpers.php                         (دوال مساعدة عامة)
│  ├─ class-spb-database.php              (طبقة قاعدة البيانات والجداول والاستعلامات)
│  ├─ class-spb-settings.php              (إعدادات المكوّن)
│  ├─ class-spb-logger.php                (التسجيل Logging)
│  ├─ class-spb-rate-limiter.php          (محدّد المعدّل لكل مفتاح)
│  ├─ class-spb-auth.php                  (المصادقة: API Key/JWT)
│  ├─ class-spb-webhook.php               (إرسال Webhook مع توقيع ومحاولات إعادة)
│  └─ class-spb-rest-controller.php       (تسجيل المسارات ومعالجة create-pages)
├─ admin/
│  ├─ class-spb-admin.php                 (قائمة أدوات/صفحة الإدارة ومعالجات النماذج)
│  └─ views/
│     ├─ keys.php                         (واجهة إدارة مفاتيح API)
│     ├─ logs.php                         (سجل نشاط API + فلاتر وتصدير CSV)
│     ├─ pages.php                        (قائمة الصفحات المنشأة عبر API)
│     ├─ settings.php                     (الإعدادات + مُولّد JWT للمشرف)
│     └─ docs.php                         (توثيق استخدام API داخل لوحة التحكم)
├─ docs/
│  ├─ postman.json                        (مجموعة Postman جاهزة)
│  └─ README-ar.md                        (هذا الدليل بالعربية)
├─ README.md                              (دليل بالإنجليزية)
└─ uninstall.php                          (إزالة بيانات المكوّن عند إلغاء التثبيت - اختياري)
```

## شرح الملفات (بالعربي)

### 1) `simple-page-builder.php`
- يعرّف ثوابت المكوّن: النسخة، المسارات، إلخ.
- يضمّن ملفات `includes/` و`admin/` اللازمة.
- Hook التفعيل: ينشئ الجداول ويضبط الإعدادات الافتراضية.
- Hook إلغاء التفعيل: لا يحذف البيانات (الحذف عبر `uninstall.php` اختياري).
- عند `plugins_loaded`: يهيّئ الإعدادات، يسجل REST routes، ويحمّل واجهة الإدارة.

### 2) `includes/helpers.php`
- `random_string($length)`: يولّد سلاسل آمنة عشوائية.
- `iso8601_now()`: يعيد توقيت UTC بصيغة ISO8601.
- `base64url_encode/ decode`: ترميز/فك ترميز Base64 URL-safe.
- `get_client_ip()`: يحاول كشف IP للعميل بأمان.
- `sanitize_slug($slug)`: تنظيف وتحويل slug.

### 3) `includes/class-spb-settings.php`
- إدارة إعدادات المكوّن في خيار واحد `spb_settings`.
- `bootstrap_defaults()`: ضبط القيم الافتراضية (تفعيل API، Webhook Secret، المعدّل، …).
- `all()/get()/set()`: قراءة/تحديث الإعدادات.

### 4) `includes/class-spb-database.php`
- تعريف أسماء الجداول: مفاتيح API، سجلات الطلبات، الصفحات المنشأة، سجلات Webhook.
- `activate()`: إنشاء الجداول عبر `dbDelta` مع فهارس مفيدة.
- عمليات على مفاتيح API:
  - `insert_api_key()`: إدراج مفتاح جديد وتخزين hash + fingerprint فقط (آمن).
  - `get_api_key_by_fingerprint()/get_api_key_by_id()`.
  - `touch_api_key()`, `increment_request_count()`, `set_api_key_status()`, `get_api_keys()`.
- التسجيل:
  - `log_request()`: يسجل كل طلب (النتيجة، الكود، IP، UA، …).
  - `log_webhook()`: يسجل نتيجة إرسال Webhook (الكود، المحاولات، …).
  - `record_created_pages()`: يحفظ ربط الصفحات المنشأة بـ API Key.
- الاستعلام لواجهة الإدارة:
  - `query_logs()` و`query_created_pages()` مع فلاتر.

### 5) `includes/class-spb-logger.php`
- طبقة بسيطة تستدعي `Database::log_*` لتوحيد التسجيل.

### 6) `includes/class-spb-rate-limiter.php`
- `check_and_increment($api_key_id, $limit)`: يعتمد على `transient` لعدّ الطلبات خلال نافذة ساعة لكل مفتاح. يرجع الحالة وعدّاد الباقي وتوقيت إعادة الضبط.

### 7) `includes/class-spb-auth.php`
- رؤوس المصادقة لوضع API Key: `X-SPB-API-Key` و `X-SPB-API-Secret`.
- `authenticate_request($request)`:
  - إن كان وضع JWT مفعّلًا: يقرأ `Authorization: Bearer` أو `X-SPB-JWT`، يتحقق بـ HS256 باستخدام `webhook_secret`، ويتأكد من `ak_fp = sha256(API Key)` وصلاحية `exp`، ثم يتحقق من حالة المفتاح (نشط/منتهي/ملغى).
  - إن كان وضع API Key: يتحقق من hash لكل من المفتاح والسر المخزّنين، ومن الحالة والانتهاء.
- `jwt_decode()`: فك JWT HS256 والتحقق من التوقيع/الانتهاء.

### 8) `includes/class-spb-webhook.php`
- `send_pages_created($request_id, $api_key_row, $pages)`:
  - يبني payload قياسي مع `event = pages_created`.
  - يوقّع الجسم بـ HMAC-SHA256 باستخدام `webhook_secret` في ترويسة `X-Webhook-Signature`.
  - محاولتان لإعادة الإرسال بانتظار متزايد.
  - يسجّل نتيجة الإرسال في جدول `spb_webhook_logs`.

### 9) `includes/class-spb-rest-controller.php`
- تسجيل المسار: `POST /pagebuilder/v1/create-pages`.
- `handle_create_pages()`:
  1. يولّد `request_id` ويسحب IP/UA.
  2. مصادقة (API Key أو JWT حسب الإعداد).
  3. تحديد المعدّل (Rate Limit) وإيقاف إذا تجاوز.
  4. قراءة `pages[]` من JSON والتحقق.
  5. إنشاء `page` لكل عنصر مع `wp_insert_post` وربط Meta `_spb_created_by_api`.
  6. حفظ ربط الصفحات المنشأة + تحديث نشاط المفتاح.
  7. التسجيل في السجلات.
  8. إرسال Webhook.
  9. يعيد استجابة 201 أو 207 (نجاح جزئي) أو أخطاء مناسبة.

### 10) `admin/class-spb-admin.php`
- يضيف صفحة "Page Builder" تحت أدوات.
- تبويبات: `keys`, `logs`, `pages`, `settings`, `docs`.
- معالجات النماذج:
  - `handle_generate_key()`: يولّد مفتاحًا/سرًا ويعرضهما مرة واحدة (باستعمال transient).
  - `handle_revoke_key()`: إلغاء/استعادة مفتاح.
  - `handle_save_settings()`: حفظ إعدادات (Webhook URL، السر، المعدّل، …).
  - `handle_export_logs()`: تصدير CSV للسجلات.
  - `handle_generate_jwt()`: مولّد JWT للمشرف (للسهولة).

### 11) `admin/views/*.php`
- `keys.php`: نموذج توليد المفتاح وجداول عرض المفاتيح وحالاتها.
- `logs.php`: فلاتر عرض السجلات وتصدير CSV.
- `pages.php`: قائمة الصفحات المنشأة مع روابطها.
- `settings.php`: إعدادات المكوّن + مولّد JWT داخل اللوحة.
- `docs.php`: توثيق قابل للقراءة داخل اللوحة (مسار API، أمثلة cURL، …).

### 12) `uninstall.php`
- إذا `delete_data_on_uninstall` مفعّل في الإعدادات، يحذف جداول وخيارات المكوّن عند إزالة التثبيت.

## Webhook (مختصر بالعربي)
- طريقة: POST إلى العنوان المضبوط في الإعدادات.
- الترويسات: `Content-Type: application/json` و`X-Webhook-Signature` (توقيع HMAC-SHA256 للجسم باستخدام `webhook_secret`).
- يحاول الإرسال حتى 3 مرات (المحاولة الأولى + محاولتان).
- يجب الرد 2xx بسرعة؛ نفّذ الأعمال الثقيلة لاحقًا.

## JWT (مختصر بالعربي)
- فعّل JWT من الإعدادات إن أردت استخدام Bearer.
- التوقيع: HS256 باستخدام `webhook_secret` نفسه.
- الحمولة: يجب أن تحتوي `ak_fp = sha256(API Key)` و`exp` (وقت انتهاء Unix).
- أرسل الترويسة: `Authorization: Bearer <jwt>` أو `X-SPB-JWT: <jwt>`.
- السر (Secret Key) المعروض عند توليد المفتاح لا يُستخدم في وضع JWT (يُستخدم فقط في وضع API Key + Secret).

## الأمان
- تخزين آمن: Hash لـ API Key/Secret (بدون نص صريح) + Fingerprint للبحث.
- تحديد معدّل الطلبات لكل مفتاح عبر `transient`.
- توقيع Webhook عبر HMAC-SHA256.
- سجلات كاملة للطلبات، والصفحات المنشأة، وحالات Webhook.

## نصائح
- إن غيّرت `Webhook Secret`، أعد توليد رموز JWT وتحديث مستهلك Webhook.
- إن رُفض الطلب بسبب الوقت، تأكد من تزامن الساعة و`exp`.
- يمكن تعديل معدل الطلب من تبويب Settings.

بالتوفيق! إذا رغبت بدليل أعمق سطرًا بسطر، أخبرنا بأول ملف تريد بدء الشرح التفصيلي له.


# تقرير التثبيت من مصدر نظيف

تاريخ التحقق: 28 أكتوبر 2026. أُنشئت نسخة مصدر جديدة وقاعدة MySQL منفصلة؛
لم تُنسخ vendor أو node_modules أو .env أو ملفات cache/runtime من التطبيق الحالي.
الاختبار على نفس جهاز Windows وأدوات PHP/Node/MySQL الحالية، وليس على نظام تشغيل جديد
أو جهاز آخر. قد تستفيد أدوات الحزم من caches الجهاز، لكن مجلدي الاعتماديات أُنشئا من الصفر.

## العزل

- مسار النسخة: `D:/New folder/dorra-clean-install-20261028-163606`.
- القاعدة: `dorra_install_verify_20261028_163606`؛ أُنشئت جديدة دون حذف قاعدة سابقة.
- إعداد .env مشتق من .env.example مع اسم القاعدة المنفصلة وAPP_URL على المنفذ 8011؛
  تم توليد APP_KEY جديد. استُخدمت بيانات اتصال MySQL المحلية دون نشرها في الأدلة.
- المكتبة الحالية على 8000 وقاعدتها لم تُستخدم لاختبارات CRUD الخاصة بالتثبيت.
- أُبقيت نسخة الاختبار وقاعدتها للمراجعة، ولم تُحذفا. سيرفر 8011 مخصص للاختبار فقط.

## الخطوات والنتائج

| الخطوة | النتيجة | الدليل |
| --- | --- | --- |
| composer install من lockfile | ناجح، exit 0 | [السجل](evidence/clean-composer-install.txt) |
| npm ci من lockfile | ناجح، exit 0 | [السجل](evidence/clean-npm-install.txt) |
| key:generate وmigrate وdb:seed | ناجح دون reset | [سجل Artisan](evidence/clean-artisan-setup.txt) |
| DesignSeeder وstorage:link | 100,000 تصميم والرابط صالح | [فحص القاعدة](evidence/clean-database-verification.json) |
| توزيع صور الملابس | ناجح على القاعدة المنفصلة | [السجل](evidence/clean-clothing-assignment.txt) |
| مجموعة الاختبارات | 57 اختبارًا و286 assertions ناجحة | [السجل](evidence/clean-tests.txt) |
| PHPStan level 7 | لا أخطاء | [السجل](evidence/clean-static-analysis.txt) |
| Vite build | ناجح | [السجل](evidence/clean-frontend-build.txt) |

البيانات النهائية: 100,000 تصميم، 10 أقسام، حسابان تجريبيان (Admin وViewer)،
50,000 عنوان عربي و50,000 إنجليزي وصفر orphan designs. الحساب الثالث في قاعدة
التطوير الأصلية ليس جزءًا من Seeder المطلوب؛ عدم وجوده في التثبيت الجديد متوقع.

## تحقق HTTP وPlaywright

- الدخول للحسابين نجح، والجدول عرض 24 صفًا.
- الأدمن يصل إلى Users وDraft؛ Viewer يتلقى 403، ولا يظهر له فلتر Status.
- CRUD للمستخدمين: إنشاء وعرض وتعديل وحذف عبر HTTP على MySQL المعزولة.
- CRUD للتصميمات: رفع JPEG مطلوب، وعرضه بـ200، وتعديل دون استبدال، وحذف السجل والملف.
- انتهت الاختبارات بحذف السجلات التجريبية المؤقتة؛ عادت الأعداد إلى 100,000 تصميم وحسابين.
- تحقق Playwright من عنوان Dorra Design Library و100 ألف تصميم وظهور صورة ملابس.

الأدلة: [نتائج HTTP](evidence/clean-http-smoke.json)،
[لقطة التثبيت الجديد](evidence/clean-install-dashboard.png).
اختبار أولي توقع 404 لرابط ملف محذوف، بينما أعاد سيرفر Windows التطويري 403؛
أُعيد التحقق من غياب الملف فعليًا وأُعيدت دورة CRUD بنجاح، كما هو مسجل في JSON.

## ملاحظات الحزم

- composer audit: لا advisories مرصودة وقت الفحص، exit 0، [النتيجة](evidence/composer-audit.json).
- npm audit: 21 finding (4 moderate، 14 high، 3 critical)، exit 1،
  [البيانات الخام](evidence/npm-audit.json). من الاعتماديات المباشرة axios وvite.
- لم تُشغّل ترقيات تلقائية أو تغييرات lockfile لإخفاء الملاحظات. يلزم فرزها وتحديث
  الحزم المناسبة وإعادة الاختبار قبل اعتماد النسخة للنشر الإنتاجي.
- التثبيت والاختبارات نجحا؛ هذا لا يعني خلو الاعتماديات من ثغرات أو اكتمال مراجعة أمنية شاملة.

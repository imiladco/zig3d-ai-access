# ZIG3D AI Access — لایهٔ Schema.org

افزونهٔ وردپرسِ اختصاصیِ [zig3d.com](https://zig3d.com) که JSON-LD صفحات را
خودش می‌سازد — مستقل از افزونهٔ سئوی نصب‌شده و هماهنگ با ساختارِ دادهٔ
همین سایت (ووکامرس، JetEngine، و ویجت‌هایِ اختصاصیِ زیگ).

## چرا جدا از افزونهٔ سئو

Rank Math برایِ هر صفحه schema تولید می‌کند، ولی از ساختارِ دادهٔ این سایت
خبر ندارد: نرم‌افزارها یک CPTِ JetEngine‌اند، قیمت‌ها به تومان ذخیره می‌شوند
(و باید ×۱۰ به ریالِ ISO تبدیل شوند)، و بخشی از محتوا داخلِ ویجت‌هایِ
اختصاصی است. این افزونه رویِ صفحاتی که پوشش می‌دهد **کلِ** خروجیِ رنک‌مث را
خاموش می‌کند و جایش یک گرافِ کامل و درست می‌گذارد.

## قواعدِ ثابت

- هر صفحه **دقیقاً یک** `<script type="application/ld+json">` و **یک** `@graph`.
- فیلدی که داده‌اش معتبر نیست **حذف** می‌شود — هیچ‌وقت مقدارِ جایگزین/ساختگی.
- همهٔ `@id`ها فقط از `Support\EntityIds` ساخته می‌شوند.
- هر حذفِ گره/بخش دلیلش در `Diagnostics` ثبت می‌شود.

## ساختار

```
zig3d-ai-access.php   بوت‌استرپ + اتولودرِ PSR-4 (بدونِ composer)
config.php            تنها منبعِ ثابت‌هایِ خاصِ این نصب (فیلترپذیر)
src/
  Config.php           خوانندهٔ دات‌نوتیشنِ config
  Diagnostics.php      شکستِ قابل‌مشاهده — بی‌صدا در تولید
  Schema_Provider.php  قراردادِ هر ماژولِ صفحه
  Route.php            رجیستری + «این مسیر پوشش داده شده؟»
  Graph.php            جمع‌کنندهٔ @graph، تنها جایی که echo می‌کند
  RankMath/            خاموش‌کردنِ schemaِ خودکارِ رنک‌مث
  Nodes/               گره‌هایِ قابلِ‌استفادهٔ مجدد (Organization، WebSite، WebPage)
  Schema/              یک Provider به‌ازایِ هر صفحه، خودثبت‌شونده
  Support/             توابعِ کمکیِ خالص (EntityIds، LoopGrid، ElementorReader)
  Cli/                 wp zig3d dump-element <id>
```

### افزودنِ یک صفحهٔ جدید

یک فایلِ تازه در `src/Schema/` بگذارید که `Schema_Provider` را پیاده کند و
در انتهایِ همان فایل خودش را ثبت کند:

```php
add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new MyProvider();

    return $providers;
});
```

`Plugin` پوشه را می‌گردد و بارگذاری می‌کند — هیچ فهرستِ هاردکدی از کلاس‌ها
جایی نیست.

## دیباگ

با `WP_DEBUG` یا ثابتِ `ZIG3D_AI_DEBUG`، هر گره‌ای که حذف شده باشد هم در
`error_log` می‌آید و هم به‌شکلِ کامنتِ HTML کنارِ `<script>`:

```html
<!-- zig3d-ai-access: home.featured-products skipped — … -->
```

در تولید کاملاً بی‌صداست.

## سفارشی‌سازی

کلِ `config.php` از فیلترِ `zig3d_ai_access/config` عبور می‌کند، پس یک
`functions.php` می‌تواند بدونِ دست‌زدن به افزونه هر مقداری را عوض کند:

```php
add_filter('zig3d_ai_access/config', function (array $config): array {
    $config['money']['output_currency'] = 'IRR';

    return $config;
});
```

## وضعیت

زیرساخت کامل است. از ماژول‌هایِ صفحه فعلاً `HomeProvider` پیاده شده؛ بقیهٔ
صفحات (فروشگاه، دانلود، بلاگ) هنوز Provider ندارند و رویِ آن‌ها فقط
`Organization`/`WebSite`ی سراسری چاپ می‌شود.

## موردنیاز

WordPress 6.0+ · PHP 7.4+

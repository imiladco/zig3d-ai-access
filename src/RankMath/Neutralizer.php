<?php
namespace Zig3d_AI_Access\RankMath;

use Zig3d_AI_Access\Route;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * خاموش‌کردنِ schemaِ خودکارِ Rank Math — «همه‌چیز رویِ مسیرهایِ
 * پوشش‌داده‌شده»، نه فهرست‌کردنِ نوع‌ها.
 *
 * نسخهٔ قبلی برایِ هر صفحه یک آرایهٔ ‎strip‎ از نوع‌ها نگه می‌داشت.
 * هر نوعی که در آن فهرست جا افتاده بود (مثلِ ‎Place‎/‎ImageObject‎ی
 * رنک‌مث رویِ صفحهٔ اصلی) زنده می‌ماند و صفحه دو ‎<script>‎ می‌شد — یک
 * کلاسِ باگِ ذاتاً تکرارشونده («یادم رفت این نوع را هم فهرست کنم»).
 *
 * تصمیمِ این‌جا: رویِ هر مسیری که ‎Route::is_covered()‎ می‌گوید ما
 * schemaِ کامل داریم، **کلِ خروجیِ رنک‌مث** حذف می‌شود — چون رویِ آن
 * صفحه ما جایگزینِ کاملیم، نگه‌داشتنِ هیچ گرهی از رنک‌مث توجیه ندارد.
 * صفر احتمالِ گرهِ جامانده، برای همیشه.
 *
 * ‎Organization‎/‎WebSite‎/‎Corporation‎ چون سراسری‌اند (رویِ *هر* صفحه‌ای
 * از سمتِ این افزونه چاپ می‌شوند، حتی صفحاتی که هیچ ‎Provider‎ی
 * پوششش نمی‌دهد)، جداگانه از *کلِ* سایت حذف می‌شوند — نه فقط مسیرهایِ
 * پوشش‌داده‌شده.
 */
final class Neutralizer {

    public static function boot(): void {
        add_filter('rank_math/json_ld', [self::class, 'filter'], 99, 2);
    }

    /**
     * @param mixed $data
     * @param mixed $jsonld امضایِ فیلترِ خودِ رنک‌مث؛ این‌جا لازم نیست
     * @return mixed
     */
    public static function filter($data, $jsonld = null) {
        if (Route::is_covered()) {
            return [];
        }

        return self::strip_global_types($data);
    }

    /** @param mixed $data */
    private static function strip_global_types($data) {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $entry) {
            if (is_array($entry) && self::is_global_type($entry)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /** @param array<string,mixed> $entry */
    private static function is_global_type(array $entry): bool {
        $type = $entry['@type'] ?? null;

        foreach (is_array($type) ? $type : [$type] as $candidate) {
            if (is_string($candidate) && in_array($candidate, ['Organization', 'Corporation', 'WebSite'], true)) {
                return true;
            }
        }

        return false;
    }
}

<?php
namespace Zig3d_AI_Access\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تنها سازندهٔ ‎@id‎هایِ کلِ افزونه (اصل ۵ی سندِ معماری).
 *
 * قبل از این، هر ‎Node‎ خودش رشتهٔ ‎@id‎ش را دستی می‌ساخت — یعنی الگویِ
 * ‎#organization‎/‎#website‎/‎{url}#product‎ در چند فایل تکرار شده بود،
 * با ریسکِ ناهماهنگی (مثلاً یک‌جا ‎trailingslashit‎ یادش می‌رفت). حالا
 * فقط این‌جاست؛ هر ‎Node‎ی دیگر همین متدها را صدا می‌زند.
 */
final class EntityIds {

    public static function organization(): string {
        return trailingslashit(home_url('/')) . '#organization';
    }

    public static function website(): string {
        return trailingslashit(home_url('/')) . '#website';
    }

    public static function webpage(string $url): string {
        return untrailingslashit($url) . '#webpage';
    }

    public static function product(string $url): string {
        return untrailingslashit($url) . '#product';
    }

    public static function software(string $url): string {
        return untrailingslashit($url) . '#software';
    }

    public static function article(string $url): string {
        return untrailingslashit($url) . '#article';
    }

    public static function category(string $slug): string {
        return trailingslashit(home_url('/')) . '#category-' . $slug;
    }

    /**
     * برند — سراسری و اسلاگ‌محور، نه وابسته به صفحه: یک برند رویِ
     * صفحهٔ محصول، آرشیوِ دسته و صفحهٔ اصلی باید *همان* موجودیت باشد،
     * وگرنه هر صفحه یک برندِ جدا اعلام می‌کند.
     */
    public static function brand(string $slug): string {
        return trailingslashit(home_url('/')) . '#brand-' . $slug;
    }

    public static function breadcrumb(string $url): string {
        return untrailingslashit($url) . '#breadcrumb';
    }

    public static function faq(string $url): string {
        return untrailingslashit($url) . '#faq';
    }

    /** لیستِ آیتم‌هایِ یک صفحه — ‎$suffix‎ برایِ صفحه‌بندی (‎products-page-2‎) */
    public static function item_list(string $url, string $suffix): string {
        return untrailingslashit($url) . '#' . $suffix;
    }

    /**
     * تصویر — خودِ آدرسِ فایل. عمدی: یک تصویر ممکن است در چند صفحه
     * بیاید و باید همه‌جا همان یک موجودیت باشد؛ آدرسش پایدارترین
     * شناسه‌ای است که دارد.
     */
    public static function image(string $url): string {
        return $url;
    }

    /** برایِ گره‌هایِ سراسر-صفحه‌ای بدونِ URLِ مخصوصِ خودشان (مثلِ ‎#featured-products‎) */
    public static function fragment(string $fragment): string {
        return trailingslashit(home_url('/')) . '#' . $fragment;
    }
}

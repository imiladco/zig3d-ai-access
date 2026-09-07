<?php
namespace Zig3d_AI_Access\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * قاعدهٔ صفحه‌بندیِ آرشیوها — یک‌جا، چون هر سه گروهِ آرشیو (فروشگاه،
 * دانلود، بلاگ) دقیقاً همین را لازم دارند و اشتباهش در هر کدام یک باگِ
 * جدا می‌شد.
 *
 * دو نکتهٔ راهنما که این‌جا تضمین می‌شوند:
 *  ۱) ‎position‎ در طولِ صفحات **ادامه‌دار** است — صفحهٔ ۲ از ۷ شروع
 *     می‌شود، نه دوباره از ۱.
 *  ۲) ‎numberOfItems‎ تعدادِ آیتم‌هایِ *همین صفحه* است، نه کلِ آرشیو.
 *     عددِ کل جایِ دیگری (‎CollectionPage‎) اعلام می‌شود.
 */
final class Archive {

    public static function paged(): int {
        $paged = (int) max(
            (int) get_query_var('paged'),
            (int) get_query_var('page')
        );

        return $paged > 0 ? $paged : 1;
    }

    /** @param \WP_Query|null $query */
    public static function per_page($query = null): int {
        $query = $query ?: self::query();

        if ($query && isset($query->query_vars['posts_per_page'])) {
            $per_page = (int) $query->query_vars['posts_per_page'];

            if ($per_page > 0) {
                return $per_page;
            }
        }

        return (int) get_option('posts_per_page', 10);
    }

    /** شمارهٔ اولین آیتمِ این صفحه: ‎(($paged - 1) * $per_page) + 1‎ */
    public static function first_position(int $paged, int $per_page): int {
        return (($paged - 1) * $per_page) + 1;
    }

    /** آدرسِ همین صفحه، با ‎?paged=N‎ی خودش — پایهٔ همهٔ ‎@id‎هایِ این صفحه */
    public static function current_url(string $base_url, int $paged): string {
        if ($paged <= 1) {
            return $base_url;
        }

        return add_query_arg('paged', $paged, $base_url);
    }

    /** پسوندِ ‎@id‎ی فهرست: صفحهٔ اول ساده، بقیه شماره‌دار */
    public static function list_suffix(string $base, int $paged): string {
        return $paged <= 1 ? $base : $base . '-page-' . $paged;
    }

    /** @return \WP_Query|null */
    public static function query() {
        global $wp_query;

        return $wp_query instanceof \WP_Query ? $wp_query : null;
    }

    /**
     * شناسهٔ پست‌هایِ همین صفحهٔ آرشیو — از کوئریِ *اصلیِ* همان صفحه،
     * نه یک کوئریِ تازه. راهنما صریح است: اگر تمپلیت از قبل کوئری دارد،
     * دوباره نساز.
     *
     * @return int[]
     */
    public static function current_ids(): array {
        $query = self::query();

        if (!$query || empty($query->posts)) {
            return [];
        }

        $ids = [];

        foreach ($query->posts as $post) {
            if (is_object($post) && isset($post->ID)) {
                $ids[] = (int) $post->ID;

                continue;
            }

            if (is_numeric($post)) {
                $ids[] = (int) $post;
            }
        }

        return $ids;
    }

    public static function found_posts(): int {
        $query = self::query();

        return $query ? (int) $query->found_posts : 0;
    }
}

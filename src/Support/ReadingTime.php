<?php
namespace Zig3d_AI_Access\Support;

use Zig3d_AI_Access\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * زمانِ مطالعه — تابعِ خالص، همان محاسبه‌ای که ویجتِ ‎zig3d-post-meta‎
 * رویِ صفحه نشان می‌دهد، فقط این‌بار به‌شکلِ ‎ISO 8601 Duration‎.
 *
 * «۲ دقیقه مطالعه» رویِ صفحه ← ‎"PT2M"‎ در schema. حداقل یک دقیقه:
 * ‎PT0M‎ برایِ مصرف‌کننده بی‌معناست.
 */
final class ReadingTime {

    public static function minutes(string $content): int {
        $text = trim(wp_strip_all_tags($content, true));

        if ('' === $text) {
            return 0;
        }

        // شمارشِ کلمه با پشتیبانیِ یونیکد — ‎str_word_count()‎ فارسی را نمی‌فهمد
        $words = preg_split('/[\s\x{200C}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $count = is_array($words) ? count($words) : 0;

        if ($count < 1) {
            return 0;
        }

        $wpm = (int) Config::get('blog.reading_wpm', 200);
        $wpm = $wpm > 0 ? $wpm : 200;

        return max(1, (int) ceil($count / $wpm));
    }

    /** ‎null‎ وقتی متنی نیست — به‌جایِ چاپِ ‎PT0M‎ی بی‌معنا */
    public static function duration(string $content): ?string {
        $minutes = self::minutes($content);

        return $minutes > 0 ? sprintf('PT%dM', $minutes) : null;
    }
}

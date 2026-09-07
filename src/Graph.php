<?php
namespace Zig3d_AI_Access;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * جمع‌کنندهٔ گرهٔ ‎@graph‎ برایِ همین یک درخواست.
 *
 * قاعدهٔ کلِ پروژه همین یک کلاس را توجیه می‌کند: «خروجی نهایی هر صفحه یک
 * ‎@graph‎ واحد در یک ‎<script>‎». هیچ ‎Provider‎/‎Node‎ی دیگری echo
 * نمی‌کند؛ فقط گره اضافه می‌کند، و فقط همین کلاس یک‌بار چاپ می‌کند —
 * دقیقاً همان چیزی که باگِ «صفحهٔ اصلی دو ‎<script>‎ داشت» را از اساس
 * غیرِممکن می‌کند (آن باگ از سمتِ رنک‌مث بود، نه این‌جا؛ نگاه کن به
 * ‎RankMath\Neutralizer‎).
 */
final class Graph {

    /** @var array<int,array<string,mixed>> */
    private static array $nodes = [];

    /** @param array<string,mixed>|null $node */
    public static function add(?array $node): void {
        if (null !== $node && [] !== $node) {
            self::$nodes[] = $node;
        }
    }

    /** @param array<int,array<string,mixed>|null> $nodes */
    public static function add_many(array $nodes): void {
        foreach ($nodes as $node) {
            self::add($node);
        }
    }

    public static function has_nodes(): bool {
        return [] !== self::$nodes;
    }

    /** @return array<int,array<string,mixed>> */
    public static function nodes(): array {
        return self::$nodes;
    }

    /** فقط برایِ تست — هر درخواستِ واقعی خودش یک پردازشِ تازه است */
    public static function reset(): void {
        self::$nodes = [];
    }

    /**
     * رشتهٔ نهایی — کامنت‌هایِ دیباگِ ‎Diagnostics‎ (اگر روشن بود) + یک
     * ‎<script>‎، یا رشتهٔ خالی اگر هیچ گرهی نبود.
     */
    public static function render(): string {
        $comments = Diagnostics::render_comments();

        if (!self::has_nodes()) {
            return $comments;
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => self::$nodes,
        ];

        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            return $comments;
        }

        return $comments . '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /**
     * چاپِ مستقیم — تنها جایی که این کلاس echo می‌کند.
     *
     * phpcs:ignore WordPress.Security.EscapeOutput -- محتوا JSON/کامنتِ
     * ساخته‌شده از wp_json_encode/رشته‌هایِ داخلیِ کد است، نه ورودیِ کاربر؛
     * esc_html این‌جا خروجی را برایِ پارسرهایِ JSON-LD خراب می‌کند.
     */
    public static function print_script(): void {
        echo self::render();
    }
}

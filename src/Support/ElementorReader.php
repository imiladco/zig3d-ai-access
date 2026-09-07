<?php
namespace Zig3d_AI_Access\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * خواننده‌یِ خامِ ‎_elementor_data‎ — تنها جایی در کلِ افزونه که JSONِ
 * المنتور را دستی می‌گردد. هر جای دیگری که به یک ویجتِ خاصِ المنتور نیاز
 * دارد (Loop Grid رویِ صفحهٔ اصلی، …) از همین کلاس استفاده می‌کند، نه
 * پارس‌کردنِ مجددِ متا.
 *
 * جایگزینِ ‎Elementor_Instance‎ی نسخهٔ قبلی — همان دو عملیات (پیداکردن با
 * ‎id‎، پیداکردن با ‎widgetType‎)، فقط بدونِ وابستگی به تنظیماتِ خاصِ
 * سایت (آن‌ها از ‎Config‎ می‌آیند، نه این‌جا).
 */
final class ElementorReader {

    /** @return array<int,array<string,mixed>> */
    public static function data(int $post_id): array {
        $raw = get_post_meta($post_id, '_elementor_data', true);

        if (!is_string($raw) || '' === $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** اولین المنت (در هر عمقی از تودرتوییِ section/column/widget) با این ‎id‎ */
    public static function find_by_id(int $post_id, string $element_id): ?array {
        return self::search_by_id(self::data($post_id), $element_id);
    }

    /** همهٔ المنت‌هایِ این ‎widgetType‎ (در هر عمقی) داخلِ این پست */
    public static function find_by_widget_type(int $post_id, string $widget_type): array {
        return self::search_by_type(self::data($post_id), $widget_type);
    }

    /**
     * @param array<int,mixed> $elements
     */
    private static function search_by_id(array $elements, string $id): ?array {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            if (($element['id'] ?? null) === $id) {
                return $element;
            }

            $children = $element['elements'] ?? null;

            if (is_array($children) && [] !== $children) {
                $found = self::search_by_id($children, $id);

                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int,mixed> $elements
     * @return array<int,array<string,mixed>>
     */
    private static function search_by_type(array $elements, string $widget_type): array {
        $out = [];

        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            if (($element['widgetType'] ?? null) === $widget_type) {
                $out[] = $element;
            }

            $children = $element['elements'] ?? null;

            if (is_array($children) && [] !== $children) {
                $out = array_merge($out, self::search_by_type($children, $widget_type));
            }
        }

        return $out;
    }
}

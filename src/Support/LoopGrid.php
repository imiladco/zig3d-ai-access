<?php
namespace Zig3d_AI_Access\Support;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * خواندنِ تنظیماتِ ویجتِ Loop Gridِ المنتور پرو — **سخت‌شده**: کلیدهایِ
 * تنظیماتِ خامِ المنتور نسخه‌به‌نسخه فرق می‌کنند و هیچ‌جا رسمی مستند
 * نیستند. این کلاس هیچ کلیدی را هاردکد نمی‌کند؛ فقط از
 * ‎config('loop_grid.*')‎ فهرستِ نامزدها را می‌خواند.
 *
 * دو راهِ *پیداکردنِ* ویجت:
 *  - ‎settings_by_id()‎: وقتی آی‌دیِ عنصر واقعاً پایدار و تأییدشده است.
 *  - ‎settings_by_widget_type()‎: وقتی آی‌دی را نمی‌شود مطمئن بود
 *    (نسخهٔ قبلی همین را حدس زده بود و رویِ سایتِ واقعی اشتباه از آب
 *    درآمد) — اولین ویجتی که ‎widgetType‎ش با یکی از نامزدهایِ
 *    ‎config('loop_grid.widget_types')‎ جور باشد انتخاب می‌شود.
 *
 * استخراجِ فیلدها (‎manual_ids‎/‎per_page‎/‎orderby‎/‎order‎) رویِ یک
 * آرایهٔ تنظیماتِ *از قبل پیداشده* کار می‌کند — مستقل از اینکه آن
 * تنظیمات با کدام‌یک از دو راهِ بالا پیدا شده.
 *
 * اگر هیچ نامزدی جواب نداد، بی‌صدا آرایهٔ خالی برنمی‌گرداند —
 * ‎Diagnostics::drop()‎ می‌گوید کدام کلیدها را امتحان کرد، تا اپراتور
 * بتواند با ‎wp zig3d dump-element <id>‎ کلیدِ واقعی را پیدا کند و به
 * ‎config.php‎ اضافه کند.
 */
final class LoopGrid {

    /** پیداکردنِ تنظیماتِ خامِ عنصر با یک ‎id‎ٍ ثابت و تأییدشده */
    public static function settings_by_id(int $post_id, string $element_id): ?array {
        $element = ElementorReader::find_by_id($post_id, $element_id);

        if (null === $element) {
            Diagnostics::drop(
                'loop-grid',
                sprintf('element id "%s" not found in _elementor_data of post #%d', $element_id, $post_id)
            );

            return null;
        }

        $settings = $element['settings'] ?? null;

        return is_array($settings) ? $settings : [];
    }

    /**
     * پیداکردنِ تنظیماتِ خامِ اولین ویجتی که ‎widgetType‎ش با یکی از
     * ‎$widget_types‎ جور باشد. برخلافِ ‎settings_by_id()‎ خودش
     * ‎Diagnostics::drop()‎ نمی‌زند — چون معمولاً چند پست/قالب پشتِ‌هم
     * امتحان می‌شود و فقط نتیجهٔ نهایی (بعد از تمامِ تلاش‌ها) ارزشِ
     * ثبت‌شدن دارد؛ آن ثبت به‌عهدهٔ صداکننده است.
     *
     * @param string[] $widget_types
     */
    public static function settings_by_widget_type(int $post_id, array $widget_types): ?array {
        foreach ($widget_types as $type) {
            $matches = ElementorReader::find_by_widget_type($post_id, (string) $type);

            if ([] !== $matches) {
                $settings = $matches[0]['settings'] ?? null;

                return is_array($settings) ? $settings : [];
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed>|null $settings از ‎settings_by_id()‎/‎settings_by_widget_type()‎
     * @return int[] شناسهٔ پست‌هایِ انتخاب‌شدهٔ دستی — خالی اگر ‎$settings‎
     * نال بود یا هیچ کلیدِ نامزدی مقدار نداشت (یعنی ویجت شاید کوئریِ
     * پویا دارد، نه انتخابِ دستی — تصمیمِ نهایی با ‎Provider‎ صداکننده است)
     */
    public static function manual_ids(?array $settings): array {
        if (null === $settings) {
            return [];
        }

        $keys = (array) Config::get('loop_grid.manual_id_keys', []);

        foreach ($keys as $key) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }

            $ids = self::normalize_ids($settings[$key]);

            if ([] !== $ids) {
                return $ids;
            }
        }

        Diagnostics::drop(
            'loop-grid',
            sprintf(
                'none of the configured manual_id_keys (%s) held a non-empty value in the resolved widget settings — run `wp zig3d dump-element <id>` to find the real key and add it to config.php',
                implode(', ', $keys)
            )
        );

        return [];
    }

    /** @param array<string,mixed>|null $settings */
    public static function per_page(?array $settings, int $default = 8): int {
        $value = self::first_scalar($settings, (array) Config::get('loop_grid.per_page_keys', []));

        return null !== $value && is_numeric($value) ? (int) $value : $default;
    }

    /** @param array<string,mixed>|null $settings */
    public static function orderby(?array $settings, string $default = 'date'): string {
        $value = self::first_scalar($settings, (array) Config::get('loop_grid.orderby_keys', []));

        return null !== $value && is_string($value) && '' !== $value ? $value : $default;
    }

    /** @param array<string,mixed>|null $settings */
    public static function order(?array $settings, string $default = 'DESC'): string {
        $value = self::first_scalar($settings, (array) Config::get('loop_grid.order_keys', []));

        return null !== $value && is_string($value) && '' !== $value ? strtoupper($value) : $default;
    }

    /**
     * @param array<string,mixed>|null $settings
     * @param string[] $keys
     * @return mixed
     */
    private static function first_scalar(?array $settings, array $keys) {
        if (null === $settings) {
            return null;
        }

        foreach ($keys as $key) {
            if (isset($settings[$key]) && '' !== $settings[$key]) {
                return $settings[$key];
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return int[]
     */
    private static function normalize_ids($value): array {
        if (is_array($value)) {
            $flat = [];

            foreach ($value as $item) {
                // المنتور گاهی id هارا به‌شکلِ ['id' => '123'] تودرتو می‌دهد
                if (is_array($item) && isset($item['id'])) {
                    $flat[] = $item['id'];
                } else {
                    $flat[] = $item;
                }
            }

            $value = $flat;
        } elseif (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        } else {
            return [];
        }

        $ids = array_map('intval', array_filter($value, static fn($v): bool => is_numeric($v)));

        return array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
    }
}

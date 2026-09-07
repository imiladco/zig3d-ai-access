<?php
namespace Zig3d_AI_Access;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * رجیستریِ ماژول‌ها + تشخیصِ «این مسیر پوشش داده شده؟».
 *
 * تنها منبعِ رجیستری همین‌جاست — ‎Plugin::print_schema()‎ و
 * ‎RankMath\Neutralizer‎ هر دو از همین یک تابع می‌خوانند، پس هیچ‌وقت
 * ممکن نیست یکی بگوید «پوشش داده شده» و دیگری «نه».
 */
final class Route {

    /** @var Schema_Provider[]|null */
    private static ?array $providers = null;

    /** @return Schema_Provider[] */
    public static function providers(): array {
        if (null === self::$providers) {
            $providers = (array) apply_filters('zig3d_ai_access/providers', []);

            self::$providers = array_values(array_filter(
                $providers,
                static fn($provider): bool => $provider instanceof Schema_Provider
            ));
        }

        return self::$providers;
    }

    /**
     * اولین ماژولی که ‎applies()‎ش true می‌دهد. شرط‌هایِ ‎applies()‎ در
     * وردپرس ذاتاً دوبه‌دو منحصربه‌فردند (‎is_front_page()‎/‎is_shop()‎/
     * ‎is_singular('product')‎/… هرگز هم‌زمان true نیستند)، پس «اولین»
     * همیشه هم‌زمان «تنها» هم هست.
     */
    public static function matching_provider(): ?Schema_Provider {
        foreach (self::providers() as $provider) {
            if ($provider->applies()) {
                return $provider;
            }
        }

        return null;
    }

    public static function is_covered(): bool {
        return null !== self::matching_provider();
    }

    /** فقط برایِ تست — هر درخواستِ واقعی خودش یک پردازشِ تازه است */
    public static function reset(): void {
        self::$providers = null;
    }
}

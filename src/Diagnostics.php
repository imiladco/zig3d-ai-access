<?php
namespace Zig3d_AI_Access;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * شکستِ قابل‌مشاهده — هستهٔ این بازطراحی.
 *
 * سه باگِ نسخهٔ قبلی (دو ‎<script>‎ رویِ صفحهٔ اصلی، محصولاتِ منتخبِ
 * بی‌سروصدا حذف‌شده، سؤالِ FAQِ نامعتبرِ منتشرشده) مشترکشان یک چیز بود:
 * **شکستِ بی‌صدا**. هر جایِ کد که یک گره/بخش را حذف می‌کند، به‌جایِ
 * سکوت، دلیلش را این‌جا ثبت می‌کند.
 *
 * در تولید (پیش‌فرض): کاملاً بی‌اثر — فقط یک آرایهٔ درون‌حافظه پر
 * می‌شود که هیچ‌جا چاپ نمی‌شود، صفر هزینهٔ قابل‌توجه. در حالتِ دیباگ
 * (‎WP_DEBUG‎ یا ثابتِ ‎ZIG3D_AI_DEBUG‎): هم در ‎error_log‎، هم به‌شکلِ
 * کامنتِ HTML کنارِ ‎<script>‎.
 */
final class Diagnostics {

    /** @var array<int,array{section:string,reason:string}> */
    private static array $log = [];

    public static function drop(string $section, string $reason): void {
        self::$log[] = ['section' => $section, 'reason' => $reason];

        if (self::enabled()) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('[zig3d-ai-access] dropped %s: %s', $section, $reason));
        }
    }

    public static function enabled(): bool {
        return (defined('WP_DEBUG') && WP_DEBUG) || (defined('ZIG3D_AI_DEBUG') && ZIG3D_AI_DEBUG);
    }

    /** @return array<int,array{section:string,reason:string}> */
    public static function log(): array {
        return self::$log;
    }

    /** یک درخواستِ واقعی این را صدا نمی‌زند — هر درخواست خودش پردازشِ تازه است؛ فقط برایِ تست/چند بار صدازدنِ ‎print_schema()‎ در یک پردازش */
    public static function reset(): void {
        self::$log = [];
    }

    /**
     * کامنت‌هایِ HTML — فقط در حالتِ دیباگ، یک خط به‌ازایِ هر گره‌ای که
     * حذف شد. خالی در تولید.
     */
    public static function render_comments(): string {
        if (!self::enabled() || !self::$log) {
            return '';
        }

        $out = '';

        foreach (self::$log as $entry) {
            $out .= sprintf(
                "<!-- zig3d-ai-access: %s skipped — %s -->\n",
                self::comment_safe($entry['section']),
                self::comment_safe($entry['reason'])
            );
        }

        return $out;
    }

    /**
     * توالیِ ‎--‎ داخلِ یک کامنتِ HTML آن را می‌شکند — این متن از رشته‌هایِ
     * خودِ کد می‌آید (نه ورودیِ کاربر)، ولی احتیاط ارزان‌تر از یک صفحهٔ
     * خراب‌شده است.
     */
    private static function comment_safe(string $text): string {
        return str_replace('--', '—', $text);
    }
}

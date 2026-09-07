<?php
namespace Zig3d_AI_Access\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ثبتِ قواعدِ بازنویسیِ فیچرها، با **یک** flushِ کنترل‌شده.
 *
 * چرا یک واسط و نه ‎add_rewrite_rule()‎ی مستقیم در هر فیچر: ‎flush_rewrite_rules()‎
 * گران است (بازتولید و ذخیرهٔ کلِ ‎rewrite_rules‎) و فراخوانی‌اش رویِ
 * هر درخواست یک اشتباهِ کلاسیکِ افزونه‌نویسی است. ولی اگر هرگز flush
 * نشود، endpointِ تازه ۴۰۴ می‌دهد. پس: امضا.
 *
 * امضا = هشِ مجموعِ قواعدِ ثبت‌شده. فقط وقتی این هش عوض می‌شود flush
 * می‌شود — یعنی دقیقاً وقتی که فیچری قاعده‌ای اضافه/حذف/عوض کرده.
 *
 * چرا این و نه فقط ‎register_activation_hook‎ (که سندِ معماری گفته):
 * هوکِ فعال‌سازی رویِ *به‌روزرسانیِ* افزونه اجرا نمی‌شود. این قاعده در
 * یک به‌روزرسانی اضافه می‌شود، پس رویِ نصبِ فعلی — که همین حالا فعال
 * است — هرگز اجرا نمی‌شد و ‎llms.txt‎ تا وقتی کاربر دستی غیرفعال/فعال
 * نکند ۴۰۴ می‌داد. امضا همان تضمینِ «فقط یک بار» را می‌دهد و این حالت
 * را هم می‌پوشاند. هوکِ فعال‌سازی هم برایِ نصبِ تازه سرِ جایش می‌ماند.
 */
final class Rewrite {

    private const OPTION = 'zig3d_ai_access_rewrite_signature';

    /** @var array<string,string> regex => query */
    private static array $rules = [];

    /** @var array<int,string> */
    private static array $vars = [];

    public static function boot(): void {
        // اولویتِ ۲۰: بعد از ‎boot()‎ی فیچرها (که رویِ ‎plugins_loaded‎ است) و
        // بعد از ثبتِ CPT/تاکسونومی‌هایِ اولویتِ پیش‌فرضِ ‎init‎
        add_action('init', [self::class, 'register'], 20);
        add_filter('query_vars', [self::class, 'query_vars']);
    }

    /**
     * @param string            $regex قاعدهٔ بازنویسی
     * @param string            $query مقصد، مثلِ ‎index.php?zig3d_llms=1‎
     * @param array<int,string> $vars  ‎query_var‎هایی که این قاعده تولید می‌کند
     */
    public static function add(string $regex, string $query, array $vars = []): void {
        self::$rules[$regex] = $query;

        foreach ($vars as $var) {
            self::$vars[] = $var;
        }
    }

    public static function register(): void {
        foreach (self::$rules as $regex => $query) {
            add_rewrite_rule($regex, $query, 'top');
        }

        $signature = md5(wp_json_encode(self::$rules) ?: '');

        if (get_option(self::OPTION) !== $signature) {
            // نرم: فقط قواعدِ داخلیِ وردپرس، بدونِ دست‌زدن به ‎.htaccess‎
            flush_rewrite_rules(false);

            update_option(self::OPTION, $signature, true);
        }
    }

    /**
     * @param  array<int,string> $vars
     * @return array<int,string>
     */
    public static function query_vars(array $vars): array {
        return array_values(array_unique(array_merge($vars, self::$vars)));
    }

    /** نصبِ تازه: هوکِ فعال‌سازی هم قواعد را جا می‌اندازد */
    public static function on_activation(): void {
        delete_option(self::OPTION);
    }

    /** فقط برایِ تست */
    public static function reset(): void {
        self::$rules = [];
        self::$vars  = [];
    }

    /** @return array<string,string> */
    public static function rules(): array {
        return self::$rules;
    }
}

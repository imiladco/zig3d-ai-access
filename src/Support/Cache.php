<?php
namespace Zig3d_AI_Access\Support;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * یک باسِ کشِ واحد — و مهم‌تر، یک نقطهٔ باطل‌سازیِ واحد.
 *
 * چرا نه «هر فیچر کشِ خودش را بسازد»: کشِ درست سخت نیست، *باطل‌سازیِ*
 * درست سخت است. اگر ‎llms.txt‎ کشِ خودش را داشته باشد و فردا Doctor هم
 * یکی بسازد، هر کدام باید مستقل بفهمند «محتوا عوض شد» — و یکی‌شان
 * فراموش می‌کند. پس فقط یک ‎invalidate_all()‎ وجود دارد.
 *
 * روشِ باطل‌سازی: **نسخه‌گذاری، نه پاک‌کردن.** یک عددِ نسخه در یک
 * ‎option‎ نگه داشته می‌شود و در همهٔ کلیدها می‌آید؛ ‎invalidate_all()‎
 * فقط آن عدد را یکی زیاد می‌کند. جایگزینش این بود که فهرستِ کلیدهایِ
 * فعال را جایی نگه داریم یا ‎DELETE … LIKE '_transient_zig3d%'‎ بزنیم —
 * اولی یک ساختارِ دادهٔ دیگر برایِ همگام‌نگه‌داشتن، دومی یک کوئریِ
 * بدونِ ایندکس رویِ ‎wp_options‎ در مسیرِ ذخیرهٔ پست. ترنزینت‌هایِ یتیم
 * خودشان با TTL منقضی می‌شوند.
 *
 * تصمیمِ عمدیِ پروژه که این‌جا هم پابرجاست: مسیرهایِ قیمت‌دار یا کش
 * نمی‌شوند یا TTLِ کوتاه دارند — قیمت ساعتی عوض می‌شود و یک قیمتِ
 * کهنه در خروجیِ ماشین‌خوان بدتر از نبودنش است.
 */
final class Cache {

    private const VERSION_OPTION = 'zig3d_ai_access_cache_version';
    private const PREFIX         = 'zig3d_aia_';

    /** طولِ مجازِ نامِ ترنزینت در وردپرس؛ کلیدهایِ ما خیلی کوتاه‌ترند، ولی صراحت ارزان است */
    private const MAX_KEY_LENGTH = 172;

    private static ?int $version = null;

    /**
     * هوک‌هایِ باطل‌سازی. از ‎Plugin‎ صدا زده می‌شود، نه از یک فیچر —
     * این زیرساخت است، نه یک قابلیتِ اختیاری.
     */
    public static function boot(): void {
        add_action('save_post', [self::class, 'on_save_post'], 10, 2);
        add_action('deleted_post', [self::class, 'invalidate_all']);
        add_action('edited_term', [self::class, 'invalidate_all']);
        add_action('created_term', [self::class, 'invalidate_all']);
        add_action('delete_term', [self::class, 'invalidate_all']);

        // تغییرِ خودِ تنظیماتِ افزونه هم محتوا را عوض می‌کند
        add_action('update_option_' . self::VERSION_OPTION . '_settings', [self::class, 'invalidate_all']);
        add_action('zig3d_ai_access/settings_changed', [self::class, 'invalidate_all']);
    }

    /**
     * ذخیرهٔ خودکار و بازنگری محتوایِ منتشرشده را عوض نمی‌کنند؛ باطل‌کردنِ
     * کش رویِ آن‌ها یعنی هر بار که ادمین در حالِ تایپ است کش می‌پرد.
     *
     * @param int|string        $post_id
     * @param \WP_Post|mixed    $post
     */
    public static function on_save_post($post_id, $post = null): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (function_exists('wp_is_post_revision') && wp_is_post_revision((int) $post_id)) {
            return;
        }

        self::invalidate_all();
    }

    public static function enabled(): bool {
        return (bool) Config::get('cache.enabled', false);
    }

    public static function default_ttl(): int {
        return max(0, (int) Config::get('cache.ttl', 0));
    }

    /**
     * @param  mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        if (!self::enabled()) {
            return $default;
        }

        $value = get_transient(self::transient_name($key));

        return false === $value ? $default : $value;
    }

    /** @param mixed $value */
    public static function set(string $key, $value, ?int $ttl = null): void {
        if (!self::enabled()) {
            return;
        }

        $ttl = null === $ttl ? self::default_ttl() : max(0, $ttl);

        if ($ttl <= 0) {
            // TTLِ صفر در وردپرس یعنی «هرگز منقضی نشو» — این‌جا یعنی
            // «کش نکن»، که خواستهٔ واقعیِ یک TTLِ تنظیم‌نشده است.
            Diagnostics::drop('cache', sprintf('key "%s" not stored: ttl is 0 (no-expiry caching is deliberately not offered)', $key));

            return;
        }

        set_transient(self::transient_name($key), $value, $ttl);
    }

    /**
     * الگویِ رایج: بخوان، اگر نبود بساز و بگذار.
     *
     * ‎null‎ی برگشتی از ‎$producer‎ کش **نمی‌شود** — یعنی «تولید شکست
     * خورد»، و کش‌کردنِ شکست یعنی تثبیتِ یک باگ تا انقضایِ TTL.
     *
     * @param  callable():mixed $producer
     * @return mixed
     */
    public static function remember(string $key, callable $producer, ?int $ttl = null) {
        $cached = self::get($key);

        if (null !== $cached) {
            return $cached;
        }

        $value = $producer();

        if (null !== $value) {
            self::set($key, $value, $ttl);
        }

        return $value;
    }

    public static function delete(string $key): void {
        delete_transient(self::transient_name($key));
    }

    /**
     * تنها راهِ باطل‌کردن. یک بار در هر درخواست کافی است — چند بار
     * صدازدنش (چند پستِ ذخیره‌شده در یک درخواست) بی‌ضرر ولی بی‌فایده
     * است، پس در همان درخواست بی‌اثر می‌شود.
     */
    public static function invalidate_all(): void {
        static $done = false;

        if ($done) {
            return;
        }

        $done = true;

        $next = self::version() + 1;

        update_option(self::VERSION_OPTION, $next, false);

        self::$version = $next;
    }

    public static function version(): int {
        if (null === self::$version) {
            self::$version = max(1, (int) get_option(self::VERSION_OPTION, 1));
        }

        return self::$version;
    }

    /** فقط برایِ تست */
    public static function reset(): void {
        self::$version = null;
    }

    /* ------------------------------------------------------------------ */

    /**
     * نامِ ترنزینت = پیشوند + نسخه + هشِ کلید. هش به این خاطر که کلیدها
     * می‌توانند شاملِ URL یا اسلاگِ فارسی باشند و طول/کاراکترشان قابلِ
     * پیش‌بینی نیست؛ کلیدِ خام هم برایِ خوانایی در ابتدایِ نام می‌آید.
     */
    private static function transient_name(string $key): string {
        $readable = preg_replace('/[^a-z0-9_]+/i', '_', $key) ?? '';
        $readable = trim(substr($readable, 0, 40), '_');

        $name = self::PREFIX . self::version() . '_' . $readable . '_' . substr(md5($key), 0, 12);

        return substr($name, 0, self::MAX_KEY_LENGTH);
    }
}

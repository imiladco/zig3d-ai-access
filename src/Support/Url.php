<?php
namespace Zig3d_AI_Access\Support;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نگهبانِ «URL عمومیِ مجاز» — تنها جایی که این مفهوم تعریف می‌شود.
 *
 * چرا لازم است: خروجی‌هایِ ماشین‌خوانِ این فاز (‎llms.txt‎، ‎IndexNow‎)
 * فهرستی از URLها هستند که مستقیم به بیرون می‌روند — به مدل‌ها، به
 * Bing، به کشِ موتورها. اگر سایت رویِ استیج بوت شود، یا یک لینکِ
 * ‎wp-admin‎/پیش‌نمایش از یک متایِ دستی بیرون بیاید، آن URL بی‌سروصدا
 * منتشر می‌شود و پس‌گرفتنش دستِ ما نیست. پس یک دروازهٔ واحد:
 *
 *   $url = Url::canonical_or_null( get_permalink( $id ) );
 *   if ( null === $url ) { continue; }   // هرگز حدس نزن، فقط ردش کن
 *
 * قاعدهٔ سخت: **هیچ خروجیِ ماشین‌خوانِ عمومی نباید URLی چاپ کند که از
 * این متد عبور نکرده باشد.** گرافِ schema از این مسیر عبور نمی‌کند —
 * آن‌جا ‎@id‎ها fragment دارند و عمداً همان permalinkِ محلی‌اند؛ این
 * دروازه مخصوصِ چیزی است که *ما* به بیرون push می‌کنیم.
 */
final class Url {

    /** مسیرهایِ داخلیِ وردپرس که هرگز نباید در خروجیِ عمومی بیایند */
    private const BLOCKED_PATH_PARTS = [
        '/wp-admin',
        '/wp-login.php',
        '/wp-json',
        '/xmlrpc.php',
        '/wp-cron.php',
        '/wp-signup.php',
        '/wp-activate.php',
    ];

    /**
     * پارامترهایی که یعنی «این نسخهٔ کانونیکالِ صفحه نیست» — پیش‌نمایش،
     * جست‌وجو، سبدِ خرید، شناسهٔ خام به‌جایِ permalink.
     */
    private const NON_CANONICAL_QUERY_KEYS = [
        'preview',
        'preview_id',
        'preview_nonce',
        'p',
        'page_id',
        'post_type',
        's',
        'replytocom',
        'add-to-cart',
        'unapproved',
        'moderation-hash',
        'customize_changeset_uuid',
        'elementor-preview',
    ];

    /**
     * هاست‌هایی که هرگز تولید نیستند، حتی اگر کسی اشتباهاً در config
     * بنویسدشان. این فهرست ‎is_production()‎ را در برابرِ یک config
     * غلط هم مقاوم می‌کند.
     */
    private const NEVER_PRODUCTION_HOSTS = [
        'localhost',
        'example.com',
        'example.org',
        'example.net',
        '127.0.0.1',
        '::1',
    ];

    /** پسوند/پیشوندهایِ آشنایِ محیط‌هایِ غیرتولید */
    private const NEVER_PRODUCTION_SUFFIXES = ['.test', '.local', '.localhost', '.invalid', '.example'];
    private const NEVER_PRODUCTION_PREFIXES = ['staging.', 'stage.', 'dev.', 'test.', 'demo.', 'preview.'];

    private static ?string $production_host = null;

    /**
     * هاستِ تولید — از config؛ اگر خالی بود از ‎home_url()‎.
     *
     * چرا config مقدم است: رویِ یک نصبِ استیج، ‎home_url()‎ خودش هاستِ
     * استیج را می‌دهد، پس اگر تنها معیارمان ‎home_url()‎ باشد هر URLی
     * «کانونیکال» به‌نظر می‌رسد و دقیقاً همان نشتی رخ می‌دهد که این
     * کلاس برایِ جلوگیری از آن ساخته شده.
     */
    public static function production_host(): string {
        if (null !== self::$production_host) {
            return self::$production_host;
        }

        $configured = self::host_of((string) Config::get('site.production_url', ''));

        if ('' === $configured) {
            $configured = self::host_of((string) home_url('/'));

            Diagnostics::drop(
                'url.production_host',
                'site.production_url is not configured — falling back to home_url(); on a staging install this cannot detect stage URLs'
            );
        }

        return self::$production_host = $configured;
    }

    /**
     * آیا این URL رویِ دامنهٔ تولیدِ واقعی است؟
     *
     * فقط دربارهٔ *هاست* حرف می‌زند؛ دربارهٔ کانونیکال‌بودنِ مسیر نه —
     * آن کارِ ‎canonical_or_null()‎ است.
     */
    public static function is_production(string $url): bool {
        $host = self::host_of($url);

        if ('' === $host) {
            return false;
        }

        foreach (self::NEVER_PRODUCTION_HOSTS as $bad) {
            if ($host === $bad) {
                return false;
            }
        }

        foreach (self::NEVER_PRODUCTION_SUFFIXES as $suffix) {
            if (self::ends_with($host, $suffix)) {
                return false;
            }
        }

        foreach (self::NEVER_PRODUCTION_PREFIXES as $prefix) {
            if (0 === strpos($host, $prefix)) {
                return false;
            }
        }

        return self::same_host($host, self::production_host());
    }

    /**
     * دروازهٔ اصلی: URLِ کانونیکالِ عمومی، یا ‎null‎.
     *
     * ‎null‎ یعنی «این را منتشر نکن» — نه «بعداً درستش کن». هیچ‌وقت یک
     * URLی نیمه‌درست را بازنویسی و منتشر نمی‌کند؛ تنها تغییرِ مجاز
     * حذفِ fragment است، چون ‎#section‎ سمتِ کلاینت است و همان سندِ
     * کانونیکال را نشان می‌دهد.
     */
    public static function canonical_or_null(string $url, string $section = 'url'): ?string {
        $url = trim($url);

        if ('' === $url) {
            return null;
        }

        $parts = wp_parse_url($url);

        if (!is_array($parts) || empty($parts['host'])) {
            Diagnostics::drop($section, sprintf('URL is not absolute or could not be parsed: %s', $url));

            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ('http' !== $scheme && 'https' !== $scheme) {
            Diagnostics::drop($section, sprintf('URL scheme "%s" is not http(s): %s', $scheme, $url));

            return null;
        }

        // اعتبارنامه داخلِ URL هرگز نباید منتشر شود
        if (isset($parts['user']) || isset($parts['pass'])) {
            Diagnostics::drop($section, 'URL carries embedded credentials — dropped');

            return null;
        }

        // پورتِ غیراستاندارد تقریباً همیشه یعنی محیطِ توسعه
        if (isset($parts['port']) && !in_array((int) $parts['port'], [80, 443], true)) {
            Diagnostics::drop($section, sprintf('URL uses non-standard port %d: %s', (int) $parts['port'], $url));

            return null;
        }

        if (!self::is_production($url)) {
            Diagnostics::drop($section, sprintf('host "%s" is not the production host "%s": %s', (string) $parts['host'], self::production_host(), $url));

            return null;
        }

        $path = (string) ($parts['path'] ?? '/');

        foreach (self::BLOCKED_PATH_PARTS as $blocked) {
            if (0 === strpos($path, $blocked) || false !== strpos($path, $blocked . '/')) {
                Diagnostics::drop($section, sprintf('URL points at WordPress internals (%s): %s', $blocked, $url));

                return null;
            }
        }

        $query = (string) ($parts['query'] ?? '');

        if ('' !== $query) {
            $args = [];
            parse_str($query, $args);

            foreach (self::NON_CANONICAL_QUERY_KEYS as $key) {
                if (array_key_exists($key, $args)) {
                    Diagnostics::drop($section, sprintf('URL is a non-canonical variant (query arg "%s"): %s', $key, $url));

                    return null;
                }
            }
        }

        // fragment حذف می‌شود، نه اینکه URL رد شود: همان سند است
        return self::rebuild($parts);
    }

    /**
     * نسخهٔ فهرستی — همان دروازه، رویِ یک آرایه. خروجی بدونِ تکرار و
     * بازچینش‌شده، تا صداکننده نگرانِ کلیدهایِ خالی نباشد.
     *
     * @param  array<int,string> $urls
     * @return array<int,string>
     */
    public static function canonical_list(array $urls, string $section = 'url'): array {
        $out = [];

        foreach ($urls as $url) {
            $canonical = self::canonical_or_null((string) $url, $section);

            if (null !== $canonical) {
                $out[] = $canonical;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * ریشهٔ عمومیِ سایت رویِ هاستِ تولید — پایهٔ ساختِ ‎llms.txt‎ و
     * endpointِ تأییدِ IndexNow. اگر ‎home_url()‎ خودش کانونیکال نبود
     * (نصبِ استیج) ‎null‎ می‌دهد، تا آن endpointها هم ساکت بمانند.
     */
    public static function site_root(): ?string {
        return self::canonical_or_null((string) home_url('/'), 'url.site_root');
    }

    /** فقط برایِ تست — یک درخواستِ واقعی هیچ‌وقت این را صدا نمی‌زند */
    public static function reset(): void {
        self::$production_host = null;
    }

    /* ------------------------------------------------------------------ */

    private static function host_of(string $url): string {
        if ('' === trim($url)) {
            return '';
        }

        $host = wp_parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    /**
     * ‎www.‎ فرقِ معناداری نیست — ‎zig3d.com‎ و ‎www.zig3d.com‎ یک سایت‌اند
     * و کدامشان کانونیکال است کارِ ریدایرکتِ سرور/سئو است، نه این کلاس.
     */
    private static function same_host(string $a, string $b): bool {
        return self::strip_www($a) === self::strip_www($b) && '' !== $a;
    }

    private static function strip_www(string $host): string {
        return 0 === strpos($host, 'www.') ? substr($host, 4) : $host;
    }

    private static function ends_with(string $haystack, string $needle): bool {
        $len = strlen($needle);

        return 0 !== $len && substr($haystack, -$len) === $needle;
    }

    /** @param array<string,mixed> $parts */
    private static function rebuild(array $parts): string {
        $url = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']);

        $url .= (string) ($parts['path'] ?? '/');

        if (!empty($parts['query'])) {
            $url .= '?' . (string) $parts['query'];
        }

        return $url;
    }
}

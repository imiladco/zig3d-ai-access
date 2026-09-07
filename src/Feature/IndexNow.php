<?php
namespace Zig3d_AI_Access\Feature;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Feature_Module;
use Zig3d_AI_Access\Support\Rewrite;
use Zig3d_AI_Access\Support\Url;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * IndexNow — اعلامِ فوریِ تغییرِ محتوا به موتورها.
 *
 * ── قاعدهٔ اولِ این فایل، که بقیه‌اش را شکل می‌دهد ──
 *
 * **هرگز نباید انتشارِ یک پست را کند یا خراب کند.**
 *
 * وسوسهٔ ساده این است که در ‎transition_post_status‎ یک ‎wp_remote_post‎
 * بزنیم. آن‌وقت هر بار که ادمین «انتشار» را می‌زند، درخواستش پشتِ یک
 * HTTPی بیرونی می‌ماند؛ و روزی که ‎api.indexnow.org‎ کند یا قطع باشد،
 * دکمهٔ انتشارِ سایت هم کند یا قطع می‌شود. یک قابلیتِ *جانبی* هرگز
 * نباید مسیرِ اصلیِ کار را گروگان بگیرد.
 *
 * پس مسیر سه‌مرحله‌ای است و هیچ‌کدام مسدودکننده نیست:
 *
 *   رویداد → صفِ درون‌حافظه → (‎shutdown‎) صفِ ماندگار + زمان‌بندی
 *                            → (دقایقی بعد، خارج از درخواستِ کاربر) ارسال
 *
 * تأخیرِ عمدیِ ‎debounce‎ یک فایدهٔ دوم هم دارد: ویرایشِ پشتِ‌هم یک پست
 * در چند دقیقه به *یک* ارسال تبدیل می‌شود، نه ده تا.
 *
 * ── حقیقتِ وابستگی (باید صریح بماند) ──
 *
 * IndexNow یک **push** است؛ ما به موتور خبر می‌دهیم، پس فایروالِ هاست
 * جلویش را نمی‌گیرد. ولی خبردادن یعنی موتور *بعداً می‌آید بخزد* — و
 * اگر همان فایروال به خزندهٔ موتور ۴۰۳ بدهد، نمایه‌سازی کامل نمی‌شود.
 * یعنی IndexNow کمک می‌کند ولی گلوگاهِ واقعی همچنان WAF است. این
 * افزونه آن را حل نمی‌کند و ادعایش را هم نمی‌کند؛ ‎wp zig3d doctor‎
 * همین را می‌گوید.
 *
 * ── و یک چیزی که این‌جا ساخته نمی‌شود ──
 *
 * sitemap. Rank Math ارائه‌دهندهٔ sitemap است و می‌ماند. IndexNow
 * مکملِ sitemap است نه جایگزینش.
 *
 * پروتکل از مستنداتِ رسمیِ ‎indexnow.org‎ راستی‌آزمایی شده: کلید ۸ تا
 * ۱۲۸ نویسه، فایلِ ‎{key}.txt‎ در ریشه که محتوایش خودِ کلید است، و
 * POSTی JSON با ‎host/key/keyLocation/urlList‎ (حداکثر ۱۰۰۰۰ نشانی).
 * پاسخِ ۲۰۰ و ۲۰۲ هر دو موفق‌اند.
 */
final class IndexNow implements Feature_Module {

    public const QUERY_VAR   = 'zig3d_indexnow_key';
    public const CRON_HOOK   = 'zig3d_ai_access_indexnow_submit';
    private const KEY_OPTION   = 'zig3d_ai_access_indexnow_key';
    private const QUEUE_OPTION = 'zig3d_ai_access_indexnow_queue';

    /** سقفِ رسمیِ پروتکل برایِ هر درخواست */
    private const PROTOCOL_MAX_URLS = 10000;

    /** @var array<int,string> صفِ همین درخواست */
    private static array $pending = [];

    public function boot(): void {
        if (!self::enabled()) {
            return;
        }

        // فایلِ تأییدِ مالکیت — یک endpointِ مجازی، نه فایلی رویِ دیسک
        $key = self::key();

        if ('' !== $key) {
            Rewrite::add('^' . preg_quote($key, '#') . '\.txt$', 'index.php?' . self::QUERY_VAR . '=1', [self::QUERY_VAR]);
            add_action('template_redirect', [$this, 'maybe_render_key'], 0);
        }

        add_action('transition_post_status', [$this, 'on_post_transition'], 10, 3);
        add_action('edited_term', [$this, 'on_term_changed'], 10, 3);
        add_action('created_term', [$this, 'on_term_changed'], 10, 3);
        add_action('delete_term', [$this, 'on_term_deleted'], 10, 4);

        add_action('shutdown', [$this, 'flush_pending'], 999);
        add_action(self::CRON_HOOK, [$this, 'submit_queue']);
    }

    public static function enabled(): bool {
        return (bool) Config::get('indexnow.enabled', true);
    }

    /* -------------------------------------------------------------- کلید */

    /**
     * کلید یک بار ساخته و در یک ‎option‎ می‌ماند. عوض‌شدنش یعنی فایلِ
     * تأییدِ قبلی بی‌اعتبار می‌شود، پس هرگز خودکار بازتولید نمی‌شود.
     */
    public static function key(): string {
        $key = (string) get_option(self::KEY_OPTION, '');

        if (self::valid_key($key)) {
            return $key;
        }

        $key = self::generate_key();

        if ('' === $key) {
            Diagnostics::drop('indexnow', 'could not generate a key — feature inert');

            return '';
        }

        update_option(self::KEY_OPTION, $key, true);

        return $key;
    }

    /** ۸ تا ۱۲۸ نویسه از ‎a-zA-Z0-9-‎، طبقِ اسپک */
    private static function valid_key(string $key): bool {
        return (bool) preg_match('/^[A-Za-z0-9-]{8,128}$/', $key);
    }

    private static function generate_key(): string {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $fallback = strtolower((string) wp_generate_password(32, false, false));

            return self::valid_key($fallback) ? $fallback : '';
        }
    }

    public static function key_url(): ?string {
        $root = Url::site_root();
        $key  = (string) get_option(self::KEY_OPTION, '');

        if (null === $root || !self::valid_key($key)) {
            return null;
        }

        return trailingslashit($root) . $key . '.txt';
    }

    public function maybe_render_key(): void {
        if (!get_query_var(self::QUERY_VAR)) {
            return;
        }

        $key = (string) get_option(self::KEY_OPTION, '');

        if (!self::valid_key($key)) {
            status_header(404);
            exit;
        }

        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');

        echo $key; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خودِ کلید، از قبل با regex اعتبارسنجی شده

        exit;
    }

    /* ------------------------------------------------------------ رویداد */

    /**
     * فقط گذارهایِ *معنادار*. یک ذخیرهٔ خودکار یا یک پیش‌نویس هیچ
     * تغییری در وبِ عمومی نداده و خبردادنش فقط سهمیه را می‌سوزاند.
     *
     * @param string        $new
     * @param string        $old
     * @param \WP_Post|mixed $post
     */
    public function on_post_transition($new, $old, $post): void {
        try {
            if (!$post instanceof \WP_Post) {
                return;
            }

            $new = (string) $new;
            $old = (string) $old;

            // نه انتشارِ تازه/به‌روزرسانیِ منتشرشده، نه خروج از انتشار → بی‌ربط
            $entering = 'publish' === $new;
            $leaving  = 'publish' === $old && 'publish' !== $new;

            if (!$entering && !$leaving) {
                return;
            }

            if (!$this->post_is_eligible($post)) {
                return;
            }

            $url = $this->post_url($post);

            if (null !== $url) {
                self::enqueue($url);
            }
        } catch (\Throwable $e) {
            // مسیرِ انتشار هرگز نباید به‌خاطرِ این فیچر بشکند
            Diagnostics::drop('indexnow', sprintf('post transition handler failed: %s', $e->getMessage()));
        }
    }

    private function post_is_eligible(\WP_Post $post): bool {
        $post_id = (int) $post->ID;

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return false;
        }

        if ('' !== (string) $post->post_password) {
            Diagnostics::drop('indexnow', sprintf('post #%d is password protected — not public content', $post_id));

            return false;
        }

        // «قابلِ‌مشاهده» یعنی نوعِ پستی که واقعاً یک نشانیِ عمومی دارد
        if (!is_post_type_viewable((string) $post->post_type)) {
            return false;
        }

        if ('' === (string) $post->post_name) {
            // هرگز اسلاگِ عمومی نداشته (پیش‌نویسی که مستقیم به زباله رفته)
            return false;
        }

        return true;
    }

    /**
     * نشانیِ عمومیِ پست.
     *
     * نکتهٔ ظریف: وقتی پستی از انتشار *خارج* می‌شود،
     * ‎get_permalink()‎ دیگر نشانیِ زیبا نمی‌دهد بلکه ‎?p=123‎ برمی‌گرداند
     * — که دروازهٔ ‎Url‎ (به‌درستی) ردش می‌کند، و نتیجه‌اش این می‌شد که
     * هیچ‌وقت حذفِ محتوا اعلام نشود. پس وضعیتِ یک *کپی* از پست موقتاً
     * ‎publish‎ گرفته می‌شود تا همان نشانی‌ای ساخته شود که تا لحظهٔ قبل
     * عمومی بود. خودِ پست دست نمی‌خورد.
     */
    private function post_url(\WP_Post $post): ?string {
        $for_link = $post;

        if ('publish' !== $post->post_status) {
            $for_link = clone $post;
            $for_link->post_status = 'publish';
        }

        $link = get_permalink($for_link);

        if (!is_string($link) || '' === $link) {
            return null;
        }

        return Url::canonical_or_null($link, 'indexnow.post');
    }

    /**
     * @param int|string $term_id
     * @param int|string $tt_id
     * @param string     $taxonomy
     */
    public function on_term_changed($term_id, $tt_id = 0, $taxonomy = ''): void {
        $this->enqueue_term((int) $term_id, (string) $taxonomy);
    }

    /**
     * @param int|string $term_id
     * @param int|string $tt_id
     * @param string     $taxonomy
     * @param mixed      $deleted
     */
    public function on_term_deleted($term_id, $tt_id = 0, $taxonomy = '', $deleted = null): void {
        try {
            // ترم دیگر وجود ندارد، پس ‎get_term_link()‎ کار نمی‌کند؛ از
            // شیءِ حذف‌شده‌ای که خودِ وردپرس پاس می‌دهد استفاده می‌شود
            if ($deleted instanceof \WP_Term) {
                $link = get_term_link($deleted);

                if (!is_wp_error($link) && is_string($link)) {
                    $url = Url::canonical_or_null($link, 'indexnow.term');

                    if (null !== $url) {
                        self::enqueue($url);
                    }
                }
            }
        } catch (\Throwable $e) {
            Diagnostics::drop('indexnow', sprintf('term deletion handler failed: %s', $e->getMessage()));
        }
    }

    private function enqueue_term(int $term_id, string $taxonomy): void {
        try {
            if ($term_id <= 0 || '' === $taxonomy) {
                return;
            }

            $object = get_taxonomy($taxonomy);

            if (!$object || empty($object->public)) {
                return;
            }

            $link = get_term_link($term_id, $taxonomy);

            if (is_wp_error($link) || !is_string($link)) {
                return;
            }

            $url = Url::canonical_or_null($link, 'indexnow.term');

            if (null !== $url) {
                self::enqueue($url);
            }
        } catch (\Throwable $e) {
            Diagnostics::drop('indexnow', sprintf('term change handler failed: %s', $e->getMessage()));
        }
    }

    /* --------------------------------------------------------------- صف */

    public static function enqueue(string $url): void {
        if (!in_array($url, self::$pending, true)) {
            self::$pending[] = $url;
        }
    }

    /** @return array<int,string> */
    public static function pending(): array {
        return self::$pending;
    }

    /**
     * پایانِ درخواست: صفِ درون‌حافظه به صفِ ماندگار می‌رود و یک اجرایِ
     * آینده زمان‌بندی می‌شود. هیچ HTTPی این‌جا زده نمی‌شود.
     */
    public function flush_pending(): void {
        try {
            if (!self::$pending) {
                return;
            }

            $queue = self::queue();
            $queue = array_values(array_unique(array_merge($queue, self::$pending)));

            self::$pending = [];

            $max = max(1, (int) Config::get('indexnow.max_queue', 500));

            if (count($queue) > $max) {
                // صفِ بی‌انتها یعنی یک ‎option‎ی متورم وقتی کرون خراب است
                $dropped = count($queue) - $max;
                $queue   = array_slice($queue, -$max);

                Diagnostics::drop('indexnow', sprintf('queue exceeded %d URLs — dropped the %d oldest (is WP-Cron running?)', $max, $dropped));
            }

            update_option(self::QUEUE_OPTION, $queue, false);

            $this->schedule();
        } catch (\Throwable $e) {
            Diagnostics::drop('indexnow', sprintf('queue flush failed: %s', $e->getMessage()));
        }
    }

    /**
     * تأخیر عمدی است: ویرایشِ پشتِ‌هم در همان بازه به یک ارسال تبدیل
     * می‌شود. اگر از قبل زمان‌بندی شده، دوباره زمان‌بندی نمی‌شود.
     */
    private function schedule(): void {
        $delay = max(0, (int) Config::get('indexnow.debounce', 60));

        if (function_exists('as_schedule_single_action') && function_exists('as_next_scheduled_action')) {
            if (!as_next_scheduled_action(self::CRON_HOOK)) {
                as_schedule_single_action(time() + $delay, self::CRON_HOOK);
            }

            return;
        }

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + $delay, self::CRON_HOOK);
        }
    }

    /** @return array<int,string> */
    public static function queue(): array {
        $queue = get_option(self::QUEUE_OPTION, []);

        return is_array($queue) ? array_values(array_filter(array_map('strval', $queue))) : [];
    }

    /* ------------------------------------------------------------- ارسال */

    /**
     * خارج از درخواستِ کاربر اجرا می‌شود — این تنها جایی است که HTTPی
     * بیرونی زده می‌شود.
     */
    public function submit_queue(): void {
        try {
            $queue = self::queue();

            if (!$queue) {
                return;
            }

            $root = Url::site_root();

            if (null === $root) {
                Diagnostics::drop('indexnow', 'not running on the canonical production host — queue cleared without submitting');
                delete_option(self::QUEUE_OPTION);

                return;
            }

            $key      = self::key();
            $key_url  = self::key_url();

            if ('' === $key || null === $key_url) {
                Diagnostics::drop('indexnow', 'no valid key — nothing submitted');

                return;
            }

            $batch = min(self::PROTOCOL_MAX_URLS, max(1, (int) Config::get('indexnow.batch', 1000)));
            $send  = array_slice($queue, 0, $batch);

            // صف *قبل* از ارسال کوتاه می‌شود: اگر ارسال شکست بخورد،
            // دوباره‌فرستادنِ بی‌پایانِ همان دسته بدتر از ازدست‌رفتنش است
            $rest = array_slice($queue, count($send));

            if ($rest) {
                update_option(self::QUEUE_OPTION, $rest, false);
                $this->schedule();
            } else {
                delete_option(self::QUEUE_OPTION);
            }

            $payload = wp_json_encode([
                'host'        => (string) wp_parse_url($root, PHP_URL_HOST),
                'key'         => $key,
                'keyLocation' => $key_url,
                'urlList'     => array_values($send),
            ]);

            if (!is_string($payload)) {
                Diagnostics::drop('indexnow', 'payload could not be encoded');

                return;
            }

            foreach ((array) Config::get('indexnow.endpoints', []) as $endpoint) {
                $this->post($endpoint, $payload, count($send));
            }
        } catch (\Throwable $e) {
            Diagnostics::drop('indexnow', sprintf('submission failed: %s', $e->getMessage()));
        }
    }

    private function post(string $endpoint, string $payload, int $count): void {
        $endpoint = trim($endpoint);

        if ('' === $endpoint || 0 !== strpos($endpoint, 'https://')) {
            Diagnostics::drop('indexnow', sprintf('endpoint "%s" is not an https URL — skipped', $endpoint));

            return;
        }

        $response = wp_remote_post($endpoint, [
            'timeout'     => 10,
            'redirection' => 2,
            'headers'     => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'        => $payload,
        ]);

        if (is_wp_error($response)) {
            Diagnostics::drop('indexnow', sprintf('%s did not respond: %s', $endpoint, $response->get_error_message()));

            return;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        // طبقِ اسپک ۲۰۰ (پذیرفته) و ۲۰۲ (دریافت شد، اعتبارسنجی در جریان) هر دو موفق‌اند
        if (200 === $code || 202 === $code) {
            return;
        }

        Diagnostics::drop('indexnow', sprintf('%s returned HTTP %d for %d URLs (403=key not found, 422=host/key mismatch, 429=rate limited)', $endpoint, $code, $count));
    }
}

add_filter('zig3d_ai_access/features', static function (array $features): array {
    $features[] = new IndexNow();

    return $features;
});

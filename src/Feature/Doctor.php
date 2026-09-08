<?php
namespace Zig3d_AI_Access\Feature;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Feature_Module;
use Zig3d_AI_Access\Route;
use Zig3d_AI_Access\Support\Blog;
use Zig3d_AI_Access\Support\Downloads;
use Zig3d_AI_Access\Support\Url;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ‎wp zig3d doctor‎ — چک‌آپِ عملیاتیِ لایهٔ AI-access.
 *
 * چرا CLI و نه یک داشبوردِ ادمین: این چیزی نیست که کسی هر روز نگاهش
 * کند؛ چیزی است که وقتی «انگار چیزی درست کار نمی‌کند» اجرا می‌شود.
 * یک دستور که در ترمینال جواب می‌دهد و خروجی‌اش قابلِ‌کپی در یک
 * گزارش است، از یک صفحهٔ رنگیِ ادمین عملیاتی‌تر است.
 *
 * ── مهم‌ترین کاری که این دستور می‌کند ──
 *
 * **fetchِ واقعی با UAی واقعیِ خزنده.** بقیهٔ چک‌ها پیکربندی را
 * می‌سنجند؛ این یکی *واقعیت* را می‌سنجد. نوشتنِ ‎Allow: /‎ در
 * ‎robots.txt‎ هیچ چیزی را باز نمی‌کند — اگر فایروالِ هاست
 * (Imunify360/ModSecurity) به همان UA ۴۰۳ بدهد، خزنده هرگز داخل
 * نمی‌آید و کاربر ماه‌ها فکر می‌کند مشکل حل شده. تنها راهِ فهمیدنش
 * زدنِ یک درخواستِ واقعی با همان UA است، و این دستور دقیقاً همان را
 * می‌کند و کدِ وضعیت را خام گزارش می‌دهد.
 *
 * منطقِ تحلیل عمداً از منطقِ واکشی جداست: ‎analyze_*‎ توابعِ خالص‌اند
 * (ورودی: متن؛ خروجی: یافته‌ها) تا بدونِ شبکه هم قابلِ‌آزمون باشند.
 */
final class Doctor implements Feature_Module {

    public const OK   = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';
    public const INFO = 'info';

    /** امضایِ ‎<script>‎ی خودِ رنک‌مث */
    private const RANK_MATH_SIGNATURE = 'rank-math-schema';

    /** نشانه‌هایِ متنِ جانگهدار که هرگز نباید به تولید برسند */
    private const PLACEHOLDERS = [
        'lorem ipsum', 'placeholder', 'your-site', 'yoursite', 'example.com',
        'todo', 'fixme', 'changeme', 'xxxxx', 'test-value',
    ];

    /** کلیدهایی که مقدارشان باید تاریخِ معتبرِ ISO 8601 باشد */
    private const DATE_KEYS = [
        'datePublished', 'dateModified', 'dateCreated', 'uploadDate',
        'releaseDate', 'foundingDate', 'validFrom', 'validThrough',
        'priceValidUntil', 'startDate', 'endDate',
    ];

    public function boot(): void {
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('zig3d doctor', [self::class, 'run']);
        }

        /*
         * صفحهٔ ادمین همان چک‌ها را با دکمه اجرا می‌کند. رویِ هاستِ
         * اشتراکیِ این سایت WP-CLI در دسترس نیست، و یک ابزارِ تشخیصی
         * که نمی‌شود اجرایش کرد یعنی ابزارِ تشخیصی نداریم.
         */
        if (is_admin() && (bool) Config::get('doctor.admin_page', true)) {
            \Zig3d_AI_Access\Admin\DoctorPage::boot();
        }
    }

    /* ---------------------------------------------------------------- CLI */

    /**
     * ## OPTIONS
     *
     * [--skip-remote]
     * : هیچ درخواستِ HTTPی نزن — فقط پیکربندی را بسنج. برایِ محیط‌هایی
     *   که خروجیِ شبکه ندارند.
     *
     * [--format=<format>]
     * : ‎table‎ (پیش‌فرض) یا ‎json‎.
     *
     * ## EXAMPLES
     *
     *     wp zig3d doctor
     *     wp zig3d doctor --skip-remote
     *     wp zig3d doctor --format=json
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc_args
     */
    public static function run(array $args = [], array $assoc_args = []): void {
        $results = self::checks(!isset($assoc_args['skip-remote']));

        if ('json' === ($assoc_args['format'] ?? 'table')) {
            \WP_CLI::line((string) wp_json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $icons  = [self::OK => '✔', self::WARN => '▲', self::FAIL => '✘', self::INFO => 'ℹ'];
        $counts = [self::OK => 0, self::WARN => 0, self::FAIL => 0, self::INFO => 0];
        $group  = '';

        foreach ($results as $result) {
            $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;

            if ($group !== $result['group']) {
                $group = $result['group'];
                \WP_CLI::line('');
                \WP_CLI::line('── ' . $group);
            }

            \WP_CLI::line(sprintf('  %s %s', $icons[$result['status']] ?? '?', $result['label']));

            if ('' !== $result['detail']) {
                \WP_CLI::line('      ' . $result['detail']);
            }
        }

        \WP_CLI::line('');

        $summary = sprintf(
            '%d سالم، %d هشدار، %d خطا',
            $counts[self::OK],
            $counts[self::WARN],
            $counts[self::FAIL]
        );

        if ($counts[self::FAIL] > 0) {
            \WP_CLI::error($summary, false);
        } elseif ($counts[self::WARN] > 0) {
            \WP_CLI::warning($summary);
        } else {
            \WP_CLI::success($summary);
        }
    }

    /* ------------------------------------------------------------ چک‌ها */

    /** @return array<int,array{group:string,status:string,label:string,detail:string}> */
    public static function checks(bool $remote = true): array {
        $out = [];

        $out = array_merge($out, self::check_config());
        $out = array_merge($out, self::check_providers());

        if (!$remote) {
            $out[] = self::result('شبکه', self::INFO, 'چک‌هایِ راه‌دور رد شدند (‎--skip-remote‎)', 'وضعیتِ واقعیِ llms.txt، robots و فایروال بررسی نشد.');

            return $out;
        }

        $out = array_merge($out, self::check_llms());
        $out = array_merge($out, self::check_robots());
        $out = array_merge($out, self::check_crawler_access());
        $out = array_merge($out, self::check_pages());

        return $out;
    }

    /* ------------------------------------------------------- پیکربندی */

    /** @return array<int,array<string,string>> */
    private static function check_config(): array {
        $group = 'پیکربندی';
        $out   = [];

        $root = Url::site_root();

        $out[] = null === $root
            ? self::result($group, self::FAIL, 'هاستِ تولید با آدرسِ فعلیِ سایت نمی‌خواند', sprintf('‎site.production_url‎ = %s، ولی ‎home_url()‎ = %s. تا این حل نشود llms.txt و IndexNow عمداً ساکت می‌مانند.', (string) Config::get('site.production_url', '—'), (string) home_url('/')))
            : self::result($group, self::OK, 'هاستِ تولید تأیید شد', $root);

        // نوعِ پستِ نرم‌افزارها
        $post_type = Downloads::post_type();

        $out[] = '' === $post_type
            ? self::result($group, self::FAIL, 'نوعِ پستِ «نرم‌افزارها» حل نشد', sprintf('نه از شناسهٔ JetEngineی %d و نه از اسلاگِ «%s». همهٔ صفحاتِ دانلود بدونِ schema می‌مانند.', (int) Config::get('downloads.jetengine_cpt_id', 0), (string) Config::get('downloads.post_type', '')))
            : self::result($group, self::OK, 'نوعِ پستِ نرم‌افزارها حل شد', $post_type);

        // تاکسونومی‌هایی که واقعاً استفاده می‌شوند
        $taxonomies = [
            'downloads.taxonomy' => (string) Config::get('downloads.taxonomy', ''),
        ];

        foreach ((array) Config::get('llms.sections', []) as $section) {
            if (is_array($section) && 'taxonomy' === ($section['source'] ?? '')) {
                $taxonomies['llms: ' . (string) ($section['title'] ?? '?')] = (string) ($section['taxonomy'] ?? '');
            }
        }

        foreach ($taxonomies as $where => $taxonomy) {
            if ('' === $taxonomy) {
                continue;
            }

            $out[] = taxonomy_exists($taxonomy)
                ? self::result($group, self::OK, sprintf('تاکسونومیِ «%s» ثبت شده', $taxonomy), $where)
                : self::result($group, self::FAIL, sprintf('تاکسونومیِ «%s» رویِ این سایت ثبت نشده', $taxonomy), sprintf('در %s پیکربندی شده ولی وجود ندارد — آن بخش بی‌صدا خالی می‌ماند.', $where));
        }

        // کلیدهایِ ویجتِ گرید
        $widget_types = array_filter((array) Config::get('loop_grid.widget_types', []));

        $out[] = $widget_types
            ? self::result($group, self::OK, 'کلیدهایِ ویجتِ گرید پیکربندی شده‌اند', implode('، ', array_map('strval', $widget_types)))
            : self::result($group, self::FAIL, 'هیچ ‎loop_grid.widget_types‎ی پیکربندی نشده', 'فهرست‌هایِ «محصولاتِ منتخب» هرگز پیدا نمی‌شوند.');

        // IndexNow
        if (IndexNow::enabled()) {
            $key_url = IndexNow::key_url();

            $out[] = null === $key_url
                ? self::result($group, self::WARN, 'کلیدِ IndexNow هنوز ساخته نشده', 'با اولین انتشار ساخته می‌شود.')
                : self::result($group, self::OK, 'کلیدِ IndexNow موجود است', $key_url);

            $queue = count(IndexNow::queue());

            if ($queue > 0) {
                $out[] = self::result($group, self::WARN, sprintf('%d نشانی در صفِ IndexNow مانده', $queue), 'اگر این عدد کم نمی‌شود، احتمالاً WP-Cron اجرا نمی‌شود.');
            }
        }

        return $out;
    }

    /** @return array<int,array<string,string>> */
    private static function check_providers(): array {
        $group     = 'ماژول‌هایِ schema';
        $providers = Route::providers();
        $scopes    = array_map(static fn($p): string => $p->rank_math_scope(), $providers);

        $expected = [
            'home', 'shop-home', 'shop-category', 'shop-product',
            'downloads-home', 'downloads-category', 'downloads-software',
            'blog-home', 'blog-category', 'blog-single',
        ];

        $missing = array_values(array_diff($expected, $scopes));

        $out = [
            self::result($group, self::OK, sprintf('%d ماژول ثبت شده', count($providers)), implode('، ', $scopes)),
        ];

        if ($missing) {
            $out[] = self::result($group, self::FAIL, 'ماژولِ گم‌شده', implode('، ', $missing) . ' — این صفحات هیچ schemaیی نمی‌گیرند و رنک‌مث هم رویشان خاموش نمی‌شود.');
        }

        $duplicates = array_values(array_filter(array_count_values($scopes), static fn(int $n): bool => $n > 1));

        if ($duplicates) {
            $out[] = self::result($group, self::WARN, 'بیش از یک ماژول برایِ یک مسیر', 'اولین ماژولِ منطبق برنده می‌شود؛ بقیه هرگز اجرا نمی‌شوند.');
        }

        return $out;
    }

    /* ---------------------------------------------------------- llms.txt */

    /** @return array<int,array<string,string>> */
    private static function check_llms(): array {
        $group = 'llms.txt';

        if (!Llms::enabled()) {
            return [self::result($group, self::INFO, 'غیرفعال است', '‎llms.enabled = false‎')];
        }

        $url = Llms::url();

        if (null === $url) {
            return [self::result($group, self::FAIL, 'نشانیِ کانونیکالی ندارد', 'به چکِ «هاستِ تولید» نگاه کن.')];
        }

        $response = self::fetch($url);

        if (null === $response) {
            return [self::result($group, self::FAIL, 'پاسخی نداد', $url)];
        }

        return self::analyze_llms($url, $response['code'], $response['content_type'], $response['body']);
    }

    /**
     * تحلیلِ خالص — بدونِ شبکه، تا قابلِ‌آزمون باشد.
     *
     * @return array<int,array<string,string>>
     */
    public static function analyze_llms(string $url, int $code, string $content_type, string $body): array {
        $group = 'llms.txt';
        $out   = [];

        $out[] = 200 === $code
            ? self::result($group, self::OK, 'وضعیتِ ۲۰۰', $url)
            : self::result($group, self::FAIL, sprintf('وضعیتِ %d به‌جایِ ۲۰۰', $code), '۴۰۴ معمولاً یعنی قواعدِ بازنویسی flush نشده‌اند — ‎wp rewrite flush‎.');

        $expected = (string) Config::get('llms.content_type', 'text/plain; charset=utf-8');

        $out[] = false !== stripos($content_type, 'text/plain')
            ? self::result($group, self::OK, 'نوعِ محتوا متنِ ساده است', $content_type)
            : self::result($group, self::WARN, 'نوعِ محتوا متنِ ساده نیست', sprintf('«%s» دریافت شد، «%s» انتظار می‌رفت. اسپک نوعی را الزام نکرده ولی مرورگر ممکن است دانلودش کند.', $content_type, $expected));

        $trimmed = ltrim($body);

        $out[] = 0 === strpos($trimmed, '# ')
            ? self::result($group, self::OK, 'با ‎H1‎ شروع می‌شود (تنها بخشِ الزامیِ اسپک)', '')
            : self::result($group, self::FAIL, 'با ‎H1‎ شروع نمی‌شود', 'اسپکِ llmstxt.org یک ‎# عنوان‎ در ابتدا الزام می‌کند.');

        $sections = preg_match_all('/^## /m', $body);
        $links    = preg_match_all('/^- \[/m', $body);

        $out[] = $sections > 0 && $links > 0
            ? self::result($group, self::OK, sprintf('%d بخش، %d لینک', $sections, $links), '')
            : self::result($group, self::WARN, 'هیچ بخش یا لینکی ندارد', 'همهٔ بخش‌ها خالی درآمده‌اند — با ‎WP_DEBUG‎ دلیلش در لاگ می‌آید.');

        if ($links > 200) {
            $out[] = self::result($group, self::WARN, sprintf('%d لینک — این دیگر کاتالوگ است نه فهرستِ منتخب', $links), 'سقفِ ‎llms.sections[].limit‎ را کم کن.');
        }

        return array_merge($out, self::scan_text($group . ' — نشتِ نشانی', $body));
    }

    /* ----------------------------------------------------------- robots */

    /** @return array<int,array<string,string>> */
    private static function check_robots(): array {
        $group = 'robots.txt';
        $root  = Url::site_root();

        if (null === $root) {
            return [];
        }

        $out = [];

        if (file_exists(ABSPATH . 'robots.txt')) {
            $out[] = self::result($group, self::FAIL, 'یک ‎robots.txt‎ی فیزیکی در ریشه هست', 'فایلِ واقعی فیلترِ وردپرس را کاملاً دور می‌زند، پس سیاستِ این افزونه اصلاً سرو نمی‌شود. فایل را پاک کن یا قواعد را دستی داخلش بگذار.');
        }

        $response = self::fetch(trailingslashit($root) . 'robots.txt');

        if (null === $response) {
            return array_merge($out, [self::result($group, self::WARN, 'پاسخی نداد', '')]);
        }

        $body   = $response['body'];
        $policy = self::parse_robots($body);

        $out[] = false !== strpos($body, 'BEGIN zig3d-ai-access')
            ? self::result($group, self::OK, 'بلوکِ سیاستِ افزونه سرو می‌شود', '')
            : self::result($group, self::FAIL, 'بلوکِ سیاستِ افزونه در خروجی نیست', 'یا افزونه غیرفعال است، یا افزونهٔ دیگری خروجی را بازنویسی می‌کند، یا فایلِ فیزیکی جلویش را گرفته.');

        foreach (Robots::search_crawlers() as $bot) {
            $rules = $policy[$bot] ?? null;

            if (null === $rules) {
                $out[] = self::result($group, self::WARN, sprintf('%s: قاعده‌ای ندارد', $bot), 'قاعدهٔ ‎*‎ رویش اعمال می‌شود.');
            } elseif (in_array('/', $rules['disallow'], true)) {
                $out[] = self::result($group, self::FAIL, sprintf('%s بسته است', $bot), 'این یک خزندهٔ جستجو/بازیابی است — بستنش یعنی سایت در پاسخِ دستیارها دیده نمی‌شود.');
            } else {
                $out[] = self::result($group, self::OK, sprintf('%s باز است', $bot), '');
            }
        }

        $training_state = Robots::training_allowed() ? 'باز' : 'بسته';
        $closed = 0;

        foreach (Robots::training_crawlers() as $bot) {
            if (in_array('/', $policy[$bot]['disallow'] ?? [], true)) {
                $closed++;
            }
        }

        $out[] = self::result(
            $group,
            self::INFO,
            sprintf('خزنده‌هایِ آموزشِ مدل: %s (%d از %d)', $training_state, $closed, count(Robots::training_crawlers())),
            Robots::training_allowed() ? 'با تصمیمِ صریح باز شده‌اند.' : 'پیش‌فرضِ افزونه؛ با ‎robots.allow_training‎ قابلِ‌تغییر است.'
        );

        return $out;
    }

    /**
     * @return array<string,array{allow:array<int,string>,disallow:array<int,string>}>
     */
    public static function parse_robots(string $body): array {
        $policy = [];
        $agents = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ('' === $line) {
                $agents = [];

                continue;
            }

            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) {
                $agent = trim($m[1]);
                $agents[] = $agent;
                $policy[$agent] = $policy[$agent] ?? ['allow' => [], 'disallow' => []];

                continue;
            }

            if (preg_match('/^(allow|disallow):\s*(.*)$/i', $line, $m) && $agents) {
                $key  = strtolower($m[1]);
                $path = trim($m[2]);

                foreach ($agents as $agent) {
                    $policy[$agent][$key][] = $path;
                }
            }
        }

        return $policy;
    }

    /* --------------------------------------------- دسترسیِ واقعیِ خزنده */

    /**
     * تنها چکی که *واقعیت* را می‌سنجد، نه پیکربندی را.
     *
     * ‎Allow: /‎ فقط یک اعلان است. اگر فایروالِ هاست به این UA ۴۰۳
     * بدهد، خزنده هرگز داخل نمی‌آید — و هیچ تنظیمی در وردپرس آن را
     * عوض نمی‌کند. پس با همان UA یک درخواستِ واقعی زده می‌شود و کدِ
     * خام گزارش می‌شود، بدونِ هیچ تفسیرِ خوش‌بینانه.
     *
     * @return array<int,array<string,string>>
     */
    private static function check_crawler_access(): array {
        $group = 'دسترسیِ واقعیِ خزنده (فایروالِ هاست)';
        $root  = Url::site_root();

        if (null === $root) {
            return [];
        }

        $out = [
            self::result($group, self::INFO, 'این چک از خودِ سرور به سایت درخواست می‌زند', 'یک ۴۰۳ این‌جا یعنی بلاک در سطحِ فایروال (Imunify360/ModSecurity)، نه در وردپرس — این افزونه آن را حل نمی‌کند؛ باید از پشتیبانیِ هاست خواسته شود.'),
        ];

        $blocked = [];

        foreach (Robots::search_crawlers() as $bot) {
            $response = self::fetch($root, $bot);

            if (null === $response) {
                $out[] = self::result($group, self::WARN, sprintf('%s: پاسخی نداد', $bot), 'قطعیِ شبکه یا تایم‌اوت.');

                continue;
            }

            $code = $response['code'];

            if (200 === $code) {
                $out[] = self::result($group, self::OK, sprintf('%-18s → ۲۰۰', $bot), '');
            } elseif (403 === $code || 406 === $code || 429 === $code) {
                $blocked[] = $bot;
                $out[] = self::result($group, self::FAIL, sprintf('%-18s → %d', $bot, $code), 'در سطحِ سرور بلاک است. robots.txt این را حل نمی‌کند.');
            } else {
                $out[] = self::result($group, self::WARN, sprintf('%-18s → %d', $bot, $code), '');
            }
        }

        if ($blocked) {
            $out[] = self::result(
                $group,
                self::FAIL,
                sprintf('%d خزندهٔ جستجو در سطحِ سرور بلاک‌اند', count($blocked)),
                sprintf('%s — تا وقتی این حل نشود، نه سیاستِ robots و نه IndexNow کاملاً کار نمی‌کنند: IndexNow خبر می‌دهد ولی موتور که برایِ خزش بیاید ۴۰۳ می‌گیرد. این را باید از هاست خواست.', implode('، ', $blocked))
            );
        }

        return $out;
    }

    /* ----------------------------------------------- صفحاتِ پوشش‌داده‌شده */

    /** @return array<int,array<string,string>> */
    private static function check_pages(): array {
        $group = 'صفحاتِ پوشش‌داده‌شده';
        $out   = [];

        $samples = self::sample_urls();

        if (!$samples) {
            return [self::result($group, self::WARN, 'هیچ نشانیِ نمونه‌ای پیدا نشد', '')];
        }

        foreach ($samples as $label => $url) {
            $response = self::fetch($url);

            if (null === $response) {
                $out[] = self::result($group, self::WARN, sprintf('%s: پاسخی نداد', $label), $url);

                continue;
            }

            $out = array_merge($out, self::analyze_page($label, $url, $response['body']));
        }

        return $out;
    }

    /**
     * تحلیلِ خالصِ یک صفحه — بدونِ شبکه.
     *
     * @return array<int,array<string,string>>
     */
    public static function analyze_page(string $label, string $url, string $html): array {
        $group = 'صفحاتِ پوشش‌داده‌شده';
        $out   = [];

        $scripts = self::json_ld_blocks($html);
        $count   = count($scripts);

        if (0 === $count) {
            return [self::result($group, self::FAIL, sprintf('%s: هیچ JSON-LDی ندارد', $label), $url . ' — احتمالاً ماژولِ این مسیر ‎applies()‎ نمی‌دهد.')];
        }

        $out[] = 1 === $count
            ? self::result($group, self::OK, sprintf('%s: دقیقاً یک ‎<script>‎', $label), '')
            : self::result($group, self::FAIL, sprintf('%s: %d عدد ‎<script>‎ی JSON-LD', $label, $count), 'قاعدهٔ پروژه یک اسکریپت به‌ازایِ هر صفحه است.');

        if (false !== strpos($html, self::RANK_MATH_SIGNATURE)) {
            $out[] = self::result($group, self::FAIL, sprintf('%s: JSON-LDی رنک‌مث هنوز نشت می‌کند', $label), 'امضایِ ‎rank-math-schema‎ در صفحه هست — ‎Neutralizer‎ رویِ این مسیر اثر نکرده.');
        }

        $graphs = 0;
        $ours   = null;

        foreach ($scripts as $script) {
            $data = json_decode($script, true);

            if (!is_array($data)) {
                $out[] = self::result($group, self::FAIL, sprintf('%s: یک بلوکِ JSON-LD پارس نمی‌شود', $label), 'JSONِ نامعتبر یعنی کلِ بلوک نادیده گرفته می‌شود.');

                continue;
            }

            if (isset($data['@graph']) && is_array($data['@graph'])) {
                $graphs++;
                $ours = $data['@graph'];
            }
        }

        if ($graphs > 1) {
            $out[] = self::result($group, self::FAIL, sprintf('%s: %d عدد ‎@graph‎ی متناقض', $label, $graphs), 'موتورها ممکن است هرکدام را جدا تفسیر کنند.');
        }

        if (null === $ours) {
            return $out;
        }

        $out = array_merge($out, self::check_canonical($label, $html, $ours));
        $out = array_merge($out, self::check_dates($label, $ours));
        $out = array_merge($out, self::scan_text(
            sprintf('صفحاتِ پوشش‌داده‌شده — %s', $label),
            (string) wp_json_encode($ours, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ));

        return $out;
    }

    /**
     * @param  array<int,array<string,mixed>> $graph
     * @return array<int,array<string,string>>
     */
    private static function check_canonical(string $label, string $html, array $graph): array {
        $group = 'صفحاتِ پوشش‌داده‌شده';

        if (!preg_match('#<link[^>]+rel=["\']canonical["\'][^>]*>#i', $html, $tag)) {
            return [self::result($group, self::WARN, sprintf('%s: تگِ canonical ندارد', $label), 'canonical کارِ افزونهٔ سئوست، نه این افزونه — ولی نبودنش یعنی نمی‌شود توافق را سنجید.')];
        }

        if (!preg_match('#href=["\']([^"\']+)["\']#i', $tag[0], $href)) {
            return [];
        }

        $canonical = strtok($href[1], '#');

        foreach ($graph as $node) {
            if (!is_array($node) || 'WebPage' !== self::first_type($node)) {
                continue;
            }

            $id = strtok((string) ($node['@id'] ?? ''), '#');

            return [
                untrailingslashit((string) $canonical) === untrailingslashit((string) $id)
                    ? self::result($group, self::OK, sprintf('%s: canonical با ‎@id‎ی WebPage می‌خواند', $label), '')
                    : self::result($group, self::FAIL, sprintf('%s: canonical با ‎@id‎ نمی‌خواند', $label), sprintf('canonical=%s ولی @id=%s — موتور این‌ها را دو صفحهٔ متفاوت می‌بیند.', $canonical, $id)),
            ];
        }

        return [];
    }

    /**
     * تاریخ‌ها باید ISO 8601ی میلادی باشند.
     *
     * این چک از یک باگِ واقعیِ همین سایت آمده: schemaِ خودکارِ قبلی
     * ‎"1404-02-13\21:07:36"‎ چاپ می‌کرد — نه جداکنندهٔ ‎T‎، نه سالِ
     * میلادی. سالِ جلالی همیشه در محدودهٔ ۱۳۰۰–۱۵۰۰ است، پس تشخیصش
     * قطعی است.
     *
     * @param  array<int,array<string,mixed>> $graph
     * @return array<int,array<string,string>>
     */
    private static function check_dates(string $label, array $graph): array {
        $bad = [];

        array_walk_recursive($graph, static function ($value, $key) use (&$bad): void {
            if (!is_string($value) || !in_array((string) $key, self::DATE_KEYS, true)) {
                return;
            }

            $year = (int) substr($value, 0, 4);

            if ($year >= 1300 && $year <= 1500) {
                $bad[] = sprintf('%s = %s (سالِ جلالی)', $key, $value);

                return;
            }

            if (!self::is_iso_8601($value)) {
                $bad[] = sprintf('%s = %s (ISO 8601 نیست)', $key, $value);
            }
        });

        if (!$bad) {
            return [];
        }

        return [self::result(
            'صفحاتِ پوشش‌داده‌شده',
            self::FAIL,
            sprintf('%s: %d تاریخِ نامعتبر', $label, count($bad)),
            implode(' | ', array_slice($bad, 0, 4)) . ' — همیشه ‎get_post_datetime()‎، هرگز ‎get_the_date()‎ که از فیلترِ تقویمِ جلالی رد می‌شود.'
        )];
    }

    /**
     * ISO 8601ی معتبر برایِ یک ‎Date‎ یا ‎DateTime‎ی schema.org.
     *
     * نکته‌ای که در اولین اجرایِ سرتاسری خودش را نشان داد: تایپِ
     * ‎Date‎ی schema.org دقتِ کمتر را هم می‌پذیرد — ‎foundingDate: "2013"‎
     * کاملاً معتبر است. اگر فقط ‎DATE_ATOM‎ پذیرفته می‌شد، doctor رویِ
     * *هر* صفحهٔ سایت یک خطایِ کاذب می‌داد و خیلی زود کسی کلاً نگاهش
     * نمی‌کرد — یک ابزارِ تشخیصی با مثبتِ کاذب همان‌قدر بی‌فایده است که
     * یک ابزارِ کور.
     */
    private static function is_iso_8601(string $value): bool {
        foreach ([DATE_ATOM, 'Y-m-d\TH:i:s', 'Y-m-d', 'Y-m', 'Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $value);

            // ‎format()‎ی برگشتی باید عیناً همان ورودی باشد، وگرنه PHP
            // چیزی مثلِ ‎2025-13-45‎ را «تصحیح» کرده و قبول شده
            if (false !== $parsed && $parsed->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * نشانیِ غیرتولید یا متنِ جانگهدار در خروجیِ ماشین‌خوان.
     *
     * @return array<int,array<string,string>>
     */
    private static function scan_text(string $group, string $text): array {
        $out = [];

        $found = [];

        /*
         * ‎wp_json_encode()‎ به‌طورِ پیش‌فرض اسلش‌ها را ‎\/‎ می‌کند. بدونِ
         * این نرمال‌سازی، اسکنر هیچ نشانی‌ای را داخلِ JSON پیدا نمی‌کرد
         * و بی‌سروصدا همیشه «سالم» می‌گفت — یعنی دقیقاً همان شکستِ
         * بی‌صدایی که این افزونه برایِ حذفش نوشته شده.
         */
        $text = str_replace('\\/', '/', $text);

        if (preg_match_all('#https?://[^\s"\'<>)\]]+#', $text, $m)) {
            foreach (array_unique($m[0]) as $candidate) {
                // فقط نشانی‌هایِ خودمان مهم‌اند؛ ‎schema.org‎ و شبکه‌هایِ
                // اجتماعی طبیعتاً بیرونی‌اند
                if (self::is_external_reference($candidate)) {
                    continue;
                }

                if (!Url::is_production($candidate)) {
                    $found[] = $candidate;
                }
            }
        }

        if ($found) {
            $out[] = self::result($group, self::FAIL, sprintf('%d نشانیِ غیرتولید', count($found)), implode(' | ', array_slice($found, 0, 4)));
        }

        $lower = strtolower($text);
        $hits  = [];

        foreach (self::PLACEHOLDERS as $needle) {
            if (false !== strpos($lower, $needle)) {
                $hits[] = $needle;
            }
        }

        if ($hits) {
            $out[] = self::result($group, self::FAIL, 'متنِ جانگهدار در خروجیِ ماشین‌خوان', implode('، ', $hits));
        }

        return $out;
    }

    private static function is_external_reference(string $url): bool {
        $host = (string) wp_parse_url($url, PHP_URL_HOST);

        foreach (['schema.org', 'instagram.com', 'twitter.com', 'x.com', 'facebook.com', 'youtube.com', 'aparat.com', 'linkedin.com', 'telegram.me', 't.me'] as $allowed) {
            if ($host === $allowed || self::ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    private static function ends_with(string $haystack, string $needle): bool {
        $len = strlen($needle);

        return 0 !== $len && substr($haystack, -$len) === $needle;
    }

    /* -------------------------------------------------------------- ابزار */

    /** @return array<int,string> */
    public static function json_ld_blocks(string $html): array {
        if (!preg_match_all('#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m)) {
            return [];
        }

        return array_map('trim', $m[1]);
    }

    /** @param array<string,mixed> $node */
    private static function first_type(array $node): string {
        $type = $node['@type'] ?? '';

        if (is_array($type)) {
            $type = $type[0] ?? '';
        }

        return (string) $type;
    }

    /**
     * یک نشانیِ نمونه به‌ازایِ هر نوعِ صفحه — از دادهٔ زنده، نه هاردکد.
     *
     * @return array<string,string>
     */
    private static function sample_urls(): array {
        $out = [];

        $add = static function (string $label, $url) use (&$out): void {
            if (is_string($url) && '' !== $url) {
                $canonical = Url::canonical_or_null($url, 'doctor.sample');

                if (null !== $canonical) {
                    $out[$label] = $canonical;
                }
            }
        };

        $add('خانه', home_url('/'));

        if (function_exists('wc_get_page_permalink')) {
            $add('فروشگاه', wc_get_page_permalink('shop'));
        }

        $add('بلاگ', Blog::home_url());
        $add('دانلود', Downloads::archive_url());

        $terms = [
            'دستهٔ محصول'  => 'product_cat',
            'دستهٔ نرم‌افزار' => (string) Config::get('downloads.taxonomy', ''),
            'دستهٔ بلاگ'    => 'category',
        ];

        foreach ($terms as $label => $taxonomy) {
            if ('' === $taxonomy || !taxonomy_exists($taxonomy)) {
                continue;
            }

            $found = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 1, 'orderby' => 'count', 'order' => 'DESC']);

            if (!is_wp_error($found) && $found && $found[0] instanceof \WP_Term) {
                $link = get_term_link($found[0]);

                if (!is_wp_error($link)) {
                    $add($label, $link);
                }
            }
        }

        $singles = ['محصول' => 'product', 'مقاله' => 'post', 'نرم‌افزار' => Downloads::post_type()];

        foreach ($singles as $label => $post_type) {
            if ('' === $post_type || !post_type_exists($post_type)) {
                continue;
            }

            $posts = get_posts([
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);

            if ($posts) {
                $add($label, get_permalink((int) $posts[0]));
            }
        }

        return $out;
    }

    /**
     * @return array{code:int,content_type:string,body:string}|null
     */
    private static function fetch(string $url, string $user_agent = ''): ?array {
        // رویِ هاستِ اشتراکی سقفِ زمانِ اجرا کوتاه است و این دستور تا
        // ~۲۰ درخواست می‌زند؛ تایم‌اوتِ سخاوتمندانه یعنی صفحه نیمه‌کاره
        // می‌ماند. اکثرِ پاسخ‌ها زیرِ یک ثانیه‌اند، پس ۸ ثانیه سقفِ منطقی است.
        $args = [
            'timeout'     => max(1, (int) Config::get('doctor.timeout', 8)),
            'redirection' => 3,
            'sslverify'   => true,
        ];

        if ('' !== $user_agent) {
            $args['user-agent'] = $user_agent;
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return null;
        }

        return [
            'code'         => (int) wp_remote_retrieve_response_code($response),
            'content_type' => (string) wp_remote_retrieve_header($response, 'content-type'),
            'body'         => (string) wp_remote_retrieve_body($response),
        ];
    }

    /** @return array{group:string,status:string,label:string,detail:string} */
    private static function result(string $group, string $status, string $label, string $detail = ''): array {
        return ['group' => $group, 'status' => $status, 'label' => $label, 'detail' => $detail];
    }
}

add_filter('zig3d_ai_access/features', static function (array $features): array {
    $features[] = new Doctor();

    return $features;
});

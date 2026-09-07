<?php
namespace Zig3d_AI_Access\Feature;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Feature_Module;
use Zig3d_AI_Access\Support\Blog;
use Zig3d_AI_Access\Support\Cache;
use Zig3d_AI_Access\Support\Downloads;
use Zig3d_AI_Access\Support\Rewrite;
use Zig3d_AI_Access\Support\Url;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ‎/llms.txt‎ — یک نقشهٔ **منتخبِ** سایت برایِ مدل‌های زبانی.
 *
 * ساختار طبقِ اسپکِ ‎llmstxt.org‎ (بررسی‌شده، نه حدس): یک ‎H1‎ (تنها
 * بخشِ الزامی)، یک بلاک‌کوتِ خلاصه، پاراگراف‌هایِ آزادِ اختیاری، و بعد
 * بخش‌هایِ ‎## عنوان‎ که هرکدام فهرستی از ‎- [عنوان](نشانی): توضیح‎ است.
 * اسپک ‎Content-Type‎ی الزام نکرده؛ خودِ ‎llmstxt.org‎ فایلش را با
 * ‎text/plain; charset=utf-8‎ سرو می‌کند و همان این‌جا هم پیش‌فرض است.
 *
 * دو تصمیمِ محوری:
 *
 * ۱. **endpointِ مجازی، نه فایلِ فیزیکی.** یک فایلِ واقعی در ریشهٔ سایت
 *    یعنی محتوایی که با انتشارِ یک محصولِ جدید کهنه می‌شود و کسی
 *    یادش نمی‌ماند تازه‌اش کند — دقیقاً همان چیزی که یک نقشهٔ سایت
 *    نباید باشد. این‌جا هر بار از دادهٔ زنده ساخته و در ‎Cache‎ی
 *    مشترک نگه داشته می‌شود، و همان باسِ باطل‌سازی که با ‎save_post‎
 *    عوض می‌شود تازه‌اش می‌کند.
 *
 * ۲. **منتخب، نه کاتالوگ.** فقط هاب‌هایِ سطحِ بالا و مهم‌ترین دسته‌ها.
 *    هزاران لینک یعنی هیچ لینکی — ارزشِ این فایل در همان انتخاب‌شدگی
 *    است، وگرنه sitemap از قبل وجود دارد (و ماله Rank Math است؛
 *    این‌جا رقیبی برایش ساخته نمی‌شود).
 *
 * هر URL دوبار از دروازه رد می‌شود: اول ‎get_permalink()/get_term_link()‎
 * تا اصلاً حدسی در کار نباشد، بعد ‎Url::canonical_or_null()‎ تا هیچ
 * نشانیِ استیج/ادمین/غیرکانونیکال بیرون نرود.
 */
final class Llms implements Feature_Module {

    public const QUERY_VAR = 'zig3d_llms';

    public function boot(): void {
        if (!self::enabled()) {
            return;
        }

        Rewrite::add('^' . preg_quote(self::path(), '#') . '$', 'index.php?' . self::QUERY_VAR . '=1', [self::QUERY_VAR]);

        add_action('template_redirect', [$this, 'maybe_render'], 0);
    }

    public static function enabled(): bool {
        return (bool) Config::get('llms.enabled', true);
    }

    /** مسیرِ نسبی، بدونِ اسلشِ ابتدایی */
    public static function path(): string {
        $path = trim((string) Config::get('llms.path', 'llms.txt'), '/');

        return '' !== $path ? $path : 'llms.txt';
    }

    /** نشانیِ عمومیِ فایل، یا ‎null‎ اگر خودِ سایت کانونیکال نباشد */
    public static function url(): ?string {
        $root = Url::site_root();

        return null === $root ? null : trailingslashit($root) . self::path();
    }

    /* ---------------------------------------------------------- endpoint */

    public function maybe_render(): void {
        if (!get_query_var(self::QUERY_VAR)) {
            return;
        }

        $body = self::body();

        if (null === $body) {
            // رویِ یک نصبِ استیج هیچ چیزی سرو نمی‌شود — نه یک فایلِ خالی،
            // نه فهرستی از URLهایِ استیج
            status_header(404);
            nocache_headers();
            exit;
        }

        status_header(200);
        header('Content-Type: ' . self::content_type());
        header('X-Robots-Tag: noindex');

        echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- متنِ ساده، نه HTML؛ فرار در خودِ سازنده انجام شده

        exit;
    }

    private static function content_type(): string {
        $type = trim((string) Config::get('llms.content_type', 'text/plain; charset=utf-8'));

        return '' !== $type ? $type : 'text/plain; charset=utf-8';
    }

    /* ----------------------------------------------------------- محتوا */

    /** متنِ کامل، یا ‎null‎ اگر سایت کانونیکال نباشد */
    public static function body(): ?string {
        if (null === Url::site_root()) {
            Diagnostics::drop('llms', 'home_url() is not the canonical production URL — refusing to publish a machine-readable URL list');

            return null;
        }

        $cached = Cache::remember('llms.txt', static fn(): ?string => self::build());

        return is_string($cached) && '' !== $cached ? $cached : null;
    }

    private static function build(): ?string {
        $lines = [];

        $lines[] = '# ' . self::one_line((string) Config::get('llms.title', (string) Config::get('organization.name', '')));

        $summary = self::one_line((string) Config::get('llms.summary', (string) Config::get('website.description', '')));

        if ('' !== $summary) {
            $lines[] = '';
            $lines[] = '> ' . $summary;
        }

        foreach (self::notes() as $note) {
            $lines[] = '';
            $lines[] = $note;
        }

        foreach ((array) Config::get('llms.sections', []) as $index => $section) {
            $rendered = self::render_section(is_array($section) ? $section : [], (int) $index);

            if (null === $rendered) {
                continue;
            }

            $lines[] = '';
            $lines[] = $rendered;
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * پاراگراف‌هایِ آزاد — فکت‌هایِ برند از همان دادهٔ ‎organization‎ی
     * فازِ schema، نه بازتولیدشان.
     *
     * ‎contact_points‎ عمداً این‌جا نمی‌آید: یکی از آن‌ها یک ایمیلِ
     * شخصیِ کارمند است و این فایل یک خروجیِ عمومیِ ماشین‌خوان است.
     * فقط ایمیلِ سازمانیِ عمومی و شهر می‌آید.
     *
     * @return array<int,string>
     */
    private static function notes(): array {
        $out = [];

        foreach ((array) Config::get('llms.notes', []) as $note) {
            $note = self::one_line((string) $note);

            if ('' !== $note) {
                $out[] = $note;
            }
        }

        $facts = [];

        $founded = trim((string) Config::get('organization.founding_date', ''));

        if ('' !== $founded) {
            $facts[] = sprintf('سالِ تأسیس: %s', $founded);
        }

        $locality = trim((string) Config::get('organization.address.address_locality', ''));

        if ('' !== $locality) {
            $facts[] = sprintf('مستقر در %s، ایران', $locality);
        }

        if ((bool) Config::get('llms.include_public_email', true)) {
            $email = trim((string) Config::get('organization.email', ''));

            if ('' !== $email && is_email($email)) {
                $facts[] = sprintf('تماس: %s', $email);
            }
        }

        if ($facts) {
            $out[] = implode(' — ', $facts) . '.';
        }

        return $out;
    }

    /**
     * @param  array<string,mixed> $section
     */
    private static function render_section(array $section, int $index): ?string {
        $title = self::one_line((string) ($section['title'] ?? ''));
        $label = '' !== $title ? $title : sprintf('#%d', $index);

        if ('' === $title) {
            Diagnostics::drop('llms.section', sprintf('section %d has no title — skipped', $index));

            return null;
        }

        $limit = max(1, (int) ($section['limit'] ?? 20));
        $items = self::items($section, $limit, $label);

        if (!$items) {
            // Diagnostics دلیلش را از قبل ثبت کرده؛ بخشِ خالی چاپ نمی‌شود
            return null;
        }

        $lines = ['## ' . $title];

        foreach (array_slice($items, 0, $limit) as $item) {
            $lines[] = self::bullet($item['title'], $item['url'], $item['note']);
        }

        return implode("\n", $lines);
    }

    /**
     * دیسپچِ منبعِ بخش. افزودنِ نوعِ تازه = یک ‎case‎ی تازه، یا فیلترِ
     * ‎zig3d_ai_access/llms/items‎ بدونِ دست‌زدن به این فایل.
     *
     * @param  array<string,mixed> $section
     * @return array<int,array{title:string,url:string,note:string}>
     */
    private static function items(array $section, int $limit, string $label): array {
        $source = (string) ($section['source'] ?? '');

        switch ($source) {
            case 'hubs':
                $items = self::hub_items($section, $label);
                break;

            case 'taxonomy':
                $items = self::taxonomy_items($section, $limit, $label);
                break;

            default:
                Diagnostics::drop('llms.section', sprintf('section "%s" has unknown source "%s" — skipped', $label, $source));

                $items = [];
        }

        /** @var array<int,array{title:string,url:string,note:string}> $items */
        $items = (array) apply_filters('zig3d_ai_access/llms/items', $items, $section, $limit);

        return $items;
    }

    /**
     * هاب‌هایِ سطحِ بالا. هر نشانی از خودِ وردپرس/ووکامرس پرسیده می‌شود،
     * نه از یک الگویِ حدسیِ ‎/shop/‎ — رویِ همین سایت اسلاگ‌ها فارسی و
     * غیرقابلِ‌پیش‌بینی‌اند.
     *
     * @param  array<string,mixed> $section
     * @return array<int,array{title:string,url:string,note:string}>
     */
    private static function hub_items(array $section, string $label): array {
        $out = [];

        foreach ((array) ($section['hubs'] ?? []) as $key => $hub) {
            $key = (string) $key;
            $hub = is_array($hub) ? $hub : [];

            $raw = self::hub_url($key);

            if ('' === $raw) {
                Diagnostics::drop('llms.hub', sprintf('hub "%s" could not be resolved to a URL by WordPress — omitted', $key));

                continue;
            }

            $url = Url::canonical_or_null($raw, 'llms.hub');

            if (null === $url) {
                continue;
            }

            $title = self::one_line((string) ($hub['label'] ?? ''));

            if ('' === $title) {
                Diagnostics::drop('llms.hub', sprintf('hub "%s" has no label in config — omitted', $key));

                continue;
            }

            $out[] = [
                'title' => $title,
                'url'   => $url,
                'note'  => self::one_line((string) ($hub['note'] ?? '')),
            ];
        }

        if (!$out) {
            Diagnostics::drop('llms.section', sprintf('section "%s" resolved to zero usable hub URLs', $label));
        }

        return $out;
    }

    private static function hub_url(string $key): string {
        switch ($key) {
            case 'home':
                return (string) home_url('/');

            case 'shop':
                if (!function_exists('wc_get_page_permalink')) {
                    return '';
                }

                $link = wc_get_page_permalink('shop');

                return is_string($link) ? $link : '';

            case 'downloads':
                return Downloads::archive_url();

            case 'blog':
                return Blog::home_url();
        }

        return '';
    }

    /**
     * مهم‌ترین ترم‌هایِ یک تاکسونومی. «مهم‌ترین» = پرمحصول‌ترین
     * (‎orderby=count‎)، چون تنها سیگنالِ اهمیتی است که رویِ این سایت
     * واقعاً وجود دارد — یک فیلدِ «اولویت»یِ دستی ساخته نشده و جعلش
     * یعنی ترتیبی که هیچ‌کس تأییدش نکرده.
     *
     * @param  array<string,mixed> $section
     * @return array<int,array{title:string,url:string,note:string}>
     */
    private static function taxonomy_items(array $section, int $limit, string $label): array {
        $taxonomy = (string) ($section['taxonomy'] ?? '');

        if ('' === $taxonomy || !taxonomy_exists($taxonomy)) {
            Diagnostics::drop('llms.section', sprintf('section "%s": taxonomy "%s" is not registered on this site', $label, $taxonomy));

            return [];
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'number'     => $limit,
            'orderby'    => (string) ($section['orderby'] ?? 'count'),
            'order'      => (string) ($section['order'] ?? 'DESC'),
        ]);

        if (is_wp_error($terms) || !$terms) {
            Diagnostics::drop('llms.section', sprintf('section "%s": taxonomy "%s" returned no non-empty terms', $label, $taxonomy));

            return [];
        }

        $out = [];

        foreach ($terms as $term) {
            if (!$term instanceof \WP_Term) {
                continue;
            }

            $link = get_term_link($term);

            if (is_wp_error($link) || !is_string($link)) {
                Diagnostics::drop('llms.term', sprintf('get_term_link(%d) failed — omitted', (int) $term->term_id));

                continue;
            }

            $url = Url::canonical_or_null($link, 'llms.term');

            if (null === $url) {
                continue;
            }

            $out[] = [
                'title' => self::one_line((string) $term->name),
                'url'   => $url,
                'note'  => self::excerpt((string) term_description((int) $term->term_id)),
            ];
        }

        if (!$out) {
            Diagnostics::drop('llms.section', sprintf('section "%s": no term produced a canonical URL', $label));
        }

        return $out;
    }

    /* ------------------------------------------------------------ متن */

    private static function bullet(string $title, string $url, string $note): string {
        $line = sprintf('- [%s](%s)', self::escape_link_text($title), $url);

        return '' !== $note ? $line . ': ' . $note : $line;
    }

    /**
     * ‎[‎ و ‎]‎ داخلِ متنِ لینک ساختارِ مارک‌داون را می‌شکنند — عنوانِ یک
     * دستهٔ واقعی می‌تواند براکت داشته باشد.
     */
    private static function escape_link_text(string $text): string {
        return str_replace(['[', ']'], ['\\[', '\\]'], $text);
    }

    /** یک خط، بدونِ تگ، بدونِ فاصلهٔ تکراری — چون هر آیتم یک سطر است */
    private static function one_line(string $text): string {
        $text = wp_strip_all_tags($text, true);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** توضیحِ کوتاهِ کنارِ لینک — بلندش فایل را به یک کاتالوگ تبدیل می‌کند */
    private static function excerpt(string $text, int $max = 140): string {
        $text = self::one_line($text);

        if ('' === $text || mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max)) . '…';
    }
}

add_filter('zig3d_ai_access/features', static function (array $features): array {
    $features[] = new Llms();

    return $features;
});

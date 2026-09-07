<?php
namespace Zig3d_AI_Access\Feature;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Feature_Module;
use Zig3d_AI_Access\Support\Url;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سیاستِ خزنده‌هایِ هوشِ مصنوعی در ‎robots.txt‎.
 *
 * ── سه نکته که کلِ طراحیِ این فایل رویشان سوار است ──
 *
 * **۱. merge، نه replace.** فیلترِ ‎robots_txt‎ کلِ متن را می‌دهد و
 * وسوسه‌اش این است که یکی بازنویسی‌اش کند. قواعدِ فعلی (ووکامرس،
 * افزونه‌هایِ امنیتی، Rank Math) این‌جا سرِ جایشان می‌مانند و ما فقط
 * یک بلوکِ نشان‌دار *اضافه* می‌کنیم. اگر همان بلوک از قبل بود، جایگزین
 * می‌شود تا خروجی هر بار بزرگ‌تر نشود.
 *
 * **۲. جستجو ≠ آموزش.** این‌ها دو چیزِ کاملاً متفاوت‌اند و قاطیشان
 * کردن یک اشتباهِ رایج و پرهزینه است:
 *
 *   - خزندهٔ *جستجو/بازیابی* چیزی است که باعث می‌شود سایت در پاسخِ
 *     ChatGPT یا Perplexity **دیده شود**. بستنش یعنی نامرئی‌شدن.
 *   - خزندهٔ *آموزشِ مدل* محتوا را برایِ آموزشِ نسل‌هایِ بعدیِ مدل
 *     برمی‌دارد و هیچ ترافیکی برنمی‌گرداند.
 *
 * پس پیش‌فرض: جستجو باز، آموزش بسته — و آموزش با یک کلید در config
 * قابلِ‌بازکردن است، ولی سایت به‌طورِ خودکار به آن رضایت نمی‌دهد.
 *
 * همهٔ توکن‌هایِ زیر از مستنداتِ رسمیِ خودِ همان شرکت‌ها راستی‌آزمایی
 * شده‌اند، نه از فهرست‌هایِ دستِ‌سوم — یک توکنِ غلط یعنی قاعده‌ای که
 * بی‌صدا هیچ کاری نمی‌کند.
 *
 * **۳. صداقتِ WAF — مهم‌ترین بند.** ‎robots.txt‎ **توصیه‌ای** است. یک
 * خطِ ‎Allow‎ هیچ چیزی را باز نمی‌کند؛ فقط اعلامِ اجازه است. بلاکِ
 * واقعیِ فعلیِ این سایت در سطحِ **WAFِ هاست** (Imunify360/ModSecurity)
 * است که به بعضی UAها ۴۰۳ می‌دهد، و **این افزونه آن را حل نمی‌کند**.
 * پس این فایل هرگز نمی‌گوید «مجاز شد»؛ Doctor هم باید همین را
 * بگوید. تنها راهِ تأیید، یک fetchِ واقعی با همان UA است.
 *
 * به همین دلیل هیچ بلاکِ UAیی در سطحِ اپلیکیشن (۴۰۳ دادن به یک
 * خزنده) این‌جا اضافه نمی‌شود: مسئلهٔ این سایت *زیادی* بلاک‌شدن است،
 * نه کم‌بودنش.
 */
final class Robots implements Feature_Module {

    private const BEGIN = '# BEGIN zig3d-ai-access';
    private const END   = '# END zig3d-ai-access';

    public function boot(): void {
        if (!(bool) Config::get('robots.enabled', true)) {
            return;
        }

        // اولویتِ بالا: بعد از هر افزونهٔ دیگری، تا بلوکِ ما آخر بیاید و چیزی را جابه‌جا نکند
        add_filter('robots_txt', [$this, 'filter'], 99, 2);
    }

    /**
     * @param string    $output متنِ فعلی — دست‌نخورده می‌ماند
     * @param bool|mixed $public مقدارِ ‎blog_public‎
     */
    public function filter($output, $public = true): string {
        $output = (string) $output;

        // سایتِ خصوصی: وردپرس خودش همه‌چیز را می‌بندد؛ ما رویش قاعدهٔ
        // اجازه نمی‌نویسیم و آن تصمیم را نقض نمی‌کنیم
        if (!$public) {
            Diagnostics::drop('robots', 'site is set to discourage search engines (blog_public = 0) — AI policy block not added');

            return $output;
        }

        $block = $this->block();

        if ('' === $block) {
            return $output;
        }

        return $this->merge($output, $block);
    }

    /**
     * بلوکِ ما را جایگزین یا اضافه می‌کند — بدونِ دست‌زدن به بقیهٔ متن.
     */
    private function merge(string $output, string $block): string {
        $pattern = '/' . preg_quote(self::BEGIN, '/') . '.*?' . preg_quote(self::END, '/') . '\R?/s';

        if (preg_match($pattern, $output)) {
            return (string) preg_replace($pattern, $block . "\n", $output, 1);
        }

        return rtrim($output, "\n") . "\n\n" . $block . "\n";
    }

    private function block(): string {
        $lines = [self::BEGIN];

        $lines[] = '# سیاستِ خزنده‌هایِ هوشِ مصنوعی. توجه: robots.txt توصیه‌ای است —';
        $lines[] = '# اجازهٔ این‌جا تضمینِ دسترسی نیست اگر فایروالِ سرور همان UA را ۴۰۳ کند.';

        $allowed = $this->group('robots.search_crawlers', true);
        $lines   = array_merge($lines, $this->rules($allowed, 'Allow', '/', 'خزنده‌هایِ جستجو/بازیابی — لازم برایِ دیده‌شدن در پاسخِ دستیارها'));

        $training_allowed = (bool) Config::get('robots.allow_training', false);
        $training         = $this->group('robots.training_crawlers', true);

        $lines = array_merge($lines, $this->rules(
            $training,
            $training_allowed ? 'Allow' : 'Disallow',
            '/',
            $training_allowed
                ? 'خزنده‌هایِ آموزشِ مدل — با تصمیمِ صریحِ مدیرِ سایت باز شده‌اند'
                : 'خزنده‌هایِ آموزشِ مدل — پیش‌فرض بسته؛ رضایتِ خودکار به آموزش داده نمی‌شود'
        ));

        $llms = Llms::enabled() ? Llms::url() : null;

        if (null !== $llms) {
            $lines[] = '';
            $lines[] = '# نقشهٔ منتخبِ سایت برایِ مدل‌های زبانی (sitemap جداست و مالِ افزونهٔ سئو می‌ماند)';
            $lines[] = '# ' . $llms;
        }

        $lines[] = self::END;

        return implode("\n", $lines);
    }

    /**
     * @param  array<int,string> $agents
     * @return array<int,string>
     */
    private function rules(array $agents, string $directive, string $path, string $comment): array {
        if (!$agents) {
            return [];
        }

        $lines = ['', '# ' . $comment];

        foreach ($agents as $agent) {
            $lines[] = 'User-agent: ' . $agent;
            $lines[] = $directive . ': ' . $path;
            $lines[] = '';
        }

        return array_slice($lines, 0, -1);
    }

    /**
     * توکن‌ها از config؛ هرکدام یک رشتهٔ ساده و تمیز.
     *
     * @return array<int,string>
     */
    private function group(string $key, bool $warn): array {
        $out = [];

        foreach ((array) Config::get($key, []) as $agent) {
            $agent = trim((string) $agent);

            // یک توکنِ حاویِ فاصله یا ‎:‎ یعنی کسی رشتهٔ کاملِ UA را
            // این‌جا نوشته؛ چنین قاعده‌ای بی‌صدا هیچ‌وقت match نمی‌شود
            if ('' === $agent || preg_match('/[\s:#]/', $agent)) {
                if ($warn && '' !== $agent) {
                    Diagnostics::drop('robots', sprintf('"%s" is not a bare product token — rule omitted rather than emitted as a silent no-op', $agent));
                }

                continue;
            }

            $out[] = $agent;
        }

        return array_values(array_unique($out));
    }

    /**
     * فهرستِ توکن‌هایی که «باید در دسترس باشند» — Doctor از همین
     * می‌خواند تا هم متنِ robots را چک کند و هم دربارهٔ WAF هشدار بدهد.
     *
     * @return array<int,string>
     */
    public static function search_crawlers(): array {
        return array_values(array_filter(array_map(
            static fn($a): string => trim((string) $a),
            (array) Config::get('robots.search_crawlers', [])
        )));
    }

    /** @return array<int,string> */
    public static function training_crawlers(): array {
        return array_values(array_filter(array_map(
            static fn($a): string => trim((string) $a),
            (array) Config::get('robots.training_crawlers', [])
        )));
    }

    public static function training_allowed(): bool {
        return (bool) Config::get('robots.allow_training', false);
    }

    /** آیا ‎robots.txt‎ اصلاً از وردپرس سرو می‌شود؟ (فایلِ فیزیکی فیلتر را دور می‌زند) */
    public static function served_by_wordpress(): bool {
        $root = Url::site_root();

        return null !== $root && !file_exists(ABSPATH . 'robots.txt');
    }
}

add_filter('zig3d_ai_access/features', static function (array $features): array {
    $features[] = new Robots();

    return $features;
});

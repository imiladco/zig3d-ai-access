<?php
namespace Zig3d_AI_Access\Admin;

use Zig3d_AI_Access\Feature\Doctor;
use Zig3d_AI_Access\Feature\IndexNow;
use Zig3d_AI_Access\Feature\Llms;
use Zig3d_AI_Access\Support\Url;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ابزارها ← ZIG3D Doctor — همان چک‌آپِ ‎wp zig3d doctor‎، با دکمه.
 *
 * چرا لازم شد: سندِ معماری صفحهٔ ادمین را «اختیاری» گذاشته بود چون CLI
 * عملیاتی‌تر است. ولی رویِ هاستِ اشتراکیِ این سایت WP-CLI در دسترس
 * نیست، و یک ابزارِ تشخیصی که نمی‌شود اجرایش کرد یعنی ابزارِ تشخیصی
 * نداریم. پس این‌جا «اختیاری» نیست؛ تنها راهِ اجراست.
 *
 * منطقِ چک این‌جا **تکرار نشده** — دقیقاً همان ‎Doctor::checks()‎ صدا
 * زده می‌شود، تا هرگز CLI و صفحهٔ ادمین دو جوابِ متفاوت ندهند.
 *
 * امنیت: نمایشِ منو و اجرا هر دو پشتِ ‎manage_options‎؛ اجرا فقط با
 * POSTِ دارایِ nonce (چون این صفحه به بیرون درخواستِ HTTP می‌زند و
 * نباید با یک لینکِ ساده قابلِ‌تحریک باشد)؛ و هیچ مسیرِ فایل، استک‌تریس
 * یا ساختارِ دیتابیسی چاپ نمی‌شود — خروجیِ ‎Doctor‎ از قبل فقط دادهٔ
 * عمومی است.
 */
final class DoctorPage {

    private const SLUG  = 'zig3d-doctor';
    private const NONCE = 'zig3d_doctor_run';

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register']);
    }

    public static function register(): void {
        add_submenu_page(
            'tools.php',
            __('ZIG3D Doctor', 'zig3d-ai-access'),
            __('ZIG3D Doctor', 'zig3d-ai-access'),
            'manage_options',
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('اجازهٔ دسترسی به این صفحه را ندارید.', 'zig3d-ai-access'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ZIG3D Doctor', 'zig3d-ai-access') . '</h1>';

        self::render_links();
        self::render_form();

        $requested = isset($_POST['zig3d_doctor_submit']);

        if ($requested && check_admin_referer(self::NONCE)) {
            self::render_results(!isset($_POST['zig3d_skip_remote']));
        }

        echo '</div>';
    }

    /* ---------------------------------------------------------------- */

    /** لینک‌هایی که کاربر می‌تواند مستقیم در مرورگر باز کند */
    private static function render_links(): void {
        $root = Url::site_root();

        $links = [];

        if (Llms::enabled() && null !== Llms::url()) {
            $links[__('فایلِ llms.txt', 'zig3d-ai-access')] = (string) Llms::url();
        }

        if (null !== $root) {
            $links[__('robots.txt', 'zig3d-ai-access')] = trailingslashit($root) . 'robots.txt';
        }

        if (IndexNow::enabled() && null !== IndexNow::key_url()) {
            $links[__('فایلِ تأییدِ IndexNow', 'zig3d-ai-access')] = (string) IndexNow::key_url();
        }

        if (!$links) {
            return;
        }

        echo '<p>' . esc_html__('این آدرس‌ها را می‌توانید همین حالا در مرورگر باز کنید:', 'zig3d-ai-access') . '</p><ul style="list-style:disc;margin-right:2em">';

        foreach ($links as $label => $url) {
            printf(
                '<li><a href="%s" target="_blank" rel="noopener">%s</a> — <code>%s</code></li>',
                esc_url($url),
                esc_html($label),
                esc_html($url)
            );
        }

        echo '</ul>';
    }

    private static function render_form(): void {
        echo '<form method="post">';

        wp_nonce_field(self::NONCE);

        echo '<p><label><input type="checkbox" name="zig3d_skip_remote" value="1" /> ';
        echo esc_html__('بدونِ بررسیِ شبکه (سریع — فقط پیکربندی را می‌سنجد)', 'zig3d-ai-access');
        echo '</label></p>';

        echo '<p class="description">' . esc_html__('بررسیِ کامل به سایت درخواستِ واقعی می‌زند (از جمله با UAی خزنده‌ها) و ممکن است تا یک دقیقه طول بکشد.', 'zig3d-ai-access') . '</p>';

        submit_button(__('اجرای بررسی', 'zig3d-ai-access'), 'primary', 'zig3d_doctor_submit');

        echo '</form>';
    }

    private static function render_results(bool $remote): void {
        // بررسیِ کامل چند درخواستِ بیرونی می‌زند؛ رویِ هاستِ اشتراکی
        // سقفِ زمانِ اجرا معمولاً کوتاه است. اگر هاست اجازه ندهد این
        // فراخوانی بی‌اثر است و چیزی خراب نمی‌شود.
        if (function_exists('set_time_limit')) {
            @set_time_limit(120); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }

        $results = Doctor::checks($remote);

        $styles = [
            Doctor::OK   => ['#1a7f37', '✔'],
            Doctor::WARN => ['#bf8700', '▲'],
            Doctor::FAIL => ['#d1242f', '✘'],
            Doctor::INFO => ['#57606a', 'ℹ'],
        ];

        $counts = [Doctor::OK => 0, Doctor::WARN => 0, Doctor::FAIL => 0, Doctor::INFO => 0];
        $group  = '';

        echo '<hr />';

        foreach ($results as $result) {
            $status = (string) $result['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            if ($group !== $result['group']) {
                $group = (string) $result['group'];
                echo '<h2 style="margin-bottom:.3em">' . esc_html($group) . '</h2>';
            }

            [$color, $icon] = $styles[$status] ?? ['#57606a', '•'];

            printf(
                '<div style="margin:.35em 0 .35em 0;padding-right:.5em;border-right:3px solid %1$s"><span style="color:%1$s;font-weight:600">%2$s</span> %3$s%4$s</div>',
                esc_attr($color),
                esc_html($icon),
                esc_html((string) $result['label']),
                '' === $result['detail']
                    ? ''
                    : '<div style="color:#57606a;margin-right:1.4em;font-size:12px">' . esc_html((string) $result['detail']) . '</div>'
            );
        }

        printf(
            '<p style="margin-top:1.5em"><strong>%s</strong></p>',
            esc_html(sprintf(
                /* translators: 1: تعداد سالم، 2: تعداد هشدار، 3: تعداد خطا */
                __('%1$d سالم، %2$d هشدار، %3$d خطا', 'zig3d-ai-access'),
                $counts[Doctor::OK],
                $counts[Doctor::WARN],
                $counts[Doctor::FAIL]
            ))
        );
    }
}

<?php
namespace Zig3d_AI_Access;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نقطهٔ ورودِ افزونه — فقط سیم‌کشیِ هوک‌ها. هیچ فهرستِ هاردکدی از
 * ماژول‌هایِ صفحه این‌جا نیست؛ ‎Route::providers()‎ آن‌ها را از فیلترِ
 * ‎zig3d_ai_access/providers‎ می‌خواند. افزودنِ صفحهٔ جدید یعنی یک فایلِ
 * تازهٔ ‎src/Schema/XProvider.php‎ که در همان فایل خودش را به همان
 * فیلتر اضافه می‌کند — بدونِ ویرایشِ این فایل. ‎load_providers()‎ فقط
 * پوشه را می‌گردد و هر فایل را ‎require‎ می‌کند تا آن ثبتِ خودکار اجرا
 * شود؛ اسمِ هیچ کلاسی این‌جا هاردکد نیست.
 */
final class Plugin {

    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->load_providers();

        add_action('wp_head', [$this, 'print_schema'], 30);

        RankMath\Neutralizer::boot();

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('zig3d dump-element', [Cli\DumpElement::class, 'run']);
        }
    }

    /**
     * هر فایلِ زیرِ ‎src/Schema/‎ را ‎require‎ می‌کند. هرکدام در انتهایِ
     * همان فایل خودش را با ‎add_filter('zig3d_ai_access/providers', …)‎
     * ثبت می‌کند — پس این متد هیچ‌وقت نیازی به ویرایش برایِ اضافه‌شدنِ
     * یک صفحهٔ جدید ندارد، فقط یک فایلِ جدید در آن پوشه.
     */
    private function load_providers(): void {
        $files = glob(ZIG3D_AI_ACCESS_PATH . 'src/Schema/*.php');

        foreach ($files ?: [] as $file) {
            require_once $file;
        }
    }

    public function print_schema(): void {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax()) {
            return;
        }

        Diagnostics::reset();
        Graph::reset();

        Graph::add(Nodes\OrganizationNode::node());
        Graph::add(Nodes\WebsiteNode::node());

        $provider = Route::matching_provider();

        if (null === $provider) {
            Diagnostics::drop('page', 'no Schema_Provider matched the current request');
        } else {
            Graph::add_many($provider->nodes());
        }

        Graph::print_script();
    }
}

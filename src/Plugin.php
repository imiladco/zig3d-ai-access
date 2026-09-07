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
        // زیرساختِ عرضی، پیش از هر چیزی که ممکن است از آن استفاده کند
        Support\Cache::boot();

        $this->load_providers();

        add_action('wp_head', [$this, 'print_schema'], 30);

        RankMath\Neutralizer::boot();

        $this->boot_features();

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

    /**
     * همان الگویِ ‎load_providers()‎، یک لایه بالاتر: هر فایلِ زیرِ
     * ‎src/Feature/‎ خودش را به فیلترِ ‎zig3d_ai_access/features‎ اضافه
     * می‌کند و این‌جا فقط ‎boot()‎ش صدا زده می‌شود.
     *
     * چرا فیچرها با Providerها یک رجیستری نیستند: Providerها یک چرخهٔ
     * عمرِ مشترک دارند (‎wp_head‎ → ‎applies()‎ → ‎nodes()‎)، فیچرها ندارند.
     * نگاه کنید به ‎Feature_Module‎.
     *
     * یک ‎boot()‎ی خطاکار نباید بقیهٔ فیچرها — یا کلِ سایت — را
     * بخواباند: هر کدام جدا گرفته می‌شود و شکستش در ‎Diagnostics‎
     * می‌نشیند. ‎Throwable‎ و نه ‎Exception‎، چون یک ‎Error‎ی PHP
     * (مثلاً متدِ نبودهٔ یک افزونهٔ ثالث) دقیقاً همان حالتی است که
     * نباید صفحهٔ سفید بدهد.
     */
    private function boot_features(): void {
        foreach (glob(ZIG3D_AI_ACCESS_PATH . 'src/Feature/*.php') ?: [] as $file) {
            require_once $file;
        }

        /** @var array<int,Feature_Module> $features */
        $features = (array) apply_filters('zig3d_ai_access/features', []);

        foreach ($features as $feature) {
            if (!$feature instanceof Feature_Module) {
                Diagnostics::drop('feature', sprintf('registered value of type "%s" is not a Feature_Module — skipped', is_object($feature) ? get_class($feature) : gettype($feature)));

                continue;
            }

            try {
                $feature->boot();
            } catch (\Throwable $e) {
                Diagnostics::drop('feature', sprintf('%s::boot() threw: %s', get_class($feature), $e->getMessage()));
            }
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

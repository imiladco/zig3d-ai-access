<?php
/**
 * Plugin Name:       ZIG3D AI Access (Schema.org)
 * Plugin URI:        https://zig3d.com
 * Description:       لایهٔ Schema.org (JSON-LD) اختصاصیِ سایتِ زیگ — مستقل از افزونهٔ سئوی فعلی، هماهنگ با ساختار داده و ویجت‌های اختصاصیِ این سایت.
 * Version:           2.0.0
 * Author:            imiladco
 * Author URI:        https://zig3d.com
 * Text Domain:       zig3d-ai-access
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.0
 *
 * @package Zig3d_AI_Access
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ZIG3D_AI_ACCESS_VERSION', '2.0.0');
define('ZIG3D_AI_ACCESS_FILE', __FILE__);
define('ZIG3D_AI_ACCESS_PATH', plugin_dir_path(__FILE__));
define('ZIG3D_AI_ACCESS_URL', plugin_dir_url(__FILE__));

define('ZIG3D_AI_ACCESS_MIN_PHP', '7.4');

/**
 * اتولودرِ PSR-4-مانند — بدونِ composer، چون این افزونه هیچ وابستگیِ
 * بیرونی ندارد و نصب‌شدنش با کپی‌کردنِ یک پوشه است، نه ‎composer install‎.
 *
 * نگاشت: ‎Zig3d_AI_Access\Foo\Bar‎ → ‎src/Foo/Bar.php‎ (زیرِ ریشهٔ همین
 * namespace). فقط کلاس‌های همین افزونه را می‌شناسد — namespace هایِ
 * دیگر را نادیده می‌گذارد و کنترل را فوراً پس می‌دهد.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Zig3d_AI_Access\\';

    if (0 !== strncmp($class, $prefix, strlen($prefix))) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path     = ZIG3D_AI_ACCESS_PATH . 'src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

/**
 * بارگذاری افزونه بعد از لود شدن همهٔ افزونه‌ها.
 *
 * برخلافِ «ویجت‌های زیگ»، این افزونه به المنتور یا ووکامرس *وابسته* نیست —
 * فقط هرجا لازم دارد (مثلاً کلاس‌های ووکامرس برایِ محصول، یا دادهٔ
 * JetEngine برایِ نرم‌افزارها) با ‎class_exists()‎/‎function_exists()‎ چک
 * می‌کند و همان بخش را بی‌صدا حذف می‌کند (با ثبتِ دلیل در ‎Diagnostics‎).
 * نبودِ یک وابستگی نباید کلِ schemaِ صفحاتِ دیگر را بخواباند.
 */
function zig3d_ai_access_init(): void {

    add_action('init', static function (): void {
        load_plugin_textdomain(
            'zig3d-ai-access',
            false,
            dirname(plugin_basename(ZIG3D_AI_ACCESS_FILE)) . '/languages'
        );
    });

    if (version_compare(PHP_VERSION, ZIG3D_AI_ACCESS_MIN_PHP, '<')) {
        add_action('admin_notices', 'zig3d_ai_access_notice_php');

        return;
    }

    \Zig3d_AI_Access\Plugin::instance();
}
add_action('plugins_loaded', 'zig3d_ai_access_init');

/**
 * فعال‌سازی: فقط امضایِ قواعدِ بازنویسی پاک می‌شود تا ‎Support\Rewrite‎
 * رویِ ‎init‎ی همان درخواست یک بار flush کند.
 *
 * چرا خودِ ‎flush_rewrite_rules()‎ این‌جا صدا زده نمی‌شود: در لحظهٔ
 * فعال‌سازی هنوز ‎init‎ اجرا نشده و قواعدِ ما ثبت نشده‌اند، پس یک flushِ
 * این‌جا دقیقاً همان قواعدی را که می‌خواهیم جا بیندازیم نمی‌بیند.
 */
register_activation_hook(__FILE__, static function (): void {
    if (class_exists('\Zig3d_AI_Access\Support\Rewrite')) {
        \Zig3d_AI_Access\Support\Rewrite::on_activation();
    }
});

function zig3d_ai_access_notice_php(): void {
    printf(
        '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
        esc_html(sprintf(
            /* translators: %s: نسخهٔ موردنیاز PHP */
            __('افزونهٔ «ZIG3D AI Access» به PHP نسخهٔ %s یا بالاتر نیاز دارد.', 'zig3d-ai-access'),
            ZIG3D_AI_ACCESS_MIN_PHP
        ))
    );
}

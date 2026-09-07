<?php
namespace Zig3d_AI_Access\Cli;

use Zig3d_AI_Access\Support\ElementorReader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ‎wp zig3d dump-element <element_id>‎ — تنظیماتِ خامِ یک المنتِ زندهٔ
 * المنتور را چاپ می‌کند.
 *
 * دلیلِ وجودش: اصل ۴ی سندِ معماری («لایهٔ خواندنِ داده، سخت‌شده»). به‌جایِ
 * حدس‌زدنِ کلیدِ تنظیماتِ Loop Grid (که بینِ نسخه‌هایِ المنتور پرو فرق
 * می‌کند) و نوشتنش داخلِ کد، این دستور کلیدهایِ *واقعیِ* آن نمونهٔ
 * بخصوص را رویِ همین نصب نشان می‌دهد — نتیجه مستقیم می‌رود توی
 * ‎config.php‎، نه توی کد.
 */
final class DumpElement {

    /**
     * ## OPTIONS
     *
     * <element_id>
     * : شناسهٔ المنتِ المنتور (مثلِ ac64ce3)
     *
     * [--post_id=<post_id>]
     * : پستی که این المنت داخلِ آن است (می‌تواند یک قالبِ المنتور هم
     *   باشد). اگر داده نشود، تا ۲۰۰ پستِ اخیری که ‎_elementor_data‎
     *   دارند جست‌وجو می‌شود.
     *
     * ## EXAMPLES
     *
     *     wp zig3d dump-element ac64ce3
     *     wp zig3d dump-element ac64ce3 --post_id=15031
     *
     * @param array<int,string> $args
     * @param array<string,string> $assoc_args
     */
    public static function run(array $args, array $assoc_args): void {
        $element_id = (string) ($args[0] ?? '');

        if ('' === $element_id) {
            \WP_CLI::error('شناسهٔ المنت لازم است: wp zig3d dump-element <element_id>');

            return;
        }

        $post_ids = !empty($assoc_args['post_id'])
            ? [(int) $assoc_args['post_id']]
            : self::recent_post_ids();

        foreach ($post_ids as $post_id) {
            $element = ElementorReader::find_by_id($post_id, $element_id);

            if (null === $element) {
                continue;
            }

            \WP_CLI::success(sprintf('پیدا شد در پستِ #%d', $post_id));
            \WP_CLI::line(sprintf('elType: %s', (string) ($element['elType'] ?? '?')));
            \WP_CLI::line(sprintf('widgetType: %s', (string) ($element['widgetType'] ?? '—')));
            \WP_CLI::line('settings:');
            \WP_CLI::line((string) wp_json_encode(
                $element['settings'] ?? [],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return;
        }

        \WP_CLI::error(sprintf(
            'المنتِ "%s" در %d پستِ بررسی‌شده پیدا نشد. ‎--post_id=<id>‎ را مستقیم بده اگر می‌دانی کجاست.',
            $element_id,
            count($post_ids)
        ));
    }

    /** @return int[] */
    private static function recent_post_ids(): array {
        $query = new \WP_Query([
            'post_type'      => 'any',
            'post_status'    => 'any',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'meta_key'       => '_elementor_data',
            'no_found_rows'  => true,
        ]);

        return array_map('intval', $query->posts);
    }
}

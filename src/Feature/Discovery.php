<?php
namespace Zig3d_AI_Access\Feature;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Feature_Module;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * کشفِ ‎llms.txt‎ — **دقیقاً یک** اعلان، و فقط چون اسپک تعریفش کرده.
 *
 * متنِ اسپک (‎llmstxt.org‎، بررسی‌شده از خودِ منبع):
 *
 *   «‎rel="alternate" type="text/markdown"‎ به نسخهٔ مارک‌داونیِ *یک
 *   صفحه* اشاره می‌کند، و ‎rel="describedby"‎ به فایلِ ‎llms.txt‎ی که
 *   آن را پوشش می‌دهد.»
 *
 * پس:
 *
 * - ‎describedby‎ چاپ **می‌شود** — تعریف‌شده و دقیقاً همین کاربرد.
 * - ‎alternate/text/markdown‎ چاپ **نمی‌شود**. این سایت نسخهٔ مارک‌داونیِ
 *   صفحه ندارد؛ چاپش یعنی اعلانِ چیزی که وجود ندارد و هر کلاینتی که
 *   دنبالش برود ۴۰۴ می‌گیرد. یک ‎rel‎ی جعلی هم اختراع نمی‌شود.
 *
 * چرا رویِ همهٔ صفحه‌ها: طبقِ همان بند، یک ‎llms.txt‎ همهٔ صفحاتِ زیرِ
 * مسیرِ خودش را پوشش می‌دهد و مالِ ما در ریشه است — پس کلِ سایت را
 * پوشش می‌دهد.
 *
 * هدرِ HTTPی ‎Link:‎ هم در اسپک آمده، ولی به‌عنوانِ *جایگزین* برایِ
 * وقتی که نمی‌شود صفحه را عوض کرد. چاپِ هر دو یعنی یک اعلانِ تکراری،
 * پس فقط ‎<link>‎.
 */
final class Discovery implements Feature_Module {

    public function boot(): void {
        if (!Llms::enabled() || !(bool) Config::get('llms.discovery_link', true)) {
            return;
        }

        add_action('wp_head', [$this, 'print_link'], 1);
    }

    public function print_link(): void {
        if (is_feed() || is_404()) {
            return;
        }

        $url = Llms::url();

        if (null === $url) {
            Diagnostics::drop('discovery', 'llms.txt has no canonical URL on this install — link omitted');

            return;
        }

        printf('<link rel="describedby" href="%s" />' . "\n", esc_url($url));
    }
}

add_filter('zig3d_ai_access/features', static function (array $features): array {
    $features[] = new Discovery();

    return $features;
});

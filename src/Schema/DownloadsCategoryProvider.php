<?php
namespace Zig3d_AI_Access\Schema;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Schema_Provider;
use Zig3d_AI_Access\Nodes\BreadcrumbNode;
use Zig3d_AI_Access\Nodes\FaqPageNode;
use Zig3d_AI_Access\Nodes\WebPageNode;
use Zig3d_AI_Access\Support\Archive;
use Zig3d_AI_Access\Support\Downloads;
use Zig3d_AI_Access\Support\EntityIds;
use Zig3d_AI_Access\Support\Faq;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آرشیوِ یک دستهٔ نرم‌افزار — یک فایلِ عمومی برایِ همهٔ ترم‌ها.
 *
 * آدرسِ کانونیکال همیشه از ‎get_term_link()‎ می‌آید، نه از الگویِ حدسیِ
 * URL: رنک‌مث رویِ همین صفحات ‎/software-category/{slug}‎ می‌ساخت درحالی
 * که آدرسِ واقعی ‎/downloads/{slug}‎ است — همان درسی که یک‌بار هم رویِ
 * ‎/product-category/‎ در برابرِ ‎/shop/‎ گرفتیم.
 */
final class DownloadsCategoryProvider implements Schema_Provider {

    public function applies(): bool {
        $taxonomy = (string) Config::get('downloads.taxonomy', '');

        return '' !== $taxonomy && is_tax($taxonomy);
    }

    public function rank_math_scope(): string {
        return 'downloads-category';
    }

    /** @return array<int,array<string,mixed>> */
    public function nodes(): array {
        $term = get_queried_object();

        if (!$term instanceof \WP_Term) {
            Diagnostics::drop('downloads-category', 'get_queried_object() did not return a WP_Term');

            return [];
        }

        $base_url = get_term_link($term);

        if (is_wp_error($base_url)) {
            Diagnostics::drop('downloads-category', 'get_term_link() failed: ' . $base_url->get_error_message());

            return [];
        }

        $paged = Archive::paged();
        $url   = Archive::current_url((string) $base_url, $paged);

        $nodes = [];

        $breadcrumb = BreadcrumbNode::node($url, [
            ['name' => 'خانه', 'url' => home_url('/')],
            ['name' => 'نرم‌افزارها', 'url' => Downloads::archive_url()],
            ['name' => (string) $term->name, 'url' => (string) $base_url],
        ]);

        if (null !== $breadcrumb) {
            $nodes[] = $breadcrumb;
        }

        $extra = ['inLanguage' => 'fa-IR'];

        $description = trim(wp_strip_all_tags((string) term_description((int) $term->term_id), true));

        if ('' !== $description) {
            $extra['description'] = trim(preg_replace('/\s+/u', ' ', $description) ?? '');
        }

        $count = Archive::found_posts() ?: (int) $term->count;

        if ($count > 0) {
            $extra['mainEntity'] = ['@type' => 'ItemList', 'numberOfItems' => $count];
        }

        if (null !== $breadcrumb) {
            $extra['breadcrumb'] = ['@id' => (string) $breadcrumb['@id']];
        }

        $nodes[] = WebPageNode::node($url, 'CollectionPage', $extra);

        $list = Downloads::software_list($url, $paged, 'downloads-category');

        if (null !== $list) {
            $nodes[] = $list;
        }

        $faq = FaqPageNode::node($url, Faq::for_term((int) $term->term_id), 'downloads-category.faq');

        if (null !== $faq) {
            $nodes[] = $faq;
        }

        return $nodes;
    }
}

add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new DownloadsCategoryProvider();

    return $providers;
});

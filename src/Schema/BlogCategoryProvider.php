<?php
namespace Zig3d_AI_Access\Schema;

use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Schema_Provider;
use Zig3d_AI_Access\Nodes\BreadcrumbNode;
use Zig3d_AI_Access\Nodes\WebPageNode;
use Zig3d_AI_Access\Support\Archive;
use Zig3d_AI_Access\Support\Blog;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آرشیوِ یک دستهٔ بلاگ — عمومی برایِ هر سه دسته، نه فایلی به‌ازایِ هرکدام.
 *
 * آدرسِ کانونیکال از ‎get_term_link()‎: رنک‌مث این‌جا
 * ‎/category/{slug}/‎ می‌ساخت درحالی که آدرسِ واقعیِ صفحه
 * ‎/blog/{slug}‎ است — همان درسِ تکرارشونده.
 */
final class BlogCategoryProvider implements Schema_Provider {

    public function applies(): bool {
        return is_category();
    }

    public function rank_math_scope(): string {
        return 'blog-category';
    }

    /** @return array<int,array<string,mixed>> */
    public function nodes(): array {
        $term = get_queried_object();

        if (!$term instanceof \WP_Term) {
            Diagnostics::drop('blog-category', 'get_queried_object() did not return a WP_Term');

            return [];
        }

        $base_url = get_term_link($term);

        if (is_wp_error($base_url)) {
            Diagnostics::drop('blog-category', 'get_term_link() failed: ' . $base_url->get_error_message());

            return [];
        }

        $paged = Archive::paged();
        $url   = Archive::current_url((string) $base_url, $paged);

        $nodes = [];

        $breadcrumb = BreadcrumbNode::node($url, [
            ['name' => 'خانه', 'url' => home_url('/')],
            ['name' => Blog::home_title(), 'url' => Blog::home_url()],
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

        $list = Blog::post_list($url, $paged, 'blog-category');

        if (null !== $list) {
            $nodes[] = $list;
        }

        return $nodes;
    }
}

add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new BlogCategoryProvider();

    return $providers;
});

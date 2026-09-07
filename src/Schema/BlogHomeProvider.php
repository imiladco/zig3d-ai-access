<?php
namespace Zig3d_AI_Access\Schema;

use Zig3d_AI_Access\Schema_Provider;
use Zig3d_AI_Access\Nodes\BreadcrumbNode;
use Zig3d_AI_Access\Nodes\WebPageNode;
use Zig3d_AI_Access\Support\Archive;
use Zig3d_AI_Access\Support\Blog;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ اصلیِ بلاگ.
 *
 * الگو عمداً ‎CollectionPage → ItemList‎ است، نه ‎Blog → blogPost‎: هر دو
 * از نظرِ schema.org معتبرند، ولی این یکی با ‎/shop‎ و ‎/downloads‎ یکسان
 * است و همان اجزایِ مشترک (‎Archive‎، ‎ItemListNode‎) را به‌کار می‌گیرد.
 *
 * بدونِ FAQ — این آرشیو سؤالِ متداول ندارد.
 */
final class BlogHomeProvider implements Schema_Provider {

    public function applies(): bool {
        // صفحهٔ اصلیِ بلاگ، نه صفحهٔ اصلیِ سایت وقتی «آخرین نوشته‌ها» است
        return is_home() && !is_front_page();
    }

    public function rank_math_scope(): string {
        return 'blog-home';
    }

    /** @return array<int,array<string,mixed>> */
    public function nodes(): array {
        $base_url = Blog::home_url();
        $paged    = Archive::paged();
        $url      = Archive::current_url($base_url, $paged);

        $nodes = [];

        $breadcrumb = BreadcrumbNode::node($url, [
            ['name' => 'خانه', 'url' => home_url('/')],
            ['name' => Blog::home_title(), 'url' => $base_url],
        ]);

        if (null !== $breadcrumb) {
            $nodes[] = $breadcrumb;
        }

        $extra = ['inLanguage' => 'fa-IR'];

        $found = Archive::found_posts();

        if ($found > 0) {
            $extra['mainEntity'] = ['@type' => 'ItemList', 'numberOfItems' => $found];
        }

        if (null !== $breadcrumb) {
            $extra['breadcrumb'] = ['@id' => (string) $breadcrumb['@id']];
        }

        $nodes[] = WebPageNode::node($url, 'CollectionPage', $extra);

        $list = Blog::post_list($url, $paged, 'blog-home');

        if (null !== $list) {
            $nodes[] = $list;
        }

        return $nodes;
    }
}

add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new BlogHomeProvider();

    return $providers;
});

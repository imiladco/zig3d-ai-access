<?php
namespace Zig3d_AI_Access\Schema;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Schema_Provider;
use Zig3d_AI_Access\Nodes\BreadcrumbNode;
use Zig3d_AI_Access\Nodes\ItemListNode;
use Zig3d_AI_Access\Nodes\SoftwareNode;
use Zig3d_AI_Access\Nodes\WebPageNode;
use Zig3d_AI_Access\Support\Archive;
use Zig3d_AI_Access\Support\Downloads;
use Zig3d_AI_Access\Support\EntityIds;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آرشیوِ اصلیِ نرم‌افزارها (‎/downloads‎).
 *
 * برخلافِ ‎/shop‎: نه فهرستِ «دسته‌هایِ شاخص» دارد (دسته‌ها این‌جا فقط
 * فیلترِ URLاند، طبقِ تأییدِ کاربر) و نه FAQ. اگر روزی FAQ اضافه شد،
 * همان مسیرِ مشترکِ ‎FaqPageNode‎ کافی است.
 */
final class DownloadsHomeProvider implements Schema_Provider {

    public function applies(): bool {
        $post_type = Downloads::post_type();

        return '' !== $post_type && is_post_type_archive($post_type);
    }

    public function rank_math_scope(): string {
        return 'downloads-home';
    }

    /** @return array<int,array<string,mixed>> */
    public function nodes(): array {
        $base_url = Downloads::archive_url();

        if ('' === $base_url) {
            Diagnostics::drop('downloads-home', 'get_post_type_archive_link() returned nothing — cannot build stable @ids');

            return [];
        }

        $paged = Archive::paged();
        $url   = Archive::current_url($base_url, $paged);

        $nodes = [];

        $breadcrumb = BreadcrumbNode::node($url, [
            ['name' => 'خانه', 'url' => home_url('/')],
            ['name' => 'نرم‌افزارها', 'url' => $base_url],
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

        $list = Downloads::software_list($url, $paged, 'downloads-home');

        if (null !== $list) {
            $nodes[] = $list;
        }

        return $nodes;
    }
}

add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new DownloadsHomeProvider();

    return $providers;
});

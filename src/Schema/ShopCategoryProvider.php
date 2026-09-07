<?php
namespace Zig3d_AI_Access\Schema;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Schema_Provider;
use Zig3d_AI_Access\Nodes\BreadcrumbNode;
use Zig3d_AI_Access\Nodes\FaqPageNode;
use Zig3d_AI_Access\Nodes\ItemListNode;
use Zig3d_AI_Access\Nodes\ProductNode;
use Zig3d_AI_Access\Nodes\WebPageNode;
use Zig3d_AI_Access\Support\Archive;
use Zig3d_AI_Access\Support\EntityIds;
use Zig3d_AI_Access\Support\Faq;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آرشیوِ یک دستهٔ محصول — یک فایلِ عمومی برایِ *همهٔ* ترم‌هایِ
 * ‎product_cat‎، نه یکی به‌ازایِ هر دسته (تأکیدِ صریحِ راهنما).
 *
 * FAQ این‌جا از متایِ *همان ترم* خوانده می‌شود، نه یک FAQِ سراسری —
 * هر دسته می‌تواند سؤالاتِ خودش را داشته باشد.
 */
final class ShopCategoryProvider implements Schema_Provider {

    public function applies(): bool {
        return function_exists('is_tax') && is_tax('product_cat');
    }

    public function rank_math_scope(): string {
        return 'shop-category';
    }

    /** @return array<int,array<string,mixed>> */
    public function nodes(): array {
        $term = get_queried_object();

        if (!$term instanceof \WP_Term) {
            Diagnostics::drop('shop-category', 'get_queried_object() did not return a WP_Term');

            return [];
        }

        $base_url = get_term_link($term);

        if (is_wp_error($base_url)) {
            Diagnostics::drop('shop-category', 'get_term_link() failed: ' . $base_url->get_error_message());

            return [];
        }

        $paged = Archive::paged();
        $url   = Archive::current_url((string) $base_url, $paged);

        $nodes = [];

        $breadcrumb = BreadcrumbNode::node($url, [
            ['name' => 'خانه', 'url' => home_url('/')],
            ['name' => 'فروشگاه', 'url' => function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : ''],
            ['name' => (string) $term->name, 'url' => (string) $base_url],
        ]);

        if (null !== $breadcrumb) {
            $nodes[] = $breadcrumb;
        }

        $nodes[] = $this->collection_page($url, $term, $breadcrumb);

        $products = $this->products($url, $paged, $term);

        if (null !== $products) {
            $nodes[] = $products;
        }

        $faq = FaqPageNode::node($url, Faq::for_term((int) $term->term_id), 'shop-category.faq');

        if (null !== $faq) {
            $nodes[] = $faq;
        }

        return $nodes;
    }

    /* ------------------------------------------------------------------ */

    /**
     * @param array<string,mixed>|null $breadcrumb
     * @return array<string,mixed>
     */
    private function collection_page(string $url, \WP_Term $term, ?array $breadcrumb): array {
        $extra = [
            'inLanguage' => 'fa-IR',
            // موضوعِ صفحه: همان ‎@id‎ی پایدارِ دسته که همه‌جا استفاده می‌شود
            'about' => [
                '@type' => 'Thing',
                '@id'   => EntityIds::category((string) $term->slug),
                'name'  => (string) $term->name,
            ],
        ];

        // توضیحِ ترم — متنی که مدیر در ادمین می‌نویسد، نه چیزی هاردکد
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

        $guide = $this->buying_guide_url((int) $term->term_id);

        if (null !== $guide) {
            $extra['relatedLink'] = [$guide];
        }

        return WebPageNode::node($url, 'CollectionPage', $extra);
    }

    /**
     * لینکِ «راهنمایِ خرید» — اختیاری. فقط اگر برایِ *همین ترم* ثبت شده
     * باشد؛ راهنما صریح می‌گوید برایِ دسته‌هایی که این بخش را ندارند
     * چیزی هاردکد نشود.
     */
    private function buying_guide_url(int $term_id): ?string {
        $meta_key = (string) Config::get('shop_category.buying_guide_meta', '');

        if ('' === $meta_key) {
            return null;
        }

        $url = trim((string) get_term_meta($term_id, $meta_key, true));

        return 0 === strpos($url, 'http') ? $url : null;
    }

    /** @return array<string,mixed>|null */
    private function products(string $url, int $paged, \WP_Term $term): ?array {
        $ids = Archive::current_ids();

        if (!$ids) {
            Diagnostics::drop('shop-category.products', sprintf('the main WP_Query returned no posts for term "%s"', $term->slug));

            return null;
        }

        $items = [];

        foreach ($ids as $id) {
            $product = ProductNode::summary($id);

            if (null !== $product) {
                $items[] = $product;
            }
        }

        if (!$items) {
            Diagnostics::drop('shop-category.products', 'no product on this page could be turned into a valid Product node');

            return null;
        }

        return ItemListNode::node(
            $url,
            Archive::list_suffix('products', $paged),
            $items,
            Archive::first_position($paged, Archive::per_page()),
            EntityIds::webpage($url)
        );
    }
}

add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new ShopCategoryProvider();

    return $providers;
});

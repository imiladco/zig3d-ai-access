<?php
namespace Zig3d_AI_Access\Schema;

use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Schema_Provider;
use Zig3d_AI_Access\Nodes\BreadcrumbNode;
use Zig3d_AI_Access\Nodes\FaqPageNode;
use Zig3d_AI_Access\Nodes\ItemListNode;
use Zig3d_AI_Access\Nodes\OrganizationNode;
use Zig3d_AI_Access\Nodes\ProductNode;
use Zig3d_AI_Access\Nodes\WebPageNode;
use Zig3d_AI_Access\Support\Archive;
use Zig3d_AI_Access\Support\EntityIds;
use Zig3d_AI_Access\Support\Faq;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آرشیوِ اصلیِ فروشگاه (‎/shop‎).
 *
 * دو تصمیمِ راهنما که این‌جا پیاده شده‌اند:
 *  ۱) «دسته‌هایِ شاخص» همان ‎@id‎ی صفحهٔ اصلی را می‌گیرد
 *     (‎#featured-categories‎) — چون *همان* موجودیت است، نه یک لیستِ
 *     تازه. اگر روزی محتوایش با صفحهٔ اصلی فرق کرد، آن‌وقت باید
 *     ‎@id‎ جدا شود.
 *  ۲) ‎ItemList‎ی محصولات از کوئریِ *واقعیِ* همین صفحه می‌آید، با
 *     ‎position‎ی ادامه‌دار بینِ صفحات.
 */
final class ShopHomeProvider implements Schema_Provider {

    public function applies(): bool {
        return function_exists('is_shop') && is_shop() && !is_search();
    }

    public function rank_math_scope(): string {
        return 'shop-home';
    }

    /** @return array<int,array<string,mixed>> */
    public function nodes(): array {
        $base_url = function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : '';

        if ('' === $base_url) {
            Diagnostics::drop('shop-home', 'wc_get_page_permalink("shop") returned nothing — cannot build stable @ids');

            return [];
        }

        $paged = Archive::paged();
        $url   = Archive::current_url($base_url, $paged);

        $nodes = [];

        $breadcrumb = BreadcrumbNode::node($url, [
            ['name' => 'خانه', 'url' => home_url('/')],
            ['name' => self::shop_title(), 'url' => $base_url],
        ]);

        if (null !== $breadcrumb) {
            $nodes[] = $breadcrumb;
        }

        $nodes[] = $this->collection_page($url, $breadcrumb);

        $categories = $this->featured_categories();

        if (null !== $categories) {
            $nodes[] = $categories;
        }

        $products = $this->products($url, $paged);

        if (null !== $products) {
            $nodes[] = $products;
        }

        $faq = FaqPageNode::node($url, Faq::for_post((int) wc_get_page_id('shop')), 'shop-home.faq');

        if (null !== $faq) {
            $nodes[] = $faq;
        }

        return $nodes;
    }

    /* ------------------------------------------------------------------ */

    /** @param array<string,mixed>|null $breadcrumb */
    private function collection_page(string $url, ?array $breadcrumb): array {
        $extra = ['inLanguage' => 'fa-IR'];

        $found = Archive::found_posts();

        if ($found > 0) {
            // عددِ *کلِ* فروشگاه — عمداً این‌جا، نه در ItemList
            $extra['mainEntity'] = ['@type' => 'ItemList', 'numberOfItems' => $found];
        }

        if (null !== $breadcrumb) {
            $extra['breadcrumb'] = ['@id' => (string) $breadcrumb['@id']];
        }

        $shop_id = function_exists('wc_get_page_id') ? (int) wc_get_page_id('shop') : 0;

        if ($shop_id > 0) {
            $description = trim(wp_strip_all_tags((string) get_post_field('post_content', $shop_id), true));

            if ('' !== $description) {
                $extra['description'] = trim(preg_replace('/\s+/u', ' ', $description) ?? '');
            }
        }

        return WebPageNode::node($url, 'CollectionPage', $extra);
    }

    /**
     * همان فهرستِ صفحهٔ اصلی — همان ‎@id‎، همان ترم‌ها.
     *
     * @return array<string,mixed>|null
     */
    private function featured_categories(): ?array {
        if (!taxonomy_exists('product_cat')) {
            Diagnostics::drop('shop-home.featured-categories', 'taxonomy product_cat does not exist');

            return null;
        }

        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true]);

        if (is_wp_error($terms) || !$terms) {
            Diagnostics::drop('shop-home.featured-categories', 'get_terms(product_cat) returned no usable terms');

            return null;
        }

        $items = [];
        $position = 0;

        foreach ($terms as $term) {
            $link = get_term_link($term);

            if (is_wp_error($link)) {
                continue;
            }

            $position++;

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'item'     => [
                    '@type' => 'Thing',
                    '@id'   => EntityIds::category((string) $term->slug),
                    'name'  => (string) $term->name,
                    'url'   => (string) $link,
                ],
            ];
        }

        if (!$items) {
            Diagnostics::drop('shop-home.featured-categories', 'all product_cat terms failed get_term_link()');

            return null;
        }

        return [
            '@type'           => 'ItemList',
            '@id'             => EntityIds::fragment('featured-categories'),
            'name'            => 'دسته‌های محصولات زیگ',
            'itemListElement' => $items,
        ];
    }

    /** @return array<string,mixed>|null */
    private function products(string $url, int $paged): ?array {
        $ids = Archive::current_ids();

        if (!$ids) {
            Diagnostics::drop('shop-home.products', 'the main WP_Query returned no posts for this shop page');

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
            Diagnostics::drop('shop-home.products', 'no product on this page could be turned into a valid Product node');

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

    private static function shop_title(): string {
        $shop_id = function_exists('wc_get_page_id') ? (int) wc_get_page_id('shop') : 0;
        $title = $shop_id > 0 ? trim((string) get_the_title($shop_id)) : '';

        return '' !== $title ? $title : 'فروشگاه';
    }
}

add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new ShopHomeProvider();

    return $providers;
});

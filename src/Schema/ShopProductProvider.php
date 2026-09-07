<?php
namespace Zig3d_AI_Access\Schema;

use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Schema_Provider;
use Zig3d_AI_Access\Nodes\BreadcrumbNode;
use Zig3d_AI_Access\Nodes\FaqPageNode;
use Zig3d_AI_Access\Nodes\ImageNode;
use Zig3d_AI_Access\Nodes\ProductNode;
use Zig3d_AI_Access\Nodes\WebPageNode;
use Zig3d_AI_Access\Support\EntityIds;
use Zig3d_AI_Access\Support\Faq;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ تکِ محصول.
 *
 * سه قاعدهٔ صریحِ راهنما که این‌جا رعایت شده‌اند:
 *  - ‎primaryImageOfPage‎ فقط ‎@id‎ی یتیم نیست؛ خودِ ‎ImageObject‎ هم به
 *    ‎@graph‎ اضافه می‌شود.
 *  - ‎priceValidUntil‎ اضافه **نمی‌شود** (قیمت‌ها روزانه عوض می‌شوند؛
 *    هر تاریخِ حدسی گمراه‌کننده است) — نگاه کنید به ‎ProductNode‎.
 *  - ‎aggregateRating‎/‎review‎ جعلی ساخته نمی‌شود؛ فقط اگر نظرِ واقعی
 *    در دیتابیس باشد.
 */
final class ShopProductProvider implements Schema_Provider {

    public function applies(): bool {
        return function_exists('is_product') ? is_product() : is_singular('product');
    }

    public function rank_math_scope(): string {
        return 'shop-product';
    }

    /** @return array<int,array<string,mixed>> */
    public function nodes(): array {
        $product_id = (int) get_queried_object_id();

        if ($product_id <= 0) {
            Diagnostics::drop('shop-product', 'get_queried_object_id() returned 0');

            return [];
        }

        $url = (string) get_permalink($product_id);

        if ('' === $url) {
            Diagnostics::drop('shop-product', sprintf('get_permalink(%d) returned nothing', $product_id));

            return [];
        }

        $nodes = [];

        $breadcrumb = BreadcrumbNode::node($url, $this->trail($product_id, $url));

        if (null !== $breadcrumb) {
            $nodes[] = $breadcrumb;
        }

        $image = ImageNode::from_attachment((int) get_post_thumbnail_id($product_id));

        $nodes[] = $this->item_page($url, $product_id, $breadcrumb, $image);

        if (null !== $image) {
            $nodes[] = $image;
        } else {
            Diagnostics::drop('shop-product.image', sprintf('product #%d has no featured image', $product_id));
        }

        $product = ProductNode::full($product_id);

        if (null !== $product) {
            $this->attach_rating($product, $product_id);

            $nodes[] = $product;
        }

        $faq = FaqPageNode::node($url, Faq::for_post($product_id), 'shop-product.faq');

        if (null !== $faq) {
            $nodes[] = $faq;
        }

        return $nodes;
    }

    /* ------------------------------------------------------------------ */

    /** @return array<int,array{name:string,url:string}> */
    private function trail(int $product_id, string $url): array {
        $trail = [
            ['name' => 'خانه', 'url' => home_url('/')],
            ['name' => 'فروشگاه', 'url' => function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : ''],
        ];

        $terms = get_the_terms($product_id, 'product_cat');

        if (!is_wp_error($terms) && $terms) {
            $term = $terms[0];
            $link = get_term_link($term);

            if (!is_wp_error($link)) {
                $trail[] = ['name' => (string) $term->name, 'url' => (string) $link];
            }
        }

        $trail[] = ['name' => (string) get_the_title($product_id), 'url' => $url];

        return $trail;
    }

    /**
     * @param array<string,mixed>|null $breadcrumb
     * @param array<string,mixed>|null $image
     * @return array<string,mixed>
     */
    private function item_page(string $url, int $product_id, ?array $breadcrumb, ?array $image): array {
        $extra = [
            'inLanguage' => 'fa-IR',
            'mainEntity' => ['@id' => EntityIds::product($url)],
        ];

        $post = get_post($product_id);

        if ($post instanceof \WP_Post) {
            $published = get_post_datetime($post, 'date');
            $modified  = get_post_datetime($post, 'modified');

            if ($published instanceof \DateTimeImmutable) {
                $extra['datePublished'] = $published->format(DATE_ATOM);
            }

            if ($modified instanceof \DateTimeImmutable) {
                $extra['dateModified'] = $modified->format(DATE_ATOM);
            }
        }

        if (null !== $image) {
            $extra['primaryImageOfPage'] = ImageNode::ref($image);
        }

        if (null !== $breadcrumb) {
            $extra['breadcrumb'] = ['@id' => (string) $breadcrumb['@id']];
        }

        return WebPageNode::node($url, 'ItemPage', $extra);
    }

    /**
     * امتیاز فقط وقتی نظرِ واقعی وجود دارد — هیچ‌وقت ساختگی.
     *
     * @param array<string,mixed> $product
     */
    private function attach_rating(array &$product, int $product_id): void {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $wc_product = wc_get_product($product_id);

        if (!$wc_product || !method_exists($wc_product, 'get_rating_count')) {
            return;
        }

        $count = (int) $wc_product->get_rating_count();
        $average = (float) $wc_product->get_average_rating();

        if ($count < 1 || $average <= 0) {
            return;
        }

        $product['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => (string) $average,
            'reviewCount' => $count,
        ];
    }
}

add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new ShopProductProvider();

    return $providers;
});

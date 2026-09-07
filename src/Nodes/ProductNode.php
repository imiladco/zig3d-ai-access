<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Support\EntityIds;
use Zig3d_AI_Access\Support\Money;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * گرهِ ‎Product‎ — مشترکِ هر سه صفحهٔ فروشگاه (‎/shop‎، آرشیوِ دسته، تکِ
 * محصول)، چون یک محصول باید در هر سه جا *همان* موجودیت باشد: همان
 * ‎@id‎، همان برند، همان دسته.
 *
 * دو سطحِ جزئیات دارد: ‎summary()‎ برایِ فهرست‌هایِ آرشیو (نام/قیمت/برند
 * — چیزی که در یک کارت دیده می‌شود) و ‎full()‎ برایِ صفحهٔ خودِ محصول
 * (به‌علاوهٔ مشخصات، اسناد، ابعاد). این‌طور آرشیو بی‌دلیل سنگین نمی‌شود
 * و صفحهٔ محصول چیزی کم ندارد.
 */
final class ProductNode {

    public static function id(int $product_id): string {
        return EntityIds::product((string) get_permalink($product_id));
    }

    /**
     * نسخهٔ فهرستی — برایِ ‎ItemList‎ی آرشیوها.
     *
     * @return array<string,mixed>|null
     */
    public static function summary(int $product_id): ?array {
        $product = self::product($product_id);

        if (null === $product) {
            return null;
        }

        $url = (string) get_permalink($product_id);

        $node = [
            '@type' => 'Product',
            '@id'   => EntityIds::product($url),
            'name'  => (string) $product->get_name(),
            'url'   => $url,
        ];

        $description = self::description($product_id, $product);

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $image = self::primary_image_url($product_id);

        if (null !== $image) {
            $node['image'] = $image;
        }

        self::attach_brand($node, $product_id);
        self::attach_category($node, $product_id);
        self::attach_offer($node, $product, $url);

        return $node;
    }

    /**
     * نسخهٔ کاملِ صفحهٔ محصول.
     *
     * @return array<string,mixed>|null
     */
    public static function full(int $product_id): ?array {
        $node = self::summary($product_id);

        if (null === $node) {
            return null;
        }

        $product = self::product($product_id);

        if (null === $product) {
            return null;
        }

        $node['mainEntityOfPage'] = ['@id' => EntityIds::webpage((string) get_permalink($product_id))];

        // گالری: چند تصویر، نه فقط شاخص
        $images = self::gallery_urls($product_id, $product);

        if ($images) {
            $node['image'] = $images;
        }

        self::attach_dimensions($node, $product);

        $properties = self::additional_properties($product_id, $product);

        if ($properties) {
            $node['additionalProperty'] = $properties;
        } else {
            Diagnostics::drop('shop-product.additionalProperty', 'product has no WooCommerce attributes and no feature-showcase rows');
        }

        $documents = self::documents($product_id);

        if ($documents) {
            $node['subjectOf'] = $documents;
        }

        $related = self::related($product_id);

        if ($related) {
            $node['isRelatedTo'] = $related;
        } else {
            Diagnostics::drop('shop-product.isRelatedTo', 'wc_get_related_products() returned nothing — property omitted rather than left empty');
        }

        return $node;
    }

    /* ------------------------------------------------------------------ */

    /** @return \WC_Product|null */
    private static function product(int $product_id) {
        if (!function_exists('wc_get_product')) {
            Diagnostics::drop('shop.product', 'WooCommerce is not active — no Product node can be built');

            return null;
        }

        $product = wc_get_product($product_id);

        if (!$product || !method_exists($product, 'get_name')) {
            Diagnostics::drop('shop.product', sprintf('wc_get_product(%d) returned nothing', $product_id));

            return null;
        }

        return $product;
    }

    /**
     * توضیح: راهنمایِ صفحهٔ محصول می‌گوید متنِ کاملِ «معرفی محصول» ارزشِ
     * بیشتری دارد تا خلاصهٔ کوتاه. پس اول توضیحِ کامل، بعد خلاصه.
     *
     * @param \WC_Product $product
     */
    private static function description(int $product_id, $product): string {
        foreach ([$product->get_description(), $product->get_short_description()] as $candidate) {
            $text = trim(wp_strip_all_tags((string) $candidate, true));
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

            if ('' !== $text) {
                return $text;
            }
        }

        Diagnostics::drop('shop.product', sprintf('product #%d has neither description nor short description', $product_id));

        return '';
    }

    private static function primary_image_url(int $product_id): ?string {
        $id = (int) get_post_thumbnail_id($product_id);

        if ($id <= 0) {
            return null;
        }

        $src = wp_get_attachment_image_src($id, 'full');

        return is_array($src) && !empty($src[0]) ? (string) $src[0] : null;
    }

    /**
     * @param \WC_Product $product
     * @return string[]
     */
    private static function gallery_urls(int $product_id, $product): array {
        $ids = [];

        $thumbnail = (int) get_post_thumbnail_id($product_id);

        if ($thumbnail > 0) {
            $ids[] = $thumbnail;
        }

        if (method_exists($product, 'get_gallery_image_ids')) {
            foreach ((array) $product->get_gallery_image_ids() as $gallery_id) {
                $ids[] = (int) $gallery_id;
            }
        }

        $urls = [];

        foreach (array_unique(array_filter($ids)) as $id) {
            $src = wp_get_attachment_image_src((int) $id, 'full');

            if (is_array($src) && !empty($src[0])) {
                $urls[] = (string) $src[0];
            }
        }

        return $urls;
    }

    /**
     * برند — سه تاکسونومیِ ممکن (ووکامرسِ ۹٫۴+ و دو افزونهٔ قدیمی‌تر)، از
     * config. ‎brand‎ و ‎manufacturer‎ عمداً همان ‎@id‎ را می‌گیرند: رویِ
     * این سایت سازنده و برند یک موجودیت‌اند.
     *
     * @param array<string,mixed> $node
     */
    private static function attach_brand(array &$node, int $product_id): void {
        foreach ((array) Config::get('brand.taxonomies', []) as $taxonomy) {
            $taxonomy = (string) $taxonomy;

            if ('' === $taxonomy || !taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = get_the_terms($product_id, $taxonomy);

            if (is_wp_error($terms) || !$terms) {
                continue;
            }

            $term = $terms[0];
            $id   = EntityIds::brand((string) $term->slug);

            $node['brand'] = ['@type' => 'Brand', '@id' => $id, 'name' => (string) $term->name];
            $node['manufacturer'] = ['@type' => 'Organization', '@id' => $id, 'name' => (string) $term->name];

            return;
        }

        Diagnostics::drop('shop.product', sprintf('product #%d has no term in any configured brand taxonomy', $product_id));
    }

    /**
     * دسته — همان ‎@id‎ی اسلاگ‌محوری که صفحهٔ اصلی و آرشیوها هم می‌سازند.
     * اگر محصول چند دسته داشت، دستهٔ اصلی (تنظیمِ رنک‌مث/یوست) و در
     * نبودش اولی.
     *
     * @param array<string,mixed> $node
     */
    private static function attach_category(array &$node, int $product_id): void {
        $terms = get_the_terms($product_id, 'product_cat');

        if (is_wp_error($terms) || !$terms) {
            Diagnostics::drop('shop.product', sprintf('product #%d has no product_cat term', $product_id));

            return;
        }

        $term = self::primary_term($product_id, $terms);
        $link = get_term_link($term);

        $category = [
            '@type' => 'Thing',
            '@id'   => EntityIds::category((string) $term->slug),
            'name'  => (string) $term->name,
        ];

        if (!is_wp_error($link)) {
            $category['url'] = (string) $link;
        }

        $node['category'] = $category;
    }

    /**
     * @param \WP_Term[] $terms
     * @return \WP_Term
     */
    private static function primary_term(int $product_id, array $terms) {
        foreach ((array) Config::get('brand.primary_category_meta', []) as $meta_key) {
            $primary_id = (int) get_post_meta($product_id, (string) $meta_key, true);

            if ($primary_id <= 0) {
                continue;
            }

            foreach ($terms as $term) {
                if ((int) $term->term_id === $primary_id) {
                    return $term;
                }
            }
        }

        return $terms[0];
    }

    /**
     * @param array<string,mixed> $node
     * @param \WC_Product $product
     */
    private static function attach_offer(array &$node, $product, string $url): void {
        $price = Money::to_output($product->get_price());

        if (null === $price) {
            Diagnostics::drop('shop.product', sprintf('product "%s" has no usable price — Offer omitted', $product->get_name()));

            return;
        }

        $node['offers'] = [
            '@type'         => 'Offer',
            'price'         => $price,
            'priceCurrency' => Money::currency(),
            'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'url'           => $url,
            'seller'        => OrganizationNode::ref(),
        ];
    }

    /**
     * ابعاد/وزن — فقط آن‌هایی که واقعاً مقدار دارند. ووکامرس واحدها را
     * در تنظیماتِ فروشگاه نگه می‌دارد؛ به ‎unitCode‎ی UN/CEFACT ترجمه
     * می‌شوند تا عدد بی‌واحد نماند.
     *
     * @param array<string,mixed> $node
     * @param \WC_Product $product
     */
    private static function attach_dimensions(array &$node, $product): void {
        $weight_unit = function_exists('get_option') ? (string) get_option('woocommerce_weight_unit', '') : '';
        $dimension_unit = function_exists('get_option') ? (string) get_option('woocommerce_dimension_unit', '') : '';

        $weight = method_exists($product, 'get_weight') ? trim((string) $product->get_weight()) : '';

        if ('' !== $weight && is_numeric($weight)) {
            $node['weight'] = self::quantity((float) $weight, self::unit_code($weight_unit));
        }

        $map = [
            'height' => method_exists($product, 'get_height') ? $product->get_height() : '',
            'width'  => method_exists($product, 'get_width') ? $product->get_width() : '',
            'depth'  => method_exists($product, 'get_length') ? $product->get_length() : '',
        ];

        foreach ($map as $key => $raw) {
            $value = trim((string) $raw);

            if ('' === $value || !is_numeric($value)) {
                continue;
            }

            $node[$key] = self::quantity((float) $value, self::unit_code($dimension_unit));
        }
    }

    /** @return array<string,mixed> */
    private static function quantity(float $value, string $unit_code): array {
        $quantity = ['@type' => 'QuantitativeValue', 'value' => $value];

        if ('' !== $unit_code) {
            $quantity['unitCode'] = $unit_code;
        }

        return $quantity;
    }

    /** واحدهایِ ووکامرس → کدهایِ UN/CEFACT که schema.org انتظار دارد */
    private static function unit_code(string $unit): string {
        $map = [
            'kg' => 'KGM', 'g' => 'GRM', 'lbs' => 'LBR', 'oz' => 'ONZ',
            'm' => 'MTR', 'cm' => 'CMT', 'mm' => 'MMT', 'in' => 'INH', 'yd' => 'YRD',
        ];

        return $map[strtolower($unit)] ?? '';
    }

    /**
     * مشخصات: ویژگی‌هایِ ووکامرس + ریپیترِ «قابلیت‌هایِ کلیدی».
     *
     * @param \WC_Product $product
     * @return array<int,array<string,string>>
     */
    private static function additional_properties(int $product_id, $product): array {
        $out = [];

        if (method_exists($product, 'get_attributes')) {
            foreach ((array) $product->get_attributes() as $attribute) {
                if (!is_object($attribute) || !method_exists($attribute, 'get_name')) {
                    continue;
                }

                $name = (string) $attribute->get_name();

                // نامِ خوانا، نه اسلاگِ خام مثلِ ‎pa_a-axis‎
                $label = function_exists('wc_attribute_label') ? (string) wc_attribute_label($name, $product) : $name;
                $value = trim((string) $product->get_attribute($name));

                if ('' === $label || '' === $value) {
                    continue;
                }

                $out[] = ['@type' => 'PropertyValue', 'name' => $label, 'value' => $value];
            }
        }

        foreach (self::feature_rows($product_id) as $row) {
            $out[] = $row;
        }

        return $out;
    }

    /**
     * ریپیترِ «قابلیت‌هایِ کلیدی» (ویجتِ ‎zig3d-feature-showcase‎) —
     * کلیدش از config می‌آید.
     *
     * @return array<int,array<string,string>>
     */
    private static function feature_rows(int $product_id): array {
        $meta_key = (string) Config::get('product.feature_showcase_meta', '');

        if ('' === $meta_key) {
            return [];
        }

        $rows = get_post_meta($product_id, $meta_key, true);

        if (!is_array($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            // ریپیترها کلیدهایِ متفاوتی دارند؛ اولین جفتِ «عنوان/متن»ی که
            // مقدار داشته باشد برداشته می‌شود.
            $name = '';
            $value = '';

            foreach ($row as $key => $cell) {
                if (!is_scalar($cell)) {
                    continue;
                }

                $cell = trim(wp_strip_all_tags((string) $cell, true));

                if ('' === $cell) {
                    continue;
                }

                if ('' === $name) {
                    $name = $cell;

                    continue;
                }

                if ('' === $value) {
                    $value = $cell;
                }
            }

            if ('' === $name || '' === $value) {
                continue;
            }

            $out[] = ['@type' => 'PropertyValue', 'name' => $name, 'value' => $value];
        }

        return $out;
    }

    /**
     * اسنادِ قابلِ‌دانلود — نوعِ درست به‌ازایِ پسوندِ فایل.
     *
     * @return array<int,array<string,string>>
     */
    private static function documents(int $product_id): array {
        $meta_key = (string) Config::get('product.documents_meta', '');

        if ('' === $meta_key) {
            return [];
        }

        $rows = get_post_meta($product_id, $meta_key, true);

        if (!is_array($rows)) {
            return [];
        }

        $fields = (array) Config::get('product.document_fields', []);
        $file_key  = (string) ($fields['file'] ?? 'document_file');
        $title_key = (string) ($fields['title'] ?? 'document_title');

        $out = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $url = self::attachment_url($row[$file_key] ?? '');
            $name = trim((string) ($row[$title_key] ?? ''));

            if ('' === $url) {
                continue;
            }

            $extension = strtolower((string) pathinfo(wp_parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

            $document = self::document_shape($extension);
            $document['url'] = $url;

            if ('' !== $name) {
                $document['name'] = $name;
            }

            $out[] = $document;
        }

        return $out;
    }

    /** @return array<string,string> */
    private static function document_shape(string $extension): array {
        $videos = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'];

        if (isset($videos[$extension])) {
            return ['@type' => 'VideoObject', 'encodingFormat' => $videos[$extension]];
        }

        $formats = [
            'pdf'  => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'  => 'application/vnd.ms-excel',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc'  => 'application/msword',
            'zip'  => 'application/zip',
            'rar'  => 'application/vnd.rar',
            'dwg'  => 'image/vnd.dwg',
            'step' => 'model/step',
            'stp'  => 'model/step',
        ];

        $document = ['@type' => 'DigitalDocument'];

        if (isset($formats[$extension])) {
            $document['encodingFormat'] = $formats[$extension];
        }

        return $document;
    }

    /** @param mixed $raw شناسهٔ پیوست یا خودِ آدرس */
    private static function attachment_url($raw): string {
        if (is_array($raw)) {
            $raw = $raw['url'] ?? ($raw['id'] ?? '');
        }

        if (is_numeric($raw)) {
            $url = wp_get_attachment_url((int) $raw);

            return is_string($url) ? $url : '';
        }

        $raw = trim((string) $raw);

        return 0 === strpos($raw, 'http') ? $raw : '';
    }

    /**
     * محصولاتِ مرتبط — از کوئریِ خودِ ووکامرس، نه هیچ متنِ جایگزینی.
     *
     * @return array<int,array<string,string>>
     */
    private static function related(int $product_id): array {
        if (!function_exists('wc_get_related_products')) {
            return [];
        }

        $ids = wc_get_related_products($product_id, 6);

        $out = [];

        foreach ((array) $ids as $id) {
            $url = get_permalink((int) $id);

            if (!is_string($url) || '' === $url) {
                continue;
            }

            $out[] = ['@id' => EntityIds::product($url)];
        }

        return $out;
    }
}

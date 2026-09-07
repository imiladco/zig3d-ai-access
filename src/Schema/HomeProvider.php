<?php
namespace Zig3d_AI_Access\Schema;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Schema_Provider;
use Zig3d_AI_Access\Nodes\WebPageNode;
use Zig3d_AI_Access\Support\EntityIds;
use Zig3d_AI_Access\Support\LoopGrid;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ اصلی.
 *
 * باگِ نسخهٔ قبلی (کامنتِ «no Schema_Provider matched» رویِ خودِ
 * زیگ۳دی.کام): ‎applies()‎ به‌جایِ ‎is_front_page()‎ چیزِ دیگری چک
 * می‌کرد (یا اصلاً این کلاس هنوز ثبت نشده بود). ‎is_front_page()‎
 * تنها شرطِ رسمیِ وردپرس است که هم حالتِ «صفحهٔ ثابت به‌عنوانِ
 * اصلی» و هم حالتِ «آخرین‌نوشته‌ها به‌عنوانِ اصلی» را درست تشخیص
 * می‌دهد — چیزی که یک آی‌دیِ هاردکدشده یا ‎is_page(X)‎ هرگز درست
 * تشخیص نمی‌دهد.
 */
final class HomeProvider implements Schema_Provider {

    public function applies(): bool {
        return is_front_page();
    }

    public function rank_math_scope(): string {
        return 'home';
    }

    /** @return array<int,array<string,mixed>> */
    public function nodes(): array {
        $nodes = [$this->webpage_node()];

        $categories = $this->featured_categories_node();

        if (null !== $categories) {
            $nodes[] = $categories;
        }

        $products = $this->featured_products_node();

        if (null !== $products) {
            $nodes[] = $products;
        }

        return $nodes;
    }

    /**
     * @return array<string,mixed>
     *
     * تاریخ‌ها عمداً از ‎get_post_datetime()‎ می‌آیند، نه ‎get_the_date()‎/
     * ‎get_the_modified_date()‎: آن دو تابع از فیلترهایِ نمایشیِ سایت
     * (این‌جا یک افزونهٔ تقویمِ جلالی) عبور می‌کنند و رشتهٔ نمایشی
     * برمی‌گردانند («۱۴۰۳-۰۶-۲۹\۱۴:۱۴:۱۷») — نامعتبر برایِ JSON-LD.
     * ‎get_post_datetime()‎ مستقیم از ‎post_date‎/‎post_modified‎ِ خامِ
     * دیتابیس می‌خواند، بدونِ عبور از آن فیلترها.
     */
    private function webpage_node(): array {
        $extra = [];

        if ('page' === get_option('show_on_front')) {
            $front_id = (int) get_option('page_on_front');
            $post     = $front_id > 0 ? get_post($front_id) : null;

            if ($post instanceof \WP_Post) {
                $published = get_post_datetime($post, 'date');
                $modified  = get_post_datetime($post, 'modified');

                if ($published instanceof \DateTimeImmutable) {
                    $extra['datePublished'] = $published->format(DATE_ATOM);
                }

                if ($modified instanceof \DateTimeImmutable) {
                    $extra['dateModified'] = $modified->format(DATE_ATOM);
                }

                if (!($published instanceof \DateTimeImmutable) || !($modified instanceof \DateTimeImmutable)) {
                    Diagnostics::drop('home.webpage-dates', sprintf('get_post_datetime() returned false for post #%d (date and/or modified)', $front_id));
                }
            } else {
                Diagnostics::drop('home.webpage-dates', sprintf('get_post(page_on_front=%d) did not return a WP_Post', $front_id));
            }
        } else {
            Diagnostics::drop(
                'home.webpage-dates',
                'front page is the posts index (show_on_front=posts), not a single static page — no one datePublished/dateModified to report'
            );
        }

        return WebPageNode::node(home_url('/'), 'WebPage', $extra);
    }

    /**
     * «دسته‌هایِ شاخص» — طبقِ تأییدِ کاربر یعنی *همهٔ* ترم‌هایِ
     * ‎product_cat‎، نه یک کوئریِ جت‌اینجینِ جداگانه.
     *
     * @return array<string,mixed>|null
     */
    private function featured_categories_node(): ?array {
        if (!taxonomy_exists('product_cat')) {
            Diagnostics::drop('home.featured-categories', 'taxonomy "product_cat" does not exist (WooCommerce inactive?)');

            return null;
        }

        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true]);

        if (is_wp_error($terms)) {
            Diagnostics::drop('home.featured-categories', 'get_terms(product_cat) returned WP_Error: ' . $terms->get_error_message());

            return null;
        }

        if (empty($terms)) {
            Diagnostics::drop('home.featured-categories', 'get_terms(product_cat) returned zero non-empty terms');

            return null;
        }

        $items    = [];
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
                'url'      => $link,
                'name'     => $term->name,
            ];
        }

        if ([] === $items) {
            Diagnostics::drop('home.featured-categories', 'all product_cat terms failed get_term_link()');

            return null;
        }

        return [
            '@type'           => 'ItemList',
            '@id'             => EntityIds::fragment('featured-categories'),
            'name'            => 'دسته‌های محصولات زیگ',
            'itemListElement' => $items,
        ];
    }

    /**
     * «محصولاتِ منتخب» — بدونِ هیچ آی‌دیِ ثابتِ عنصر/قالب (یک نمونهٔ
     * قبلی، ‎ac64ce3‎/‎15031‎، رویِ سایتِ واقعی اشتباه از آب درآمد:
     * عنصر دیگر آن‌جا نبود). به‌جایش با ‎widgetType‎ پیدا می‌شود —
     * اول رویِ خودِ صفحهٔ اصلیِ *واقعی* (نه یک آی‌دیِ حدسی، بلکه همان
     * چیزی که ‎get_option('page_on_front')‎ می‌گوید)، بعد — اگر آن‌جا
     * نبود — بینِ قالب‌هایِ ‎elementor_library‎ (برایِ حالتی که ویجت از
     * طریقِ Theme Builder جداگانه تزریق شده). اگر هیچ‌جا پیدا نشد، گره
     * حذف می‌شود؛ حدسِ جایگزین زده نمی‌شود.
     *
     * @return array<string,mixed>|null
     */
    private function featured_products_node(): ?array {
        $widget_types = (array) Config::get('loop_grid.widget_types', []);

        if ([] === $widget_types) {
            Diagnostics::drop('home.featured-products', 'config loop_grid.widget_types is empty — nothing to search for');

            return null;
        }

        $settings = $this->find_featured_products_settings($widget_types);

        if (null === $settings) {
            return null;
        }

        $ids = LoopGrid::manual_ids($settings);

        if ([] === $ids) {
            // دلیلِ دقیق را خودِ LoopGrid::manual_ids() قبلاً با Diagnostics::drop() ثبت کرده
            return null;
        }

        $items    = [];
        $position = 0;

        foreach ($ids as $product_id) {
            $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;

            $url  = $product ? $product->get_permalink() : get_permalink($product_id);
            $name = $product ? $product->get_name() : get_the_title($product_id);

            if (!is_string($url) || '' === $url || !is_string($name) || '' === $name) {
                continue;
            }

            $position++;

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'url'      => $url,
                'name'     => $name,
            ];
        }

        if ([] === $items) {
            Diagnostics::drop('home.featured-products', 'manual_ids resolved but none of the referenced posts/products exist any more');

            return null;
        }

        return [
            '@type'           => 'ItemList',
            '@id'             => EntityIds::fragment('featured-products'),
            'name'            => 'محصولات منتخب زیگ',
            'itemListElement' => $items,
        ];
    }

    /**
     * محلِ *واقعیِ* ویجتِ محصولاتِ منتخب را می‌گردد — بدونِ فرض کردنِ
     * هیچ آی‌دیِ ثابتی. اول خودِ پستِ صفحهٔ اصلی، بعد (اگر آن‌جا نبود)
     * تا ۲۰ قالبِ منتشرشدهٔ ‎elementor_library‎ (Theme Builder). این
     * محدودیتِ ۲۰ عمداً کوچک است — این جست‌وجو در ‎wp_head‎ی *هر*
     * بازدیدِ صفحهٔ اصلی اجرا می‌شود و کش هنوز پیاده نشده (طبقِ اصل ۷ی
     * سندِ معماری)، پس هزینه‌اش باید محدود بماند.
     *
     * @param string[] $widget_types
     * @return array<string,mixed>|null
     */
    private function find_featured_products_settings(array $widget_types): ?array {
        $searched = [];

        if ('page' === get_option('show_on_front')) {
            $front_id = (int) get_option('page_on_front');

            if ($front_id > 0) {
                $searched[] = $front_id;

                $settings = LoopGrid::settings_by_widget_type($front_id, $widget_types);

                if (null !== $settings) {
                    return $settings;
                }
            }
        }

        $templates = get_posts([
            'post_type'      => 'elementor_library',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        foreach ((array) $templates as $template_id) {
            $template_id = (int) $template_id;
            $searched[]  = $template_id;

            $settings = LoopGrid::settings_by_widget_type($template_id, $widget_types);

            if (null !== $settings) {
                return $settings;
            }
        }

        Diagnostics::drop(
            'home.featured-products',
            sprintf(
                'no widget matching configured widget_types (%s) found on the front page or in any of %d elementor_library templates (searched post IDs: %s)',
                implode(', ', $widget_types),
                count($templates),
                implode(', ', $searched)
            )
        );

        return null;
    }
}

add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new HomeProvider();

    return $providers;
});

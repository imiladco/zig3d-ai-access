<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Support\EntityIds;
use Zig3d_AI_Access\Support\ReadingTime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نوشتهٔ بلاگ — ‎BlogPosting‎ در فهرست‌ها، ‎Article‎ رویِ صفحهٔ خودش.
 *
 * **قاعدهٔ الزامیِ تاریخ‌ها**: همیشه از ‎get_post_datetime()‎، هرگز از
 * ‎get_the_date()‎. رویِ همین سایت، schemaِ خودکارِ فعلی تاریخ‌هایی مثل
 * ‎"1404-02-13\21:07:36"‎ چاپ می‌کرد — نه ISO 8601 معتبر (جداکننده باید
 * ‎T‎ باشد نه ‎\‎) و نه میلادی. جالب اینکه گرهِ ‎WebPage‎ی *همان صفحه*
 * تاریخِ درست داشت؛ یعنی حتی درست‌بودنِ یک بخش تضمینی برایِ بخشِ دیگر
 * نیست. ‎get_post_datetime()‎ مستقیم ‎post_date‎ی خام را می‌خواند و از
 * فیلترهایِ نمایشیِ تقویمِ جلالی عبور نمی‌کند.
 */
final class BlogPostingNode {

    public static function id(int $post_id): string {
        return EntityIds::article((string) get_permalink($post_id));
    }

    /**
     * نسخهٔ فهرستی — برایِ ‎ItemList‎ی آرشیوهایِ بلاگ.
     *
     * @return array<string,mixed>|null
     */
    public static function summary(int $post_id): ?array {
        return self::build($post_id, 'BlogPosting');
    }

    /**
     * نسخهٔ کاملِ صفحهٔ خودِ مقاله.
     *
     * @return array<string,mixed>|null
     */
    public static function full(int $post_id): ?array {
        $node = self::build($post_id, 'Article');

        if (null === $node) {
            return null;
        }

        $url = (string) get_permalink($post_id);

        $node['isPartOf'] = ['@id' => EntityIds::webpage($url)];
        $node['mainEntityOfPage'] = ['@id' => EntityIds::webpage($url)];

        $keywords = self::keywords($post_id);

        if ($keywords) {
            $node['keywords'] = $keywords;
        }

        $section = self::section($post_id);

        if (null !== $section) {
            $node['articleSection'] = $section;
        }

        $mentions = self::mentions($post_id);

        if ($mentions) {
            $node['mentions'] = $mentions;
        }

        return $node;
    }

    /* ------------------------------------------------------------------ */

    /** @return array<string,mixed>|null */
    private static function build(int $post_id, string $type): ?array {
        $url = (string) get_permalink($post_id);

        if ('' === $url) {
            Diagnostics::drop('blog.post', sprintf('get_permalink(%d) returned nothing', $post_id));

            return null;
        }

        $node = [
            '@type'    => $type,
            '@id'      => EntityIds::article($url),
            'headline' => (string) get_the_title($post_id),
            'url'      => $url,
        ];

        $description = self::description($post_id);

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $author = AuthorNode::node($post_id);

        if (null !== $author) {
            $node['author'] = $author;
        }

        $node['publisher'] = OrganizationNode::ref();

        $image = ImageNode::from_attachment((int) get_post_thumbnail_id($post_id));

        if (null !== $image) {
            $node['image'] = $image;
        } else {
            Diagnostics::drop('blog.post', sprintf('post #%d has no featured image', $post_id));
        }

        self::attach_dates($node, $post_id);

        $duration = ReadingTime::duration((string) get_post_field('post_content', $post_id));

        if (null !== $duration) {
            $node['timeRequired'] = $duration;
        } else {
            Diagnostics::drop('blog.post', sprintf('post #%d has no body text — timeRequired omitted', $post_id));
        }

        $node['inLanguage'] = 'fa-IR';

        return $node;
    }

    /** @param array<string,mixed> $node */
    private static function attach_dates(array &$node, int $post_id): void {
        $post = get_post($post_id);

        if (!$post instanceof \WP_Post) {
            Diagnostics::drop('blog.post', sprintf('get_post(%d) did not return a WP_Post — dates omitted', $post_id));

            return;
        }

        $published = get_post_datetime($post, 'date');
        $modified  = get_post_datetime($post, 'modified');

        if ($published instanceof \DateTimeImmutable) {
            $node['datePublished'] = $published->format(DATE_ATOM);
        }

        if ($modified instanceof \DateTimeImmutable) {
            $node['dateModified'] = $modified->format(DATE_ATOM);
        }
    }

    private static function description(int $post_id): string {
        $candidates = [
            (string) get_post_field('post_excerpt', $post_id),
            (string) get_post_meta($post_id, 'rank_math_description', true),
        ];

        foreach ($candidates as $candidate) {
            $text = trim(wp_strip_all_tags($candidate, true));
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

            if ('' !== $text) {
                return $text;
            }
        }

        return '';
    }

    /**
     * کیوردها — **آرایه**، نه یک رشتهٔ کاما-جدا. همیشه از متایِ همان
     * پست؛ هر پست کیوردهایِ خودش را دارد.
     *
     * @return string[]
     */
    private static function keywords(int $post_id): array {
        foreach ((array) Config::get('blog.focus_keyword_meta', []) as $meta_key) {
            $raw = trim((string) get_post_meta($post_id, (string) $meta_key, true));

            if ('' === $raw) {
                continue;
            }

            $parts = array_map('trim', explode(',', $raw));
            $parts = array_values(array_filter($parts, static fn(string $p): bool => '' !== $p));

            if ($parts) {
                return $parts;
            }
        }

        Diagnostics::drop('blog-single.keywords', sprintf('post #%d has no focus-keyword meta', $post_id));

        return [];
    }

    /** دستهٔ اصلیِ مقاله — تنظیمِ سئو، وگرنه اولین دسته */
    private static function section(int $post_id): ?string {
        $terms = get_the_terms($post_id, 'category');

        if (is_wp_error($terms) || !$terms) {
            return null;
        }

        foreach ((array) Config::get('blog.primary_category_meta', []) as $meta_key) {
            $primary = (int) get_post_meta($post_id, (string) $meta_key, true);

            if ($primary <= 0) {
                continue;
            }

            foreach ($terms as $term) {
                if ((int) $term->term_id === $primary) {
                    return (string) $term->name;
                }
            }
        }

        return (string) $terms[0]->name;
    }

    /**
     * ارجاع‌ها — فقط به موجودیت‌هایی که **واقعاً وجود دارند**.
     *
     * راهنما صریح است: این ارجاع باید *دستی* باشد (ادمین هنگامِ نوشتنِ
     * مقاله انتخاب کند)، نه استخراجِ خودکار از متن. کاوشِ دادهٔ زنده نشان
     * داد چنین فیلدی رویِ این سایت هنوز ساخته نشده، پس تا آن‌موقع این
     * property اصلاً چاپ نمی‌شود — نه حدس، نه آرایهٔ خالی.
     *
     * @return array<int,array<string,string>>
     */
    private static function mentions(int $post_id): array {
        $out = [];

        $category_key = (string) Config::get('blog.mentions_category_meta', '');
        $products_key = (string) Config::get('blog.mentions_products_meta', '');

        if ('' === $category_key && '' === $products_key) {
            Diagnostics::drop(
                'blog-single.mentions',
                'no admin field is configured for "which product/category this article is about" — mentions omitted rather than guessed from the text'
            );

            return [];
        }

        if ('' !== $category_key) {
            $slug = trim((string) get_post_meta($post_id, $category_key, true));

            if ('' !== $slug) {
                $term = get_term_by('slug', $slug, 'product_cat');

                if ($term instanceof \WP_Term) {
                    $out[] = ['@id' => EntityIds::category((string) $term->slug)];
                } else {
                    Diagnostics::drop('blog-single.mentions', sprintf('product_cat "%s" does not exist — reference dropped', $slug));
                }
            }
        }

        if ('' !== $products_key) {
            $ids = get_post_meta($post_id, $products_key, true);

            if (is_string($ids) && '' !== $ids) {
                $ids = array_map('trim', explode(',', $ids));
            }

            foreach ((array) $ids as $id) {
                $id = (int) $id;

                if ($id <= 0 || 'publish' !== get_post_status($id)) {
                    continue;
                }

                $product_url = (string) get_permalink($id);

                if ('' !== $product_url) {
                    $out[] = ['@id' => EntityIds::product($product_url)];
                }
            }
        }

        return $out;
    }
}

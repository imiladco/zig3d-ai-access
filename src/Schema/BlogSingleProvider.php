<?php
namespace Zig3d_AI_Access\Schema;

use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Schema_Provider;
use Zig3d_AI_Access\Nodes\AuthorNode;
use Zig3d_AI_Access\Nodes\BlogPostingNode;
use Zig3d_AI_Access\Nodes\BreadcrumbNode;
use Zig3d_AI_Access\Nodes\FaqPageNode;
use Zig3d_AI_Access\Nodes\ImageNode;
use Zig3d_AI_Access\Nodes\WebPageNode;
use Zig3d_AI_Access\Support\Blog;
use Zig3d_AI_Access\Support\EntityIds;
use Zig3d_AI_Access\Support\Faq;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ تکِ مقاله.
 *
 * ‎FAQPage‎ اختیاری است: همهٔ مقاله‌ها FAQ ندارند. اگر پست سؤالی نداشت،
 * گره اصلاً ساخته نمی‌شود — نه آرایهٔ خالی، نه placeholder. تشخیص و
 * پاک‌سازی هر دو از همان مسیرِ مشترکِ ‎Faq::sanitize()‎ می‌آید که صفحاتِ
 * فروشگاه و دانلود هم از آن استفاده می‌کنند.
 */
final class BlogSingleProvider implements Schema_Provider {

    public function applies(): bool {
        return is_singular('post');
    }

    public function rank_math_scope(): string {
        return 'blog-single';
    }

    /** @return array<int,array<string,mixed>> */
    public function nodes(): array {
        $post_id = (int) get_queried_object_id();

        if ($post_id <= 0) {
            Diagnostics::drop('blog-single', 'get_queried_object_id() returned 0');

            return [];
        }

        $url = (string) get_permalink($post_id);

        if ('' === $url) {
            Diagnostics::drop('blog-single', sprintf('get_permalink(%d) returned nothing', $post_id));

            return [];
        }

        $nodes = [];

        $breadcrumb = BreadcrumbNode::node($url, $this->trail($post_id, $url));

        if (null !== $breadcrumb) {
            $nodes[] = $breadcrumb;
        }

        // تصویر به‌عنوانِ گرهِ مستقل، تا ارجاعِ صفحه به یک ‎@id‎ی یتیم نباشد
        $image = ImageNode::from_attachment((int) get_post_thumbnail_id($post_id));

        if (null !== $image) {
            $nodes[] = $image;
        }

        $author = AuthorNode::node($post_id);

        if (null !== $author && isset($author['@id'])) {
            $nodes[] = $author;
        }

        $article = BlogPostingNode::full($post_id);

        $nodes[] = $this->webpage($url, $post_id, $breadcrumb, $image, $article);

        if (null !== $article) {
            // وقتی گرهِ کاملِ نویسنده در گراف هست، مقاله فقط ارجاع می‌دهد
            if (null !== $author && isset($author['@id'])) {
                $article['author'] = AuthorNode::ref($author);
            }

            $nodes[] = $article;
        }

        $faq = FaqPageNode::node($url, Faq::for_post($post_id), 'blog-single.faq');

        if (null !== $faq) {
            $nodes[] = $faq;
        }

        return $nodes;
    }

    /* ------------------------------------------------------------------ */

    /** @return array<int,array{name:string,url:string}> */
    private function trail(int $post_id, string $url): array {
        $trail = [
            ['name' => 'خانه', 'url' => home_url('/')],
            ['name' => Blog::home_title(), 'url' => Blog::home_url()],
        ];

        $terms = get_the_terms($post_id, 'category');

        if (!is_wp_error($terms) && $terms) {
            $link = get_term_link($terms[0]);

            if (!is_wp_error($link)) {
                $trail[] = ['name' => (string) $terms[0]->name, 'url' => (string) $link];
            }
        }

        $trail[] = ['name' => (string) get_the_title($post_id), 'url' => $url];

        return $trail;
    }

    /**
     * @param array<string,mixed>|null $breadcrumb
     * @param array<string,mixed>|null $image
     * @param array<string,mixed>|null $article
     * @return array<string,mixed>
     */
    private function webpage(string $url, int $post_id, ?array $breadcrumb, ?array $image, ?array $article): array {
        $extra = ['inLanguage' => 'fa-IR'];

        if (null !== $article) {
            $extra['mainEntity'] = ['@id' => (string) $article['@id']];
        }

        $post = get_post($post_id);

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

        return WebPageNode::node($url, 'WebPage', $extra);
    }
}

add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new BlogSingleProvider();

    return $providers;
});

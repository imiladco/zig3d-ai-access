<?php
namespace Zig3d_AI_Access\Support;

use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Nodes\BlogPostingNode;
use Zig3d_AI_Access\Nodes\ItemListNode;

if (!defined('ABSPATH')) {
    exit;
}

/** تکهٔ مشترکِ دو آرشیوِ بلاگ — تا یک منطق دو بار نوشته نشود. */
final class Blog {

    /**
     * ‎ItemList‎ی نوشته‌هایِ همین صفحهٔ آرشیو.
     *
     * @return array<string,mixed>|null
     */
    public static function post_list(string $url, int $paged, string $section): ?array {
        $ids = Archive::current_ids();

        if (!$ids) {
            Diagnostics::drop($section . '.posts', 'the main WP_Query returned no posts for this archive page');

            return null;
        }

        $items = [];

        foreach ($ids as $id) {
            $post = BlogPostingNode::summary((int) $id);

            if (null !== $post) {
                $items[] = $post;
            }
        }

        if (!$items) {
            Diagnostics::drop($section . '.posts', 'no post on this page could be turned into a valid BlogPosting node');

            return null;
        }

        return ItemListNode::node(
            $url,
            Archive::list_suffix('articles', $paged),
            $items,
            Archive::first_position($paged, Archive::per_page()),
            EntityIds::webpage($url)
        );
    }

    /** آدرسِ صفحهٔ اصلیِ بلاگ — از تنظیماتِ واقعیِ وردپرس، نه الگویِ حدسی */
    public static function home_url(): string {
        $page_id = (int) get_option('page_for_posts');

        if ($page_id > 0) {
            $link = get_permalink($page_id);

            if (is_string($link) && '' !== $link) {
                return $link;
            }
        }

        $link = get_post_type_archive_link('post');

        return is_string($link) ? $link : home_url('/');
    }

    public static function home_title(): string {
        $page_id = (int) get_option('page_for_posts');
        $title = $page_id > 0 ? trim((string) get_the_title($page_id)) : '';

        return '' !== $title ? $title : 'بلاگ';
    }
}

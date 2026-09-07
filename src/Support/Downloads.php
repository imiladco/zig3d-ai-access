<?php
namespace Zig3d_AI_Access\Support;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Nodes\ItemListNode;
use Zig3d_AI_Access\Nodes\SoftwareNode;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تکه‌هایِ مشترکِ سه صفحهٔ دانلود — تا آرشیوِ اصلی و آرشیوِ دسته دو
 * پیاده‌سازیِ موازی از یک چیز نشوند.
 */
final class Downloads {

    /**
     * نوعِ پستِ نرم‌افزارها.
     *
     * شناسهٔ داخلیِ JetEngine (نه اسلاگِ حدسی) منبعِ حقیقت است، چون
     * اسلاگ بینِ نصب‌ها فرق می‌کند. اگر JetEngine در دسترس نبود، اسلاگِ
     * ثبت‌شده در config به‌عنوانِ پشتیبان امتحان می‌شود.
     */
    public static function post_type(): string {
        static $resolved = null;

        if (null !== $resolved) {
            return $resolved;
        }

        $resolved = '';

        $cpt_id = (int) Config::get('downloads.jetengine_cpt_id', 0);

        if ($cpt_id > 0 && function_exists('jet_engine')) {
            $engine = jet_engine();

            if (is_object($engine) && isset($engine->cpt) && is_object($engine->cpt) && method_exists($engine->cpt, 'get_items')) {
                foreach ((array) $engine->cpt->get_items() as $item) {
                    if (!is_array($item) || $cpt_id !== (int) ($item['id'] ?? 0)) {
                        continue;
                    }

                    $slug = sanitize_key((string) ($item['slug'] ?? ''));

                    if ('' !== $slug && post_type_exists($slug)) {
                        $resolved = $slug;

                        return $resolved;
                    }
                }
            }
        }

        $fallback = (string) Config::get('downloads.post_type', 'downloads');

        if ('' !== $fallback && post_type_exists($fallback)) {
            $resolved = $fallback;

            return $resolved;
        }

        Diagnostics::drop('downloads', sprintf('could not resolve the downloads post type (JetEngine CPT id %d, fallback "%s")', $cpt_id, $fallback));

        return $resolved;
    }

    public static function archive_url(): string {
        $post_type = self::post_type();

        if ('' === $post_type) {
            return '';
        }

        $link = get_post_type_archive_link($post_type);

        return is_string($link) ? $link : '';
    }

    /**
     * ‎ItemList‎ی نرم‌افزارهایِ همین صفحهٔ آرشیو.
     *
     * ‎@id‎ عمداً ‎#software-list‎ است، نه ‎#software‎: پسوندِ ‎#software‎
     * به‌ازایِ *هر آیتمِ تکی* استفاده می‌شود و اگر فهرست هم همان را
     * می‌گرفت، دو موجودیتِ متفاوت یک شناسه پیدا می‌کردند.
     *
     * @return array<string,mixed>|null
     */
    public static function software_list(string $url, int $paged, string $section): ?array {
        $ids = Archive::current_ids();

        if (!$ids) {
            Diagnostics::drop($section . '.software', 'the main WP_Query returned no posts for this archive page');

            return null;
        }

        $items = [];

        foreach ($ids as $id) {
            $software = SoftwareNode::summary((int) $id);

            if (null !== $software) {
                $items[] = $software;
            }
        }

        if (!$items) {
            Diagnostics::drop($section . '.software', 'no item on this page could be turned into a valid SoftwareApplication node');

            return null;
        }

        return ItemListNode::node(
            $url,
            Archive::list_suffix('software-list', $paged),
            $items,
            Archive::first_position($paged, Archive::per_page()),
            EntityIds::webpage($url)
        );
    }
}

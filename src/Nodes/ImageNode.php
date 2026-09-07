<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Support\EntityIds;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ‎ImageObject‎ی واقعی — نه یک ‎@id‎ی یتیم.
 *
 * راهنمایِ صفحهٔ محصول صریح است: تصویرِ اصلیِ صفحه باید هم در
 * ‎primaryImageOfPage‎ ارجاع داده شود و هم به‌عنوانِ یک گرهِ مستقل در
 * همان ‎@graph‎ تعریف شود. ارجاع به ‎@id‎ی که هیچ‌جا تعریف نشده، از نظرِ
 * مصرف‌کننده یعنی هیچ.
 */
final class ImageNode {

    /** @return array<string,mixed>|null */
    public static function from_attachment(int $attachment_id): ?array {
        if ($attachment_id <= 0) {
            return null;
        }

        $src = wp_get_attachment_image_src($attachment_id, 'full');

        if (!is_array($src) || empty($src[0])) {
            return null;
        }

        $node = [
            '@type' => 'ImageObject',
            '@id'   => EntityIds::image((string) $src[0]),
            'url'   => (string) $src[0],
        ];

        if (!empty($src[1])) {
            $node['width'] = (int) $src[1];
        }

        if (!empty($src[2])) {
            $node['height'] = (int) $src[2];
        }

        return $node;
    }

    public static function ref(array $node): array {
        return ['@id' => (string) ($node['@id'] ?? '')];
    }
}

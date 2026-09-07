<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Support\EntityIds;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ‎BreadcrumbList‎ — مشترکِ همهٔ صفحاتِ داخلی.
 *
 * هر ‎Provider‎ فقط زنجیرهٔ «نام => آدرس» را می‌دهد؛ شماره‌گذاری و شکلِ
 * ‎ListItem‎ این‌جا یک‌جا ساخته می‌شود تا بینِ صفحات فرق نکند.
 */
final class BreadcrumbNode {

    /**
     * @param array<int,array{name:string,url:string}> $trail
     * @return array<string,mixed>|null
     */
    public static function node(string $page_url, array $trail): ?array {
        $items = [];
        $position = 0;

        foreach ($trail as $step) {
            $name = trim((string) ($step['name'] ?? ''));
            $url  = trim((string) ($step['url'] ?? ''));

            if ('' === $name || '' === $url) {
                continue;
            }

            $position++;

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'item'     => ['@id' => $url, 'name' => $name],
            ];
        }

        // یک بردکرامبِ تک‌حلقه‌ای («خانه») چیزی به کسی نمی‌گوید.
        if (count($items) < 2) {
            return null;
        }

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => EntityIds::breadcrumb($page_url),
            'itemListElement' => $items,
        ];
    }
}

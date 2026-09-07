<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Support\EntityIds;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ‎ItemList‎ی آرشیو — با شمارشِ ادامه‌دار بینِ صفحات.
 *
 * ‎numberOfItems‎ عمداً تعدادِ آیتم‌هایِ *همین صفحه* است، نه کلِ آرشیو
 * (طبقِ راهنما). عددِ کل در ‎CollectionPage‎ می‌آید.
 */
final class ItemListNode {

    /**
     * @param array<int,array<string,mixed>> $items گره‌هایِ کاملِ آیتم (نه فقط ‎@id‎)
     * @return array<string,mixed>|null
     */
    public static function node(string $page_url, string $suffix, array $items, int $first_position, ?string $main_entity_of = null): ?array {
        if (!$items) {
            return null;
        }

        $elements = [];
        $position = $first_position;

        foreach ($items as $item) {
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'item'     => $item,
            ];

            $position++;
        }

        $node = [
            '@type'           => 'ItemList',
            '@id'             => EntityIds::item_list($page_url, $suffix),
            'numberOfItems'   => count($elements),
            'itemListElement' => $elements,
        ];

        if (null !== $main_entity_of) {
            $node['mainEntityOfPage'] = ['@id' => $main_entity_of];
        }

        return $node;
    }
}

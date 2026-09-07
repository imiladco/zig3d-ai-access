<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Support\EntityIds;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ‎WebPage‎ی عمومی — هر ‎Provider‎ی صفحه (خانه، آرشیوِ فروشگاه، آرشیوِ
 * دانلود، …) همین را با ‎@type‎/فیلدهایِ اضافیِ خودش صدا می‌زند، به‌جایِ
 * اینکه هرکدام دوباره ‎isPartOf‎/‎about‎/‎inLanguage‎ را دستی بسازند.
 */
final class WebPageNode {

    /**
     * @param array<string,mixed> $extra فیلدهایِ اضافه/بازنویسی (مثلِ
     * datePublished/dateModified/breadcrumb) — فقط کلیدهایی که مقدارِ
     * واقعی دارند پاس داده شوند، نه ‎null‎.
     * @return array<string,mixed>
     */
    public static function node(string $url, string $type = 'WebPage', array $extra = []): array {
        $base = [
            '@type'      => $type,
            '@id'        => EntityIds::webpage($url),
            'url'        => $url,
            'name'       => wp_get_document_title(),
            'isPartOf'   => WebsiteNode::ref(),
            'about'      => OrganizationNode::ref(),
            'inLanguage' => 'fa-IR',
        ];

        return array_merge($base, $extra);
    }
}

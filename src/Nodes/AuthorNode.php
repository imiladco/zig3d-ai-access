<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نویسنده — ‎Person‎ی واقعیِ همان پست، نه یک نامِ ثابت.
 *
 * رویِ این سایت بیش از یک نویسنده هست، پس هاردکدکردنِ نام یعنی نسبت‌دادنِ
 * مقاله به آدمِ اشتباه.
 */
final class AuthorNode {

    /** ‎@id‎: صفحهٔ آرشیوِ خودِ نویسنده — پایدارترین شناسه‌ای که وردپرس می‌دهد */
    public static function id(int $author_id): string {
        $url = get_author_posts_url($author_id);

        return is_string($url) && '' !== $url ? untrailingslashit($url) : '';
    }

    /** @return array<string,mixed>|null */
    public static function node(int $post_id): ?array {
        $author_id = (int) get_post_field('post_author', $post_id);

        if ($author_id <= 0) {
            Diagnostics::drop('blog.author', sprintf('post #%d has no author id', $post_id));

            return null;
        }

        $name = trim((string) get_the_author_meta('display_name', $author_id));

        if ('' === $name) {
            Diagnostics::drop('blog.author', sprintf('author #%d has no display name', $author_id));

            return null;
        }

        $node = ['@type' => 'Person', 'name' => $name];

        $id = self::id($author_id);

        if ('' !== $id) {
            $node['@id'] = $id;
            $node['url'] = $id;
        }

        return $node;
    }

    /**
     * ارجاعِ کوتاه وقتی گرهِ کاملِ نویسنده جایِ دیگری در همان گراف هست.
     *
     * @param array<string,mixed> $node
     * @return array<string,mixed>
     */
    public static function ref(array $node): array {
        return isset($node['@id'])
            ? ['@id' => (string) $node['@id']]
            : ['@type' => 'Person', 'name' => (string) ($node['name'] ?? '')];
    }
}

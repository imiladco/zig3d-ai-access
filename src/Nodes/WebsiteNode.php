<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Support\EntityIds;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سایت — سراسری، رویِ همهٔ صفحات. مثلِ ‎OrganizationNode‎، پروفایلِ
 * ثابت از ‎config('website')‎ می‌آید؛ فقط ‎potentialAction‎ (SearchAction)
 * پویاست چون بسته به URLِ واقعیِ نتایجِ جست‌وجو است.
 */
final class WebsiteNode {

    public static function id(): string {
        return EntityIds::website();
    }

    /** @return array<string,mixed> */
    public static function node(): array {
        $config = Config::get('website', []);

        $node = [
            '@type'         => 'WebSite',
            '@id'           => self::id(),
            'name'          => (string) ($config['name'] ?? ''),
            'alternateName' => (string) ($config['alternate_name'] ?? ''),
            'description'   => (string) ($config['description'] ?? ''),
            'url'           => home_url('/'),
            'inLanguage'    => 'fa-IR',
            'publisher'     => OrganizationNode::ref(),
        ];

        $search_action = self::search_action((string) ($config['search_post_type'] ?? ''));

        if (null !== $search_action) {
            $node['potentialAction'] = $search_action;
        }

        return $node;
    }

    /**
     * الگویِ ‎SearchAction‎ — با یک سنتینلِ یک‌بارمصرف به‌جایِ عبارتِ
     * جست‌وجو از ‎get_search_link()‎ (تنها راهِ رسمیِ وردپرس برایِ ساختنِ
     * URLِ نتایج، هماهنگ با پرمالینکِ فعال) خودِ URL را می‌گیریم، آرگومانِ
     * ‎post_type‎ را رویِ همان اضافه می‌کنیم (دقیقاً همان چیزی که ویجتِ
     * ‎zig3d-search‎ در ‎results_url()‎ش انجام می‌دهد)، بعد سنتینل را با
     * ‎{search_term_string}‎ عوض می‌کنیم. اگر ‎get_search_link()‎ سنتینل
     * را در خروجی نگه نداشت (مثلاً یک ری‌رایتِ غیرمنتظره)، بی‌صدا حذف
     * نمی‌کنیم — ‎Diagnostics::drop()‎ دقیقاً می‌گوید چرا.
     */
    private static function search_action(string $post_type): ?array {
        $sentinel = 'zig3d_search_sentinel_' . wp_generate_password(12, false, false);

        $url = get_search_link($sentinel);

        if (!is_string($url) || '' === $url || false === strpos($url, $sentinel)) {
            Diagnostics::drop('website.search-action', 'get_search_link() did not return a URL containing the sentinel search term');

            return null;
        }

        if ('' !== $post_type) {
            $url = add_query_arg('post_type', $post_type, $url);
        }

        $template = str_replace($sentinel, '{search_term_string}', $url);

        return [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => $template,
            ],
            'query-input' => 'required name=search_term_string',
        ];
    }

    /** ارجاعِ کوتاه — جایی که فقط ‎@id‎ لازم است (mainEntityOfPage/…) */
    public static function ref(): array {
        return ['@id' => self::id()];
    }
}

<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Support\EntityIds;
use Zig3d_AI_Access\Support\Faq;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ‎FAQPage‎ — همیشه از مسیرِ پاک‌سازیِ مشترک.
 *
 * قاعدهٔ راهنما: آیتمِ نامعتبر حذف می‌شود، و اگر بعد از فیلتر هیچ آیتمِ
 * معتبری نماند، **کلِ گره** حذف می‌شود — ‎FAQPage‎ با ‎mainEntity: []‎
 * نامعتبر است.
 */
final class FaqPageNode {

    /**
     * @param array<int,array{question?:string,answer?:string}> $raw
     * @return array<string,mixed>|null
     */
    public static function node(string $page_url, array $raw, string $section = 'faq'): ?array {
        if (!$raw) {
            return null;
        }

        $items = Faq::sanitize($raw, $section);

        if (!$items) {
            Diagnostics::drop($section, 'FAQPage omitted: no valid question/answer pair survived sanitisation');

            return null;
        }

        $questions = [];

        foreach ($items as $item) {
            $questions[] = [
                '@type'          => 'Question',
                'name'           => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ];
        }

        return [
            '@type'      => 'FAQPage',
            '@id'        => EntityIds::faq($page_url),
            'mainEntity' => $questions,
        ];
    }
}

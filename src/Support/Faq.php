<?php
namespace Zig3d_AI_Access\Support;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * خواندن و **پاک‌سازیِ** سؤالاتِ متداول.
 *
 * ‎sanitize()‎ قلبِ این کلاس است و یک باگِ واقعیِ تولید را می‌بندد: رویِ
 * صفحهٔ اصلی، سؤالی منتشر شده بود که «جواب»ش چیزی جز تکرارِ خودِ سؤال
 * نبود. برابریِ دقیقِ رشته‌ها آن را نمی‌گرفت (جواب چهاربار سؤال را با
 * فاصله‌هایِ متفاوت تکرار کرده بود)، پس معیار باید محتوایی باشد نه
 * لفظی: جوابی که کلمهٔ *تازه*ای نسبت به سؤال نمی‌آورد، جواب نیست.
 *
 * آستانه از ‎config('faq.min_unique_ratio')‎ می‌آید تا بدونِ دست‌زدن به
 * کد قابلِ‌تنظیم بماند.
 */
final class Faq {

    /**
     * @param array<int,array{question?:string,answer?:string}> $items
     * @return array<int,array{question:string,answer:string}> فقط آیتم‌هایِ معتبر
     */
    public static function sanitize(array $items, string $section = 'faq'): array {
        $out = [];

        foreach ($items as $item) {
            $question = self::clean((string) ($item['question'] ?? ''));
            $answer   = self::clean((string) ($item['answer'] ?? ''));

            if ('' === $question || '' === $answer) {
                Diagnostics::drop($section, 'FAQ item dropped: empty question or answer');

                continue;
            }

            if (!self::answer_adds_content($question, $answer)) {
                Diagnostics::drop(
                    $section,
                    sprintf('FAQ item dropped: answer adds no content beyond the question ("%s")', self::excerpt($question))
                );

                continue;
            }

            $out[] = ['question' => $question, 'answer' => $answer];
        }

        return $out;
    }

    /**
     * آیا جواب واقعاً چیزی *فراتر از* سؤال می‌گوید؟
     *
     * نسبتِ کلمه‌هایِ یکتایِ جواب (آن‌هایی که در سؤال نیستند) به کلِ
     * کلمه‌هایِ جواب. جوابی که از تکرارِ سؤال ساخته شده این نسبت را
     * تقریباً صفر می‌کند؛ جوابِ واقعیِ کوتاه که چند کلمه از سؤال را هم
     * به‌کار می‌برد بالایِ آستانه می‌ماند.
     */
    public static function answer_adds_content(string $question, string $answer): bool {
        if (self::normalize($question) === self::normalize($answer)) {
            return false;
        }

        $answer_words = self::words($answer);

        if (!$answer_words) {
            return false;
        }

        $question_words = array_fill_keys(self::words($question), true);

        $fresh = 0;

        foreach ($answer_words as $word) {
            if (!isset($question_words[$word])) {
                $fresh++;
            }
        }

        $ratio = $fresh / count($answer_words);
        $threshold = (float) Config::get('faq.min_unique_ratio', 0.35);

        return $ratio >= $threshold;
    }

    /**
     * سؤال/جوابِ سطحِ ترم — کلیدهایِ *واقعیِ* دیتابیس از config
     * (شاملِ همان تایپویِ «answere» که در یک نشستِ دیباگ تأیید شد).
     *
     * @return array<int,array{question:string,answer:string}>
     */
    public static function for_term(int $term_id): array {
        if ($term_id <= 0) {
            return [];
        }

        $rows = get_term_meta($term_id, (string) Config::get('faq.term_meta_key', ''), true);

        return self::rows_to_items(
            $rows,
            (string) Config::get('faq.term_title', ''),
            (string) Config::get('faq.term_answer', '')
        );
    }

    /**
     * @return array<int,array{question:string,answer:string}>
     */
    public static function for_post(int $post_id): array {
        if ($post_id <= 0) {
            return [];
        }

        $rows = get_post_meta($post_id, (string) Config::get('faq.post_meta_key', ''), true);

        return self::rows_to_items(
            $rows,
            (string) Config::get('faq.post_title', ''),
            (string) Config::get('faq.post_answer', '')
        );
    }

    /**
     * @param mixed $rows
     * @return array<int,array{question:string,answer:string}>
     */
    private static function rows_to_items($rows, string $title_key, string $answer_key): array {
        if (!is_array($rows) || '' === $title_key || '' === $answer_key) {
            return [];
        }

        $items = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'question' => (string) ($row[$title_key] ?? ''),
                'answer'   => (string) ($row[$answer_key] ?? ''),
            ];
        }

        return $items;
    }

    private static function clean(string $text): string {
        $text = wp_strip_all_tags($text, true);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** حروفِ کوچک، بدونِ نشانه‌گذاری و فاصلهٔ اضافه — برایِ مقایسهٔ محتوایی */
    private static function normalize(string $text): string {
        $text = self::clean($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim(mb_strtolower($text));
    }

    /** @return string[] */
    private static function words(string $text): array {
        $normalized = self::normalize($text);

        if ('' === $normalized) {
            return [];
        }

        return array_values(array_filter(explode(' ', $normalized), static fn(string $w): bool => '' !== $w));
    }

    private static function excerpt(string $text): string {
        return mb_strlen($text) > 60 ? mb_substr($text, 0, 60) . '…' : $text;
    }
}

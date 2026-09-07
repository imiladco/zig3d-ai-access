<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Support\EntityIds;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ‎HowTo‎ی راهنمایِ نصب.
 *
 * منبع همان ریپیتری است که ویجتِ ‎jet-listing-dynamic-repeater‎ رویِ
 * صفحه می‌خواند — نامِ فیلد و کلیدِ متنِ هر مرحله هر دو از config
 * می‌آیند و هر دو از تنظیماتِ خامِ همان ویجت رویِ سایتِ زنده تأیید
 * شده‌اند (‎installation_steps‎ + ‎%step_description%‎).
 *
 * رابطهٔ دوطرفه با نرم‌افزار (‎SoftwareApplication.subjectOf‎ ↔
 * ‎HowTo.about‎) در ‎Provider‎ بسته می‌شود؛ این‌جا فقط سمتِ ‎about‎.
 */
final class HowToNode {

    public static function id(string $page_url): string {
        return untrailingslashit($page_url) . '#installation-guide';
    }

    /** @return array<string,mixed>|null */
    public static function node(int $post_id, string $page_url, string $software_id): ?array {
        $meta_key = (string) Config::get('downloads.install_steps_meta', '');
        $text_key = (string) Config::get('downloads.install_step_text_key', '');

        if ('' === $meta_key || '' === $text_key) {
            Diagnostics::drop('downloads-software.howto', 'config downloads.install_steps_meta/install_step_text_key is empty');

            return null;
        }

        $rows = get_post_meta($post_id, $meta_key, true);

        if (is_string($rows)) {
            $rows = maybe_unserialize($rows);
        }

        if (!is_array($rows) || !$rows) {
            Diagnostics::drop(
                'downloads-software.howto',
                sprintf('software #%d has no rows in the "%s" repeater — HowTo omitted', $post_id, $meta_key)
            );

            return null;
        }

        $steps = [];
        $position = 0;

        foreach ($rows as $row) {
            $text = '';

            if (is_array($row)) {
                $text = (string) ($row[$text_key] ?? '');
            } elseif (is_scalar($row)) {
                $text = (string) $row;
            }

            $text = trim(wp_strip_all_tags($text, true));
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

            if ('' === $text) {
                continue;
            }

            $position++;

            $steps[] = [
                '@type'    => 'HowToStep',
                'position' => $position,
                'text'     => $text,
            ];
        }

        if (!$steps) {
            Diagnostics::drop(
                'downloads-software.howto',
                sprintf('the "%s" repeater had rows but none carried text in "%s"', $meta_key, $text_key)
            );

            return null;
        }

        return [
            '@type' => 'HowTo',
            '@id'   => self::id($page_url),
            'name'  => sprintf('راهنمای نصب %s', (string) get_the_title($post_id)),
            'about' => ['@id' => $software_id],
            'step'  => $steps,
        ];
    }
}

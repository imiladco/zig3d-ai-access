<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Support\EntityIds;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ‎SoftwareApplication‎ — مشترکِ هر سه صفحهٔ دانلود.
 *
 * چرا ‎SoftwareApplication‎ و نه ‎Product‎: این آیتم‌ها قابلِ‌دانلود،
 * نسخه‌دار و وابسته به سیستم‌عامل/دستگاه‌اند، نه کالایِ فیزیکی.
 *
 * دو قاعدهٔ صریحِ راهنما که این‌جا تضمین می‌شوند:
 *  - ‎availableOnDevice‎ به‌کار می‌رود، **نه** ‎device‎ی منسوخ.
 *  - ‎softwareVersion‎ فقط از فیلدِ واقعیِ نسخه؛ هرگز از نامِ فایلِ
 *    دانلود حدس زده نمی‌شود (رویِ نمونهٔ واقعی، نامِ فایل
 *    ‎2025.0618.1137.0‎ بود ولی نسخهٔ رسمی ‎2.0.0‎ — دو چیزِ متفاوت).
 */
final class SoftwareNode {

    public static function id(int $post_id): string {
        return EntityIds::software((string) get_permalink($post_id));
    }

    /**
     * نسخهٔ فهرستی — برایِ ‎ItemList‎ی آرشیوها.
     *
     * @return array<string,mixed>|null
     */
    public static function summary(int $post_id): ?array {
        $url = (string) get_permalink($post_id);

        if ('' === $url) {
            Diagnostics::drop('downloads.software', sprintf('get_permalink(%d) returned nothing', $post_id));

            return null;
        }

        $node = [
            '@type' => 'SoftwareApplication',
            '@id'   => EntityIds::software($url),
            'name'  => (string) get_the_title($post_id),
            'url'   => $url,
        ];

        $description = self::description($post_id);

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $category = self::application_category($post_id);

        if ($category) {
            $node['applicationCategory'] = $category;
        }

        $os = self::operating_system($post_id);

        if ('' !== $os) {
            $node['operatingSystem'] = $os;
        }

        $version = self::version($post_id);

        if (null !== $version) {
            $node['softwareVersion'] = $version;
        }

        $download = self::download_url($post_id);

        if (null !== $download) {
            $node['downloadUrl'] = $download;
        }

        // خلاصهٔ سازگاری برایِ فهرست — آرایهٔ کامل جایِ صفحهٔ خودِ نرم‌افزار است
        $devices = self::devices($post_id);

        if ($devices) {
            $node['availableOnDevice'] = sprintf('سازگار با %d مدل دستگاه', count($devices));
        }

        $node['publisher'] = OrganizationNode::ref();

        return $node;
    }

    /**
     * نسخهٔ کاملِ صفحهٔ خودِ نرم‌افزار.
     *
     * @return array<string,mixed>|null
     */
    public static function full(int $post_id): ?array {
        $node = self::summary($post_id);

        if (null === $node) {
            return null;
        }

        $url = (string) get_permalink($post_id);

        $node['mainEntityOfPage'] = ['@id' => EntityIds::webpage($url)];

        // این‌جا آرایهٔ کاملِ مدل‌ها، نه خلاصهٔ تعداد
        $devices = self::devices($post_id);

        if ($devices) {
            $node['availableOnDevice'] = $devices;
        } else {
            unset($node['availableOnDevice']);

            Diagnostics::drop('downloads-software.devices', sprintf('software #%d lists no compatible device models', $post_id));
        }

        $size = self::file_size($post_id);

        if (null !== $size) {
            $node['fileSize'] = $size;
        }

        $format = self::encoding_format($post_id);

        if (null !== $format) {
            $node['encodingFormat'] = $format;
        }

        $screenshots = self::screenshots($post_id);

        if ($screenshots) {
            $node['screenshot'] = $screenshots;
        }

        return $node;
    }

    /* ------------------------------------------------------------------ */

    private static function description(int $post_id): string {
        $candidates = [
            (string) get_post_field('post_content', $post_id),
            (string) get_post_meta($post_id, 'software-information', true),
            (string) get_post_field('post_excerpt', $post_id),
        ];

        foreach ($candidates as $candidate) {
            $text = trim(wp_strip_all_tags($candidate, true));
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

            if ('' !== $text) {
                return $text;
            }
        }

        Diagnostics::drop('downloads.software', sprintf('software #%d has no description text', $post_id));

        return '';
    }

    /**
     * ‎applicationCategory‎ آرایه‌ای از دو مقدار است (طبقِ راهنما): متنی
     * که *نرم‌افزار* را توصیف می‌کند و آدرسِ واقعیِ صفحهٔ دستهٔ دانلود.
     *
     * متنِ توصیف از نامِ خودِ ترم ساخته می‌شود، نه هاردکد — و «نرم‌افزار»
     * فقط وقتی جلویش می‌آید که خودِ نامِ ترم آن را نداشته باشد، تا
     * «نرم‌افزار نرم‌افزارهای میلینگ» تولید نشود.
     *
     * @return string[]
     */
    private static function application_category(int $post_id): array {
        $term = self::term($post_id);

        if (null === $term) {
            return [];
        }

        $name = trim((string) $term->name);
        $label = (false !== mb_strpos($name, 'نرم‌افزار') || false !== mb_strpos($name, 'نرم افزار'))
            ? $name
            : 'نرم‌افزار ' . $name;

        $out = [$label];

        $link = get_term_link($term);

        if (!is_wp_error($link)) {
            $out[] = (string) $link;
        } else {
            Diagnostics::drop('downloads.software', sprintf('get_term_link() failed for software category "%s"', $term->slug));
        }

        return $out;
    }

    /** ترمِ دستهٔ نرم‌افزار — از تاکسونومیِ *واقعی* پست، نه حدس */
    public static function term(int $post_id): ?\WP_Term {
        $taxonomy = (string) Config::get('downloads.taxonomy', '');

        if ('' === $taxonomy || !taxonomy_exists($taxonomy)) {
            Diagnostics::drop('downloads.software', sprintf('software taxonomy "%s" does not exist', $taxonomy));

            return null;
        }

        $terms = get_the_terms($post_id, $taxonomy);

        if (is_wp_error($terms) || !$terms) {
            Diagnostics::drop('downloads.software', sprintf('software #%d has no term in "%s"', $post_id, $taxonomy));

            return null;
        }

        return $terms[0];
    }

    private static function operating_system(int $post_id): string {
        $field = (string) Config::get('downloads.operating_system_field', '');

        if ('' === $field) {
            return '';
        }

        $values = self::selected_values(get_post_meta($post_id, $field, true));

        return $values ? implode(', ', $values) : '';
    }

    /** فقط از فیلدِ واقعیِ نسخه — هرگز از نامِ فایل */
    private static function version(int $post_id): ?string {
        foreach ((array) Config::get('downloads.version_fields', []) as $field) {
            $value = trim((string) get_post_meta($post_id, (string) $field, true));

            if ('' !== $value) {
                return $value;
            }
        }

        Diagnostics::drop(
            'downloads.software',
            sprintf('software #%d has no version field value — softwareVersion omitted rather than guessed from the file name', $post_id)
        );

        return null;
    }

    /** فقط وقتی فایلِ واقعی هست؛ هرگز آدرسِ *صفحه* به‌جایِ فایل */
    private static function download_url(int $post_id): ?string {
        $field = (string) Config::get('downloads.download_url_meta', '');

        if ('' === $field) {
            return null;
        }

        $url = trim((string) get_post_meta($post_id, $field, true));

        if ('' === $url || 0 !== strpos($url, 'http')) {
            Diagnostics::drop('downloads.software', sprintf('software #%d has no real download file — downloadUrl omitted (never the page URL)', $post_id));

            return null;
        }

        return $url;
    }

    /** @return string[] */
    private static function devices(int $post_id): array {
        $field = (string) Config::get('downloads.compatible_models_field', '');

        return '' === $field ? [] : self::selected_values(get_post_meta($post_id, $field, true));
    }

    /**
     * حجم — واحدِ انگلیسی/استاندارد (‎"66.4 MB"‎)، طبقِ راهنما: پارسرها
     * با واحدِ فارسی کنار نمی‌آیند.
     */
    private static function file_size(int $post_id): ?string {
        $bytes = (int) get_post_meta($post_id, (string) Config::get('downloads.size_meta', ''), true);

        if ($bytes <= 0) {
            Diagnostics::drop('downloads-software.fileSize', sprintf('software #%d has no cached file size', $post_id));

            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit < 2 ? 0 : 1, '.', '') . ' ' . $units[$unit];
    }

    /** از پسوندِ *واقعیِ* فایلِ دانلود */
    private static function encoding_format(int $post_id): ?string {
        $url = self::download_url($post_id);

        if (null === $url) {
            return null;
        }

        $extension = strtolower((string) pathinfo(wp_parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        $formats = [
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
            '7z'  => 'application/x-7z-compressed',
            'exe' => 'application/vnd.microsoft.portable-executable',
            'msi' => 'application/x-msi',
            'dmg' => 'application/x-apple-diskimage',
        ];

        if (!isset($formats[$extension])) {
            Diagnostics::drop('downloads-software.encodingFormat', sprintf('unknown download file extension ".%s"', $extension));

            return null;
        }

        return $formats[$extension];
    }

    /**
     * ‎ImageObject‎هایِ واقعی، نه رشتهٔ خامِ URL (تأکیدِ راهنما).
     *
     * @return array<int,array<string,mixed>>
     */
    private static function screenshots(int $post_id): array {
        $field = (string) Config::get('downloads.gallery_meta', '');

        if ('' === $field) {
            return [];
        }

        $raw = get_post_meta($post_id, $field, true);

        if (is_string($raw) && '' !== $raw && false !== strpos($raw, ',')) {
            $raw = array_map('trim', explode(',', $raw));
        }

        $out = [];

        foreach ((array) $raw as $item) {
            if (is_array($item)) {
                $item = $item['id'] ?? ($item['url'] ?? '');
            }

            if (is_numeric($item)) {
                $node = ImageNode::from_attachment((int) $item);

                if (null !== $node) {
                    $out[] = $node;
                }

                continue;
            }

            $url = trim((string) $item);

            if (0 === strpos($url, 'http')) {
                $out[] = ['@type' => 'ImageObject', '@id' => EntityIds::image($url), 'url' => $url];
            }
        }

        return $out;
    }

    /**
     * فیلدهایِ چک‌باکسیِ جت‌اینجین به‌شکلِ ‎['up300' => 'true', …]‎ ذخیره
     * می‌شوند — فقط کلیدهایی که واقعاً انتخاب شده‌اند برمی‌گردند.
     *
     * @param mixed $raw
     * @return string[]
     */
    private static function selected_values($raw): array {
        if (is_string($raw)) {
            $raw = maybe_unserialize($raw);
        }

        if (!is_array($raw)) {
            $value = trim((string) $raw);

            return '' === $value ? [] : [$value];
        }

        $out = [];

        foreach ($raw as $key => $value) {
            // نگاشتِ کلید=>true (چک‌باکس)
            if (is_string($key) && !is_array($value)) {
                $flag = strtolower(trim((string) $value));

                if (in_array($flag, ['true', '1', 'yes'], true)) {
                    $out[] = $key;
                }

                continue;
            }

            // فهرستِ ساده
            if (is_scalar($value)) {
                $value = trim((string) $value);

                if ('' !== $value) {
                    $out[] = $value;
                }
            }
        }

        return array_values(array_unique($out));
    }
}

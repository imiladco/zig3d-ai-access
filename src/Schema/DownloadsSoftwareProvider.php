<?php
namespace Zig3d_AI_Access\Schema;

use Zig3d_AI_Access\Diagnostics;
use Zig3d_AI_Access\Schema_Provider;
use Zig3d_AI_Access\Nodes\BreadcrumbNode;
use Zig3d_AI_Access\Nodes\FaqPageNode;
use Zig3d_AI_Access\Nodes\HowToNode;
use Zig3d_AI_Access\Nodes\SoftwareNode;
use Zig3d_AI_Access\Nodes\WebPageNode;
use Zig3d_AI_Access\Support\Downloads;
use Zig3d_AI_Access\Support\EntityIds;
use Zig3d_AI_Access\Support\Faq;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ تکِ نرم‌افزار.
 *
 * ‎HowTo‎ی راهنمایِ نصب گرهِ جداست (‎#installation-guide‎) و رابطه‌اش با
 * نرم‌افزار **دوطرفه** بسته می‌شود:
 * ‎SoftwareApplication.subjectOf → HowTo‎ و ‎HowTo.about → SoftwareApplication‎.
 * یک‌طرفه بستنش یعنی مصرف‌کننده‌ای که از سمتِ دیگر می‌آید رابطه را
 * نمی‌بیند.
 *
 * بردکرامب و ‎applicationCategory‎ هر دو از تاکسونومیِ *واقعیِ* پست
 * می‌آیند (‎get_the_terms()‎). اگر دسته‌بندیِ یک نرم‌افزار در دیتابیس
 * غلط باشد، schema هم همان غلط را نشان می‌دهد — این عمدی است: وظیفهٔ
 * schema بازتابِ دادهٔ واقعی است، نه اصلاحِ حدسیِ آن. (باگِ دسته‌بندی،
 * اگر بود، یک مسئلهٔ محتوایی است و باید جدا اصلاح شود.)
 */
final class DownloadsSoftwareProvider implements Schema_Provider {

    public function applies(): bool {
        $post_type = Downloads::post_type();

        return '' !== $post_type && is_singular($post_type);
    }

    public function rank_math_scope(): string {
        return 'downloads-software';
    }

    /** @return array<int,array<string,mixed>> */
    public function nodes(): array {
        $post_id = (int) get_queried_object_id();

        if ($post_id <= 0) {
            Diagnostics::drop('downloads-software', 'get_queried_object_id() returned 0');

            return [];
        }

        $url = (string) get_permalink($post_id);

        if ('' === $url) {
            Diagnostics::drop('downloads-software', sprintf('get_permalink(%d) returned nothing', $post_id));

            return [];
        }

        $nodes = [];

        $breadcrumb = BreadcrumbNode::node($url, $this->trail($post_id, $url));

        if (null !== $breadcrumb) {
            $nodes[] = $breadcrumb;
        }

        $software = SoftwareNode::full($post_id);

        if (null === $software) {
            // دلیل را خودِ SoftwareNode ثبت کرده
            return $nodes;
        }

        $howto = HowToNode::node($post_id, $url, (string) $software['@id']);

        if (null !== $howto) {
            // سمتِ دومِ رابطه — قبل از افزودنِ گرهِ نرم‌افزار به گراف
            $software['subjectOf'] = [['@id' => (string) $howto['@id']]];
        }

        $nodes[] = $this->webpage($url, $post_id, $breadcrumb, (string) $software['@id']);
        $nodes[] = $software;

        if (null !== $howto) {
            $nodes[] = $howto;
        }

        $faq = FaqPageNode::node($url, Faq::for_post($post_id), 'downloads-software.faq');

        if (null !== $faq) {
            $nodes[] = $faq;
        }

        return $nodes;
    }

    /* ------------------------------------------------------------------ */

    /** @return array<int,array{name:string,url:string}> */
    private function trail(int $post_id, string $url): array {
        $trail = [
            ['name' => 'خانه', 'url' => home_url('/')],
            ['name' => 'نرم‌افزارها', 'url' => Downloads::archive_url()],
        ];

        $term = SoftwareNode::term($post_id);

        if (null !== $term) {
            $link = get_term_link($term);

            if (!is_wp_error($link)) {
                $trail[] = ['name' => (string) $term->name, 'url' => (string) $link];
            }
        }

        $trail[] = ['name' => (string) get_the_title($post_id), 'url' => $url];

        return $trail;
    }

    /**
     * @param array<string,mixed>|null $breadcrumb
     * @return array<string,mixed>
     */
    private function webpage(string $url, int $post_id, ?array $breadcrumb, string $software_id): array {
        $extra = [
            'inLanguage' => 'fa-IR',
            'mainEntity' => ['@id' => $software_id],
        ];

        /*
         * تاریخ‌ها از ‎get_post_datetime()‎ — نه از ردیفِ «تاریخِ
         * به‌روزرسانی»یِ جدول که رویِ سایت *جلالی* نمایش داده می‌شود.
         * راهنما هشدار می‌دهد تبدیلِ دستی/تخمینی نکنیم؛ امن‌ترین کار
         * خواندنِ همان مقدارِ خامِ میلادی از خودِ دیتابیس است.
         */
        $post = get_post($post_id);

        if ($post instanceof \WP_Post) {
            $published = get_post_datetime($post, 'date');
            $modified  = get_post_datetime($post, 'modified');

            if ($published instanceof \DateTimeImmutable) {
                $extra['datePublished'] = $published->format(DATE_ATOM);
            }

            if ($modified instanceof \DateTimeImmutable) {
                $extra['dateModified'] = $modified->format(DATE_ATOM);
            }
        } else {
            Diagnostics::drop('downloads-software.dates', sprintf('get_post(%d) did not return a WP_Post', $post_id));
        }

        if (null !== $breadcrumb) {
            $extra['breadcrumb'] = ['@id' => (string) $breadcrumb['@id']];
        }

        return WebPageNode::node($url, 'WebPage', $extra);
    }
}

add_filter('zig3d_ai_access/providers', static function (array $providers): array {
    $providers[] = new DownloadsSoftwareProvider();

    return $providers;
});

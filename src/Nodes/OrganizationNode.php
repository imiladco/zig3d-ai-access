<?php
namespace Zig3d_AI_Access\Nodes;

use Zig3d_AI_Access\Config;
use Zig3d_AI_Access\Support\EntityIds;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سازمان — سراسری، رویِ همهٔ صفحات. پروفایلِ هویتیِ شرکت، از
 * ‎config('organization')‎ (به‌ندرت عوض می‌شود، پس هاردکد آن‌جا عمدی
 * است — نه یک کوئری برایِ چیزی که در دیتابیس ذخیره نشده).
 *
 * ‎@type: Corporation‎ (نه ‎Organization‎ ساده): زیگ شرکتِ ثبت‌شدهٔ
 * تجاری است. ‎LocalBusiness‎ عمداً استفاده نشد — آدرس فقط دفترِ شرکت
 * است، مشتری حضوری نمی‌پذیرد.
 */
final class OrganizationNode {

    public static function id(): string {
        return EntityIds::organization();
    }

    /** @return array<string,mixed> */
    public static function node(): array {
        $config = Config::get('organization', []);

        $node = [
            '@type'         => 'Corporation',
            '@id'           => self::id(),
            'name'          => (string) ($config['name'] ?? ''),
            'alternateName' => (array) ($config['alternate_name'] ?? []),
            'url'           => home_url('/'),
            'foundingDate'  => (string) ($config['founding_date'] ?? ''),
            'knowsAbout'    => (array) ($config['knows_about'] ?? []),
            'logo'          => [
                '@type'      => 'ImageObject',
                '@id'        => EntityIds::fragment('logo'),
                'url'        => (string) ($config['logo_url'] ?? ''),
                'contentUrl' => (string) ($config['logo_url'] ?? ''),
            ],
            'image'   => (string) ($config['image_url'] ?? ''),
            'address' => self::address((array) ($config['address'] ?? [])),
            'contactPoint' => self::contact_points((array) ($config['contact_points'] ?? [])),
            'email'   => (string) ($config['email'] ?? ''),
            'sameAs'  => (array) ($config['same_as'] ?? []),
        ];

        return $node;
    }

    /** @return array<string,mixed> */
    private static function address(array $address): array {
        return [
            '@type'           => 'PostalAddress',
            'streetAddress'   => (string) ($address['street_address'] ?? ''),
            'addressLocality' => (string) ($address['address_locality'] ?? ''),
            'postalCode'      => (string) ($address['postal_code'] ?? ''),
            'addressCountry'  => (string) ($address['address_country'] ?? ''),
        ];
    }

    /**
     * @param array<int,array{telephone?:string,contact_type?:string,email?:string|null}> $points
     * @return array<int,array<string,mixed>>
     */
    private static function contact_points(array $points): array {
        $out = [];

        foreach ($points as $point) {
            $entry = [
                '@type'            => 'ContactPoint',
                'telephone'        => (string) ($point['telephone'] ?? ''),
                'contactType'      => (string) ($point['contact_type'] ?? ''),
                'areaServed'       => 'IR',
                'availableLanguage' => ['fa'],
            ];

            if (!empty($point['email'])) {
                $entry['email'] = (string) $point['email'];
            }

            $out[] = $entry;
        }

        return $out;
    }

    /** ارجاعِ کوتاه — جایی که فقط ‎@id‎ لازم است (seller/publisher/…) */
    public static function ref(): array {
        return ['@id' => self::id()];
    }
}

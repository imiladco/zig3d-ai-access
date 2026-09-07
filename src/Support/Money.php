<?php
namespace Zig3d_AI_Access\Support;

use Zig3d_AI_Access\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تبدیلِ قیمت — تابعِ خالص، تنها جایی که قاعدهٔ «تومان×۱۰ → ریال» زندگی
 * می‌کند.
 *
 * ووکامرسِ این سایت قیمت را به **تومان** ذخیره می‌کند، ولی ‎IRT‎ کدِ
 * معتبرِ ISO 4217 نیست و مصرف‌کنندهٔ ماشینی نمی‌فهمدش. واحدِ رسمیِ ایران
 * ریال (‎IRR‎) است و هر تومان ده ریال. پس عددِ ذخیره‌شده ×۱۰ می‌شود و
 * ارز همیشه ‎IRR‎ اعلام می‌شود — هر دو مقدار از ‎config('money')‎، نه
 * هاردکد.
 */
final class Money {

    /**
     * @param mixed $stored قیمتِ خامِ ووکامرس (تومان)
     * @return string|null رشتهٔ عددیِ ریال، یا ‎null‎ اگر قیمت معتبر نبود
     *
     * رشته برمی‌گرداند نه float: قیمت‌هایِ این‌جا به میلیارد ریال می‌رسند
     * و float در JSON به نمادِ علمی (‎6.386E+10‎) تبدیل می‌شود که هیچ
     * اعتبارسنجی‌ای قبولش ندارد.
     */
    public static function to_output( $stored): ?string {
        if (!is_scalar($stored)) {
            return null;
        }

        $value = trim((string) $stored);

        if ('' === $value || !is_numeric($value)) {
            return null;
        }

        $multiplier = (int) Config::get('money.multiplier', 10);
        $amount = (float) $value * $multiplier;

        if ($amount <= 0) {
            return null;
        }

        // بدونِ اعشار: ریال واحدِ خردتر ندارد، و ‎.00‎ فقط نویز است.
        return number_format($amount, 0, '.', '');
    }

    public static function currency(): string {
        return (string) Config::get('money.output_currency', 'IRR');
    }
}

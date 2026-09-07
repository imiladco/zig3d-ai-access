<?php
namespace Zig3d_AI_Access;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دسترسیِ خواندنی به ‎config.php‎ — با فیلترِ ‎zig3d_ai_access/config‎
 * (این‌جا اعمال می‌شود، نه در خودِ ‎config.php‎، تا آن فایل یک آرایهٔ
 * دادهٔ محض بماند، بدونِ هیچ فراخوانیِ وردپرسی).
 *
 * کلیدهایِ تودرتو با نقطه: ‎Config::get('faq.term_meta_key')‎.
 */
final class Config {

    /** @var array<string,mixed>|null */
    private static ?array $data = null;

    /** @return array<string,mixed> */
    public static function all(): array {
        if (null === self::$data) {
            $file = ZIG3D_AI_ACCESS_PATH . 'config.php';
            $raw  = is_file($file) ? (array) require $file : [];

            self::$data = (array) apply_filters('zig3d_ai_access/config', $raw);
        }

        return self::$data;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        $value = self::all();

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** فقط برایِ تست/دیباگ — یک درخواستِ واقعی هیچ‌وقت این را صدا نمی‌زند */
    public static function reset(): void {
        self::$data = null;
    }
}

<?php
namespace Zig3d_AI_Access;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * قراردادِ هر ماژولِ صفحه.
 *
 * ‎Plugin‎ کلاسِ هیچ ماژولی را نمی‌شناسد — فقط رجیستریِ فیلترِ
 * ‎zig3d_ai_access/providers‎ را می‌پیماید و رویِ همین اینترفیس تکیه
 * می‌کند. افزودنِ صفحهٔ جدید یعنی یک کلاسِ تازه که خودش را به همان
 * فیلتر اضافه می‌کند — نه ویرایشِ ‎Plugin‎.
 */
interface Schema_Provider {

    /** آیا این ماژول مسئولِ درخواستِ فعلی است؟ */
    public function applies(): bool;

    /**
     * گره‌هایِ ‎@graph‎ — هرکدام ممکن است حذف شود (fail-safe)؛ دلیلِ هر
     * حذف باید با ‎Diagnostics::drop()‎ ثبت شود.
     *
     * @return array<int,array<string,mixed>>
     */
    public function nodes(): array;

    /**
     * برچسبِ کوتاهِ این ماژول — فقط برایِ خوانایی در دیباگ/لاگ (مثلِ
     * ‎'shop-product'‎)؛ خودِ ‎Route::is_covered()‎ منطقش را از
     * ‎applies()‎ می‌گیرد، نه از این متد.
     */
    public function rank_math_scope(): string;
}

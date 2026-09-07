<?php
/**
 * تنها منبعِ ثابت‌هایِ خاصِ این نصب.
 *
 * هرچه به این سایتِ بخصوص وابسته است — شناسهٔ یک عنصرِ المنتور، کلیدِ متا،
 * تاکسونومی، نامزدهایِ کلیدِ Loop Grid — این‌جا و فقط این‌جا. اگر تم/
 * المنتور یک شناسه را عوض کرد، یک ویرایش کافی است؛ قبلاً باید چند فایل
 * می‌گشتی.
 *
 * از فیلترِ ‎zig3d_ai_access/config‎ عبور می‌کند (در ‎Config::all()‎، نه
 * این‌جا) — یعنی یک سایتِ دیگر یا یک ‎functions.php‎ می‌تواند این آرایه
 * را بدونِ دست‌زدن به خودِ افزونه بازنویسی کند.
 *
 * فقط یک آرایه برمی‌گرداند — هیچ منطقی، هیچ فراخوانیِ وردپرسی. یک فایلِ
 * دادهٔ محض، تا بدونِ بوت‌شدنِ کامل افزونه هم قابلِ‌خواندن/دیف‌کردن باشد.
 */

return [

    /*
     * صفحهٔ اصلی: کوئریِ جت‌اینجینِ «دسته‌هایِ شاخص» — بلااستفاده (طبقِ
     * تأییدِ کاربر، دسته‌هایِ شاخص یعنی *همهٔ* ترم‌هایِ ‎product_cat‎، نه
     * این کوئری)؛ فقط برایِ مرجع نگه داشته شده.
     *
     * توجه: دیگر هیچ آی‌دیِ ثابتِ element/template برایِ «محصولاتِ
     * منتخب» این‌جا نیست — یک نمونه (‎ac64ce3‎/‎15031‎) رویِ سایتِ
     * واقعی اشتباه از آب درآمد (المنت دیگر آن‌جا نبود). به‌جایش
     * ‎loop_grid.widget_types‎ در پایین: ویجت به‌صورتِ پویا با نوعش
     * پیدا می‌شود، نه با یک آی‌دیِ حدسی.
     */
    'home' => [
        'featured_categories_query' => 11,
    ],

    /*
     * کلیدهایِ محتملِ تنظیماتِ Loop Gridِ المنتور پرو — به‌ترتیبِ اولویت.
     * اولین کلیدی که در تنظیماتِ *واقعیِ* آن نمونهٔ ویجت پیدا شد برنده
     * است. برایِ کشفِ کلیدِ واقعی: ‎wp zig3d dump-element <id>‎.
     */
    'loop_grid' => [
        'manual_id_keys' => ['posts_ids', 'query_posts_ids', 'jet_manual_selection_ids', 'query_1_posts_ids', 'manual_ids'],
        'per_page_keys'  => ['posts_per_page', 'query_posts_per_page', 'jet_posts_num'],
        'orderby_keys'   => ['orderby', 'query_orderby'],
        'order_keys'     => ['order', 'query_order'],

        /*
         * نامزدهایِ widgetTypeِ ویجتِ «محصولاتِ منتخب» — به‌ترتیبِ
         * اولویت. **حدسِ مستندشده**: هیچ‌کدام هنوز رویِ دادهٔ زنده
         * تأیید نشده — اولین ویجتی که widgetTypeش با یکی از این‌ها
         * جور دربیاید انتخاب می‌شود (چه رویِ خودِ صفحهٔ اصلی، چه داخلِ
         * یک قالبِ Theme Builder). اگر هیچ‌کدام جور درنیامدند، گره
         * بدونِ حدس‌زدن حذف می‌شود و Diagnostics دلیلش را می‌گوید — نه
         * جایگزینیِ خودسرانه با یک آی‌دیِ دیگر. برایِ کشفِ مقدارِ
         * واقعی: ‎wp zig3d dump-element <id> --post_id=<id>‎.
         */
        'widget_types' => [
            'loop-grid',
            'jet-engine-listing-grid',
            'jet-listing-grid',
            'jet-woo-product-grid',
            'woocommerce-products',
            'posts',
        ],
    ],

    /*
     * FAQ — کلیدهایِ *واقعیِ* دیتابیس، نه پیش‌فرضِ خودِ ویجت. سطحِ ترم با
     * یک نشستِ دیباگِ قبلی با کاربر تأیید شد (شاملِ همان تایپویِ
     * «answere»)؛ سطحِ پست هنوز با پیش‌فرضِ خودِ ویجت است چون شاهدِ
     * مخالفی ندیدیم.
     */
    'faq' => [
        'term_meta_key'  => 'zig3d-faq-terms',
        'term_title'     => 'zig3d-faq-terms-title',
        'term_answer'    => 'zig3d-faq-terms-answere',
        'post_meta_key'  => 'faq',
        'post_title'     => 'faq_question',
        'post_answer'    => 'faq_answer',
        'widget_type'    => 'zig3d-faq',

        /*
         * آستانهٔ «یکتاییِ محتوا»یِ Faq::sanitize(): اگر جوابِ نرمال‌شده
         * کمتر از این نسبت کلمهٔ *یکتا* (نسبت‌به‌سؤال) داشته باشد —
         * یعنی عمدتاً از تکرارِ خودِ سؤال ساخته شده — نامعتبر شمرده
         * می‌شود. مقدارِ ۰٫۳۵ روی نمونهٔ واقعیِ باگ (چهاربار تکرارِ
         * سؤال) به‌وضوح رد می‌شود ولی جواب‌هایِ کوتاهِ واقعی که فقط
         * چندکلمه از متنِ سؤال را دوباره به‌کار می‌برند رد نمی‌شوند.
         */
        'min_unique_ratio' => 0.35,
    ],

    /** تبدیلِ قیمت: تومانِ ذخیره‌شده × ۱۰ → ریالِ ISO 4217 معتبر */
    'money' => [
        'stored_currency' => 'IRT',
        'output_currency' => 'IRR',
        'multiplier'       => 10,
    ],

    /*
     * CPT/تاکسونومیِ «نرم‌افزارها» — با شناسهٔ داخلیِ JetEngine (نه
     * اسلاگِ حدسی، چون اسلاگ می‌تواند بینِ نصب‌ها فرق کند).
     */
    'downloads' => [
        'jetengine_cpt_id'      => 8,
        'taxonomy'               => 'software-category',
        'download_url_meta'      => 'download_url',
        'gallery_meta'           => 'software_gallery',
        'version_fields'         => ['software_version', 'version'],
        'date_fields'            => ['release_date', 'updated_date'],
        'size_meta'               => '_zig_download_size_bytes',
        'compatible_models_field' => 'compatible_models',
        'operating_system_field'  => 'supported_os',

        /*
         * راهنمایِ نصب (ویجتِ ‎jet-listing-dynamic-repeater‎) — **حدسِ
         * مستندشده**: کلیدِ دقیقِ فیلدِ ریپیتر روی این سایت هنوز از رویِ
         * دادهٔ زنده تأیید نشده. اگر اشتباه بود، فقط همین دو مقدار را
         * اصلاح کن — کدِ خواننده جای دیگری تغییر نمی‌خواهد.
         */
        'install_steps_meta'     => 'installation_steps',
        'install_step_text_key'  => 'step_text',
    ],

    /** برندِ محصول — سه تاکسونومیِ ممکن (ووکامرسِ ۹٫۴+ و دو افزونهٔ قدیمی‌تر) */
    'brand' => [
        'taxonomies'               => ['product_brand', 'pwb-brand', 'product_brands'],
        'manufactured_by_zig_meta' => '_zig3d_manufactured_by_zig',
        'primary_category_meta'    => ['rank_math_primary_product_cat', '_yoast_wpseo_primary_product_cat'],
    ],

    /** لینکِ اختیاریِ «راهنمای خرید» رویِ صفحهٔ یک دستهٔ محصول */
    'shop_category' => [
        'buying_guide_meta' => '_zig3d_buying_guide_url',
    ],

    /*
     * بلاگ — کیوردهایِ سئو (رنک‌مث/یوست)، و ارجاعاتِ «این مقاله دربارهٔ
     * کدام محصول/دسته است» که طبقِ راهنما باید دستی/با یک فیلدِ ادمین
     * باشد، نه استخراجِ خودکار از متن.
     */
    'blog' => [
        'focus_keyword_meta'      => ['rank_math_focus_keyword', '_yoast_wpseo_focuskw'],
        'mentions_category_meta' => '_zig3d_mentions_category',
        'mentions_products_meta' => '_zig3d_mentions_products',
        'reading_wpm'             => 200,
    ],

    /** فیلدِ ریپیترِ «قابلیت‌های کلیدی» رویِ خودِ محصول (ویجتِ zig3d-feature-showcase) */
    'product' => [
        'feature_showcase_meta' => 'feature_showcase',
        'documents_meta'        => 'documents',
        'document_fields'       => [
            'file'   => 'document_file',
            'title'  => 'document_title',
            'format' => 'document_format',
        ],
    ],

    /*
     * پروفایلِ سازمان — سراسری، رویِ هر صفحه چاپ می‌شود. تقریباً هیچ‌وقت
     * عوض نمی‌شود؛ هاردکد اینجا عمدی است، نه یک کوئری برایِ چیزی که
     * جایی در دیتابیس ذخیره نشده.
     */
    'organization' => [
        'name'           => 'گروه زیگ',
        'alternate_name' => ['زیگ', 'زیگورات', 'ZIG3D', 'Zig'],
        'founding_date'  => '2013',
        'knows_about'    => [
            'Digital Dentistry',
            'Dental CAD/CAM',
            'Dental Milling Machines',
            '3D Dental Scanners',
            'Dental 3D Printing',
            'Dental Laboratory Workflow',
        ],
        'logo_url'    => 'https://zig3d.com/wp-content/uploads/2026/08/image-4.avif',
        'image_url'   => 'https://zig3d.com/wp-content/uploads/2026/08/image-1.avif',
        'address'     => [
            'street_address'   => 'خیابان جابر انصاری، پلاک 7، واحد 4',
            'address_locality' => 'اصفهان',
            'postal_code'      => '8193989730',
            'address_country'  => 'IR',
        ],
        'contact_points' => [
            ['telephone' => '+98-31-34415816', 'contact_type' => 'customer service', 'email' => null],
            ['telephone' => '+98-910-8087105', 'contact_type' => 'sales', 'email' => null],
            ['telephone' => '+98-916-2012208', 'contact_type' => 'technical support', 'email' => 'Pooria7alijani@gmail.com'],
            ['telephone' => '+98-913-4646801', 'contact_type' => 'emergency', 'email' => null],
        ],
        'email'   => 'info@zig3d.com',
        'same_as' => ['https://www.instagram.com/ziggroup_dental'],
    ],

    'website' => [
        'name'          => 'زیگ (ZIG3D)',
        'alternate_name' => 'گروه زیگ',
        'description'    => 'راهکارهای دیجیتال دندانسازی و دندانپزشکی',
        /*
         * قسمتِ ‎post_type=product‎ی الگویِ SearchAction — همان چیزی که
         * ‎results_url()‎ی خودِ ویجتِ ‎zig3d-search‎ به‌عنوانِ آرگومان به
         * ‎add_query_arg()‎ می‌دهد. اگر روزی آن ویجت پارامترِ دیگری اضافه
         * کرد، همین‌جا هم باید هماهنگ شود.
         */
        'search_post_type' => 'product',
    ],

    /*
     * کش — عمداً هنوز پیاده نشده (طبقِ سندِ معماری). این بخش فقط نقطهٔ
     * اتصال را نگه می‌دارد تا وقتی تصمیمِ نهایی گرفته شد، جایِ روشنی
     * برایِ فعال‌کردنش باشد — بدونِ اینکه معماریِ اطرافش عوض شود.
     */
    'cache' => [
        'enabled' => false,
        'ttl'     => 0,
    ],

];

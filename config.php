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
     * کلیدهایِ تنظیماتِ ویجت‌هایِ گرید — **همه از دادهٔ زندهٔ همین سایت
     * تأیید شده‌اند** (کاوشِ تنظیماتِ خامِ ویجت‌ها)، نه حدس.
     *
     * چیزی که کاوش نشان داد: ویجتِ «محصولاتِ منتخب»یِ صفحهٔ اصلی
     * (‎loop-grid‎، رویِ خودِ پستِ صفحهٔ اصلی) **انتخابِ دستی ندارد** —
     * یک کوئریِ پویا رویِ ‎post_query_post_type = 'product'‎ است. پس
     * مسیرِ «شناسه‌هایِ دستی» به‌تنهایی هیچ‌وقت برایِ این صفحه جواب
     * نمی‌داد؛ مسیرِ کوئری هم لازم است (نگاه کنید به ‎LoopGrid::query()‎).
     *
     * انتخابِ دستی جایِ دیگری *هست* (قالبِ «پرزنت برند»)، با کلیدِ
     * ‎product_query_posts_ids‎ — پس هر دو مسیر واقعی‌اند.
     */
    'loop_grid' => [
        // ترتیب مهم است: کلیدهایِ تأییدشدهٔ این سایت اول
        'manual_id_keys' => ['product_query_posts_ids', 'post_query_posts_ids', 'posts_ids'],
        'per_page_keys'  => ['posts_per_page', 'posts_num'],
        'orderby_keys'   => ['orderby', 'post_query_orderby', 'product_query_orderby'],
        'order_keys'     => ['order', 'post_query_order', 'product_query_order'],

        /*
         * نوعِ کوئری: ‎'by_id'‎ یعنی انتخابِ دستی، هر مقدارِ دیگری
         * (‎'product'‎، ‎'current_query'‎، ‎'related'‎، …) یعنی کوئریِ پویا.
         */
        'query_type_keys' => ['product_query_post_type', 'post_query_post_type'],

        /*
         * ‎widgetType‎هایِ *واقعیِ* گریدها رویِ این سایت. ‎loop-grid‎ اول
         * است چون همان ویجتِ محصولاتِ منتخبِ صفحهٔ اصلی است؛ صفحهٔ اصلی
         * چند ‎jet-listing-grid‎ی دیگر هم دارد (دسته‌ها، اسلایدرها) که
         * نباید به‌جایش انتخاب شوند.
         */
        'widget_types' => [
            'loop-grid',
            'jet-listing-grid',
            'loop-carousel',
        ],
    ],

    /*
     * FAQ — کلیدهایِ *واقعیِ* دیتابیس، هر دو سطح از دادهٔ زنده تأیید
     * شده (شاملِ همان تایپویِ «answere» که در هر دو سطح تکرار شده).
     *
     * سطحِ پست قبلاً حدس بود (‎faq‎/‎faq_question‎/‎faq_answer‎) و **غلط**
     * از آب درآمد: کلیدِ واقعی ‎zig3d-faq-posts‎ است. با آن حدس، FAQِ
     * هیچ محصول/نرم‌افزاری هیچ‌وقت خوانده نمی‌شد.
     */
    'faq' => [
        'term_meta_key'  => 'zig3d-faq-terms',
        'term_title'     => 'zig3d-faq-terms-title',
        'term_answer'    => 'zig3d-faq-terms-answere',
        'post_meta_key'  => 'zig3d-faq-posts',
        'post_title'     => 'zig3d-faq-posts-title',
        'post_answer'    => 'zig3d-faq-posts-answere',
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
        /* پشتیبانِ اسلاگ، اگر JetEngine در دسترس نبود — از دادهٔ زنده تأیید شد */
        'post_type'              => 'downloads',
        'taxonomy'               => 'software-category',
        'download_url_meta'      => 'download_url',
        'gallery_meta'           => 'software_gallery',
        'version_fields'         => ['software_version', 'version'],
        'date_fields'            => ['release_date', 'updated_date'],
        'size_meta'               => '_zig_download_size_bytes',
        'compatible_models_field' => 'compatible_models',
        'operating_system_field'  => 'supported_os',

        /*
         * راهنمایِ نصب — **از دادهٔ زنده تأیید شد**. تنظیماتِ خامِ ویجتِ
         * ‎jet-listing-dynamic-repeater‎ رویِ قالبِ «Single Download»:
         *   dynamic_field_source = 'installation_steps'
         *   dynamic_field_format = '%step_description%'
         *
         * یعنی نامِ فیلدِ ریپیتر درست حدس زده شده بود ولی کلیدِ متنِ هر
         * مرحله **نه**: ‎step_description‎ است، نه ‎step_text‎. با آن حدس
         * هیچ مرحله‌ای خوانده نمی‌شد.
         */
        'install_steps_meta'     => 'installation_steps',
        'install_step_text_key'  => 'step_description',

        /** مشخصاتِ سیستم — کلیدهایِ واقعیِ همین CPT */
        'requirement_fields' => [
            'processor' => 'required_processor',
            'gpu'       => 'required_gpu',
            'ram'       => 'required_ram',
        ],
        'brand_field'        => 'software_brand',
        'file_type_field'    => 'file_type',
        'architecture_field' => 'system_architecture',
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
     * بلاگ.
     *
     * ‎focus_keyword_meta‎ رویِ دادهٔ زنده تأیید شد
     * (‎rank_math_focus_keyword‎ مقدار دارد).
     *
     * ‎mentions_*‎ عمداً **خالی** است: کاوشِ متایِ یک نوشتهٔ واقعی نشان
     * داد هیچ فیلدی برایِ «این مقاله دربارهٔ کدام محصول/دسته است» رویِ
     * این سایت وجود ندارد. راهنما می‌گوید این ارجاع باید *دستی* باشد نه
     * استخراجِ خودکار از متن — پس تا وقتی مدیرِ سایت چنین فیلدی نساخته،
     * ‎mentions‎ اصلاً چاپ نمی‌شود (و ‎Diagnostics‎ دلیلش را می‌گوید).
     * برایِ فعال‌کردن، فقط کافی است کلیدِ واقعی این‌جا نوشته شود.
     */
    'blog' => [
        'focus_keyword_meta'     => ['rank_math_focus_keyword', '_yoast_wpseo_focuskw'],
        'primary_category_meta'  => ['rank_math_primary_category', '_yoast_wpseo_primary_category'],
        'mentions_category_meta' => '',
        'mentions_products_meta' => '',
        'reading_wpm'            => 200,
    ],

    /*
     * ریپیترهایِ محصول — همه از دادهٔ زندهٔ یک محصولِ واقعی تأیید شدند.
     *
     * ‎documents_meta‎ قبلاً ‎'documents'‎ حدس زده شده بود و **غلط** بود؛
     * کلیدِ واقعی ‎zig_product_document‎ است. کلیدهایِ *داخلِ* هر ردیف
     * (آیکون/عنوان/فایل) پیشوندِ مشترکِ همان فیلد را دارند، ولی چون
     * ریپیترهایِ جت‌اینجین نام‌گذاریِ یکدستی ندارند، خواننده به‌جایِ
     * تکیه به نامِ دقیق، اولین مقدارِ آدرس‌مانند و اولین متنِ غیرخالی را
     * برمی‌دارد (نگاه کنید به ‎ProductNode::documents()‎) — پس تغییرِ
     * نامِ زیرفیلدها این‌جا چیزی را نمی‌شکند.
     */
    'product' => [
        'feature_showcase_meta' => 'feature_showcase',
        'documents_meta'        => 'zig_product_document',
        'specifications_meta'   => 'technical_specifications',
        'key_benefits_meta'     => 'product_key_benefits',
        'video_meta'            => 'zig-product-video',
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

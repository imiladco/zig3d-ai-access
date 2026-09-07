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
     * هویتِ سایتِ تولید — پایهٔ ‎Support\Url‎.
     *
     * چرا این‌جا و نه ‎home_url()‎: رویِ یک نصبِ استیج، ‎home_url()‎ خودش
     * هاستِ استیج را می‌دهد، پس اگر تنها معیارِ کانونیکال‌بودن
     * ‎home_url()‎ باشد، URLهایِ استیج هم کانونیکال به‌نظر می‌رسند و در
     * ‎llms.txt‎/IndexNow منتشر می‌شوند. با نوشتنِ صریحِ هاستِ تولید
     * این‌جا، یک کپیِ استیج خودبه‌خود *ساکت* می‌شود: هیچ URLی از دروازه
     * رد نمی‌شود و هیچ چیزی push نمی‌شود.
     */
    'site' => [
        'production_url' => 'https://zig3d.com/',
    ],

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
     * ‎/llms.txt‎ — نقشهٔ منتخبِ سایت برایِ مدل‌های زبانی.
     *
     * قاعدهٔ سردبیریِ این فایل: **منتخب، نه کاتالوگ.** فقط هاب‌هایِ
     * سطحِ بالا و مهم‌ترین دسته‌ها. تک‌تکِ محصولات/مقاله‌ها عمداً نمی‌آیند
     * — آن کارِ sitemap است (که مالِ Rank Math می‌ماند) و هزاران لینک
     * دقیقاً همان چیزی است که این فایل قرار بود جایگزینش شود.
     *
     * سقفِ هر بخش (‎limit‎) همین را تضمین می‌کند: اگر فردا دسته‌ها سه
     * برابر شوند، فایل بزرگ‌تر نمی‌شود.
     */
    'llms' => [
        'enabled' => true,
        'path'    => 'llms.txt',

        /* اسپکِ llmstxt.org نوعِ محتوا را الزام نکرده؛ این همان چیزی است که خودِ مرجع سرو می‌کند */
        'content_type' => 'text/plain; charset=utf-8',

        /*
         * ‎<link rel="describedby">‎ در ‎<head>‎ — تنها ‎rel‎ی که اسپک
         * برایِ کشفِ این فایل تعریف کرده. ‎alternate/text/markdown‎ی
         * اسپک مالِ نسخهٔ مارک‌داونیِ *صفحه* است که این سایت ندارد، پس
         * چاپ نمی‌شود.
         */
        'discovery_link' => true,

        'title'   => 'گروه زیگ (ZIG3D)',
        'summary' => 'تأمین‌کنندهٔ راهکارهای دیجیتالِ دندان‌سازی و دندان‌پزشکی در ایران: اسکنرهای سه‌بعدی، دستگاه‌های میلینگ، پرینترهای سه‌بعدی، مواد مصرفی، و نرم‌افزارهای تخصصی — به‌همراه آموزش و پشتیبانیِ فنی.',

        'notes' => [
            'این سایت به زبانِ فارسی است و قیمت‌ها به ریال ایران اعلام می‌شوند. توضیحِ ساختاریِ هر صفحه به‌صورتِ JSON-LD در خودِ همان صفحه آمده است.',
        ],

        /* ایمیلِ عمومیِ سازمان مجاز است؛ ‎organization.contact_points‎ عمداً نمی‌آید — یکی از آن‌ها ایمیلِ شخصیِ یک کارمند است */
        'include_public_email' => true,

        'sections' => [
            [
                'title'  => 'صفحه‌های اصلی',
                'source' => 'hubs',
                'limit'  => 10,
                'hubs'   => [
                    'home'      => ['label' => 'خانه', 'note' => 'معرفیِ کلیِ مجموعه و دسته‌ها و محصولاتِ منتخب'],
                    'shop'      => ['label' => 'فروشگاه', 'note' => 'همهٔ محصولاتِ سخت‌افزاری و مصرفی'],
                    'downloads' => ['label' => 'دانلودِ نرم‌افزار', 'note' => 'نرم‌افزارهایِ تخصصی، نسخه‌ها، و راهنمایِ نصب'],
                    'blog'      => ['label' => 'بلاگ', 'note' => 'مقاله‌هایِ آموزشی و فنیِ دندان‌سازیِ دیجیتال'],
                ],
            ],
            [
                'title'    => 'دسته‌های محصول',
                'source'   => 'taxonomy',
                'taxonomy' => 'product_cat',
                'limit'    => 20,
                'orderby'  => 'count',
                'order'    => 'DESC',
            ],
            [
                'title'    => 'دسته‌های نرم‌افزار',
                'source'   => 'taxonomy',
                'taxonomy' => 'software-category',
                'limit'    => 15,
                'orderby'  => 'count',
                'order'    => 'DESC',
            ],
            [
                'title'    => 'دسته‌های بلاگ',
                'source'   => 'taxonomy',
                'taxonomy' => 'category',
                'limit'    => 15,
                'orderby'  => 'count',
                'order'    => 'DESC',
            ],
        ],
    ],

    /*
     * سیاستِ خزنده‌هایِ هوشِ مصنوعی در ‎robots.txt‎.
     *
     * ⚠ ‎robots.txt‎ توصیه‌ای است. این فهرست‌ها *اعلامِ سیاست*اند، نه
     * کنترلِ دسترسی. بلاکِ واقعیِ فعلیِ این سایت در سطحِ فایروالِ هاست
     * (Imunify360/ModSecurity رویِ لیموهاست) است که به بعضی UAها ۴۰۳
     * می‌دهد؛ این افزونه آن را حل نمی‌کند. تنها راهِ تأیید یک fetchِ
     * واقعی با همان UA است — ‎wp zig3d doctor‎ همین را می‌گوید.
     *
     * همهٔ توکن‌ها از مستنداتِ رسمیِ خودِ همان شرکت راستی‌آزمایی شده‌اند
     * (توکنِ غلط = قاعده‌ای که بی‌صدا هیچ کاری نمی‌کند).
     */
    'robots' => [
        'enabled' => true,

        /*
         * جستجو/بازیابی — **باز**. این‌ها همان‌هایی‌اند که باعث می‌شوند
         * سایت در پاسخِ دستیارها دیده شود؛ بستنشان یعنی نامرئی‌شدن.
         */
        'search_crawlers' => [
            'OAI-SearchBot',    // OpenAI: جستجو/بازیابی — مستنداتِ bots اوپن‌ای‌آی
            'ChatGPT-User',     // OpenAI: واکشیِ به‌درخواستِ کاربر، نه خزشِ خودکار
            'PerplexityBot',    // Perplexity: نمایه‌سازی برایِ نتایجِ جستجو
            'Perplexity-User',  // Perplexity: واکشیِ به‌درخواستِ کاربر
            'Claude-User',      // Anthropic: واکشیِ به‌درخواستِ کاربر
            'Claude-SearchBot', // Anthropic: نمایه‌سازیِ جستجو
        ],

        /*
         * آموزشِ مدل — **بسته** مگر مدیرِ سایت صریحاً بازش کند.
         * سایت به‌طورِ خودکار به آموزش رضایت نمی‌دهد.
         *
         * ‎Google-Extended‎ و ‎Applebot-Extended‎ خزنده نیستند؛ توکنِ
         * *کنترلِ استفاده*اند (رتبه/حضور در جستجویِ گوگل و اسپاتلایت را
         * عوض نمی‌کنند، فقط استفادهٔ آموزشی را). به همین دلیل بستنشان
         * هزینهٔ دیده‌شدن ندارد.
         */
        'training_crawlers' => [
            'GPTBot',             // OpenAI: آموزش
            'ClaudeBot',          // Anthropic: جمع‌آوریِ محتوا برایِ آموزش
            'Google-Extended',    // Google: کنترلِ استفادهٔ آموزشیِ Gemini، نه خزنده
            'Applebot-Extended',  // Apple: کنترلِ استفادهٔ آموزشی، نه خزنده
            'CCBot',              // Common Crawl: منبعِ رایجِ دیتاستِ آموزشی

            /*
             * ‎meta-externalagent‎ تنها موردِ این فهرست است که خالص
             * نیست: مستنداتِ خودِ متا می‌گوید هم برایِ آموزش و هم برایِ
             * «نمایه‌سازیِ مستقیمِ محتوا» است. یعنی بستنش ممکن است
             * حضور در محصولاتِ متا را هم کم کند. این‌جا بسته است چون
             * پیش‌فرضِ پروژه رضایت‌ندادن به آموزش است، ولی این یک
             * انتخابِ آگاهانه با هزینه است، نه یک بستنِ بی‌هزینه.
             */
            'meta-externalagent',
        ],

        'allow_training' => false,
    ],

    /*
     * کش (‎Support\Cache‎) — یک باسِ واحد با یک ‎invalidate_all()‎.
     *
     * دامنه‌اش عمداً محدود است: خروجی‌هایِ *ساختاریِ* گران و کم‌تغییر،
     * یعنی ‎llms.txt‎. گرافِ schema اصلاً از این باس استفاده نمی‌کند و
     * هر درخواست تازه ساخته می‌شود — نه به‌خاطرِ فراموشی، بلکه چون
     * صفحاتِ محصول قیمت دارند و قیمت ساعتی عوض می‌شود؛ یک قیمتِ کهنه
     * در JSON-LD بدتر از نبودنِ کش است.
     *
     * باطل‌سازی خودکار است (‎save_post‎/‎edited_term‎/…)، پس TTL فقط
     * سقفِ ایمنی است، نه مکانیزمِ اصلیِ تازه‌ماندن.
     */
    'cache' => [
        'enabled' => true,
        /* ۱۲ ساعت — عددِ خام و نه ‎HOUR_IN_SECONDS‎، تا این فایل همچنان بدونِ بوتِ وردپرس خواندنی بماند */
        'ttl'     => 43200,
    ],

];

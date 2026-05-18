<?php
/**
 * config/config.php
 *
 * Один централізований файл налаштувань.
 * Усе, що залежить від оточення (БД, base_url, ліміти),
 * лежить тут. У бойовому проєкті це винесли б у .env, але
 * для WAMP/навчального проєкту такого достатньо.
 *
 * НІКОЛИ не показуй цей файл назовні: він поза public/ і
 * потрапляє в браузер лише через PHP.
 */

return [

    // -------- База даних --------
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'photogallery',
        'user'     => 'root',
        // На WAMP стандартний пароль root зазвичай порожній.
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // -------- Базовий URL --------
    // Якщо у WAMP заходиш як http://localhost/, лиши порожнім.
    // Якщо як http://localhost/photogallery/public/, постав '/photogallery/public'.
    // Найкраще — налаштувати VirtualHost з DocumentRoot=public/, тоді base_url = ''.
    'base_url' => '',

    // -------- Шляхи --------
    // Обчислюються від кореня проєкту (на 1 рівень вище від config/).
    // Робимо це прямо тут, щоб і bootstrap (index.php), і хелпер config(…)
    // отримували ОДНАКОВІ значення — бо обидва завантажують цей файл окремо.
    'paths' => [
        'originals' => dirname(__DIR__) . '/storage/originals',
        'large'     => dirname(__DIR__) . '/public/uploads/large',
        'thumbs'    => dirname(__DIR__) . '/public/uploads/thumbs',
    ],

    // -------- Завантаження фото --------
    'uploads' => [
        // Максимальний розмір файлу у байтах (25 МБ).
        'max_size' => 25 * 1024 * 1024,

        // Дозволені MIME-типи (перевіряємо через finfo, а не з $_FILES).
        'allowed_mime' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],

        // Дозволені розширення (другий рівень захисту).
        'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    // -------- Параметри для ImageService (Етап 9) --------
    'image' => [
        // Довша сторона у пікселях
        'large_max' => 2400,
        'thumb_max' => 500,

        // Якість WebP (0..100)
        'webp_quality_large' => 85,
        'webp_quality_thumb' => 78,

        // Якість JPEG (fallback, якщо WebP недоступний)
        'jpeg_quality' => 88,
    ],

    // -------- Сесія --------
    'session' => [
        'name'     => 'PGSESSID',
        // На локалці HTTP, тому secure = false.
        // На бойовому HTTPS обов'язково постав true.
        'secure'   => false,
        'http_only'=> true,
        'samesite' => 'Lax',
    ],

    // -------- Дебаг --------
    // У продакшені постав false: будуть показані лише загальні помилки.
    'debug' => true,
];

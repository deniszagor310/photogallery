<?php
/**
 * app/Helpers/helpers.php
 *
 * Невелика збірка глобальних функцій, які зручно
 * використовувати у view-шаблонах і контролерах.
 *
 * Усі прості, без класів, без неймспейсу.
 */

if (!function_exists('e')) {
    /**
     * Escape для виводу в HTML.
     * Використовуй ЗАВЖДИ, коли друкуєш у шаблоні дані з БД або від користувача.
     *
     *   <h1><?= e($photo['title']) ?></h1>
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    /**
     * Доступ до значень із config/config.php (з накладенням config.local.php).
     * Точково: config('db.host'), config('uploads.max_size').
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require BASE_PATH . '/config/config.php';
            $local = BASE_PATH . '/config/config.local.php';
            if (is_file($local)) {
                $override = require $local;
                if (is_array($override)) {
                    $merge = static function (array $base, array $ov) use (&$merge): array {
                        foreach ($ov as $k => $v) {
                            $base[$k] = (is_array($v) && isset($base[$k]) && is_array($base[$k]))
                                ? $merge($base[$k], $v)
                                : $v;
                        }
                        return $base;
                    };
                    $config = $merge($config, $override);
                }
            }
        }
        $parts = explode('.', $key);
        $value = $config;
        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                return $default;
            }
            $value = $value[$p];
        }
        return $value;
    }
}

if (!function_exists('url')) {
    /**
     * Згенерувати URL з урахуванням base_url.
     * url('/gallery') → '/gallery' (якщо base_url порожній)
     *                 → '/photogallery/public/gallery' (інакше)
     */
    function url(string $path = '/'): string
    {
        $base = rtrim((string)config('base_url', ''), '/');
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        return $base . $path;
    }
}

if (!function_exists('asset')) {
    /**
     * URL до файлів у public/assets/.
     *   asset('css/style.css') → '/assets/css/style.css'
     */
    function asset(string $path): string
    {
        return url('/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('public_url')) {
    /**
     * URL для зображення у public/uploads/.
     * Приймає шлях, як він збережений у БД (відносно кореня проєкту),
     * напр. 'public/uploads/large/abcd.webp', і робить з нього URL.
     */
    function public_url(string $relativePath): string
    {
        // 'public/uploads/large/...' → '/uploads/large/...'
        $clean = preg_replace('#^public/?#', '', $relativePath);
        return url('/' . ltrim($clean ?? '', '/'));
    }
}

if (!function_exists('old')) {
    /**
     * Старе значення поля після невдалої форми (зберігається у сесії).
     */
    function old(string $key, string $default = ''): string
    {
        if (!isset($_SESSION['_old'][$key])) {
            return $default;
        }
        $value = $_SESSION['_old'][$key];
        unset($_SESSION['_old'][$key]);
        return (string)$value;
    }
}

if (!function_exists('flash')) {
    /**
     * Прочитати і одразу очистити флеш-повідомлення.
     */
    function flash(string $key): ?string
    {
        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }
        $value = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}

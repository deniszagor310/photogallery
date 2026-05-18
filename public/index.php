<?php
/**
 * public/index.php
 *
 * Єдина точка входу до застосунку. ВСІ запити, які приходять у Apache,
 * через .htaccess переписуються сюди.
 *
 * Що тут відбувається:
 *  1) Визначаємо BASE_PATH — корінь проєкту (на 1 рівень вище від public/).
 *  2) Завантажуємо config і Helper-функції.
 *  3) Реєструємо простий автозавантажувач для класів з namespace App\...
 *  4) Стартуємо сесію (потрібна буде для Auth/CSRF/flash).
 *  5) Створюємо Router, підвантажуємо routes.php, диспатчимо запит.
 */

declare(strict_types=1);

// 1) Корінь проєкту — батьківська папка для public/
define('BASE_PATH', dirname(__DIR__));

// 2) Конфіг + хелпери
$config = require BASE_PATH . '/config/config.php';

// Локальний override (необов'язковий, не в гіті, у .gitignore).
// Зручно для розробника тримати свої БД-паролі окремо від загальних дефолтів.
$localConfigPath = BASE_PATH . '/config/config.local.php';
if (is_file($localConfigPath)) {
    $local = require $localConfigPath;
    if (is_array($local)) {
        // Рекурсивне злиття: 'db' секція з local замінює відповідні поля.
        $merge = static function (array $base, array $override) use (&$merge): array {
            foreach ($override as $k => $v) {
                $base[$k] = (is_array($v) && isset($base[$k]) && is_array($base[$k]))
                    ? $merge($base[$k], $v)
                    : $v;
            }
            return $base;
        };
        $config = $merge($config, $local);
    }
}

require BASE_PATH . '/app/Helpers/helpers.php';

// Підставляємо в конфіг абсолютні шляхи до папок (зручно потім).
$config['paths']['originals'] = BASE_PATH . '/storage/originals';
$config['paths']['large']     = BASE_PATH . '/public/uploads/large';
$config['paths']['thumbs']    = BASE_PATH . '/public/uploads/thumbs';

// 3) Простий PSR-4 автозавантажувач для App\...
// App\Core\Router      → app/Core/Router.php
// App\Controllers\Home → app/Controllers/Home.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));   // напр. 'Core\Router'
    $relative = str_replace('\\', '/', $relative); //          'Core/Router'
    $file = BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// 4) Помилки: у debug режимі — показуємо; у проді — лише логуємо.
if (!empty($config['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// 5) Сесія
$sess = $config['session'];
session_name($sess['name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (bool)$sess['secure'],
    'httponly' => (bool)$sess['http_only'],
    'samesite' => $sess['samesite'] ?? 'Lax',
]);
session_start();

// 6) Підключення до БД. Обов'язкове.
//    Якщо MySQL не запущений або БД не створено — показуємо акуратну
//    сторінку з підказкою, як це виправити (а не білий екран зі стек-трейсом).
try {
    \App\Core\Database::init($config['db']);
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    $msg = !empty($config['debug']) ? $e->getMessage() : 'Database is unavailable';
    echo "<!doctype html><meta charset='utf-8'><title>DB error</title>";
    echo "<style>body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.5}";
    echo "code{background:#eef;padding:2px 5px;border-radius:3px}.fail{color:#b00020}</style>";
    echo "<h1 class='fail'>Не вдалось підключитись до бази даних</h1>";
    echo "<p>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<ol>";
    echo "<li>Запусти MySQL у WAMP (іконка має стати зеленою).</li>";
    echo "<li>Перевір налаштування у <code>config/config.php → db</code>.</li>";
    echo "<li>Виконай <code>database/schema.sql</code> у phpMyAdmin.</li>";
    echo "</ol>";
    exit;
}

// 7) Роутинг
$router = new \App\Core\Router();
$registerRoutes = require BASE_PATH . '/routes.php';
$registerRoutes($router);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI']    ?? '/';

// Якщо застосунок встановлено в підпапку (config base_url),
// відріжемо її від URI перед матчингом.
$base = rtrim((string)($config['base_url'] ?? ''), '/');
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
    if ($uri === '') {
        $uri = '/';
    }
}

$router->dispatch($method, $uri);

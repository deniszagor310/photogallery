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

// 6) Підключення до БД. Поки що БД може ще не існувати —
//    ми обернемо у try/catch, щоб демо-сторінка все одно відкривалась.
//    Етап 3 зробить це обов'язковим.
try {
    \App\Core\Database::init($config['db']);
} catch (\Throwable $e) {
    if (!empty($config['debug'])) {
        // На етапі розробки бачимо, що саме сталось.
        // На Етапі 3 додамо коректний "MySQL ще не запущений" екран.
    }
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

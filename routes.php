<?php
/**
 * routes.php
 *
 * Усі маршрути проєкту в одному файлі — щоб їх легко переглядати.
 * Файл повертає замикання, яке отримує екземпляр Router і реєструє маршрути.
 *
 * На цьому етапі (Етап 2) тут лише демо-маршрут, що доводить:
 * Router, Controller, .htaccess і index.php працюють разом.
 * Далі (Етап 4) сюди додамо повний список маршрутів з ТЗ.
 */

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\GalleryController;
use App\Controllers\AlbumController;
use App\Controllers\PhotoController;
use App\Controllers\AuthController;
use App\Controllers\DbCheckController;

return function (Router $router): void {
    // ---- Публічна частина ----
    $router->get('/',              [HomeController::class,    'index']);
    $router->get('/gallery',       [GalleryController::class, 'index']);
    $router->get('/albums',        [AlbumController::class,   'index']);
    $router->get('/album/{id}',    [AlbumController::class,   'show']);
    $router->get('/photo/{id}',          [PhotoController::class, 'show']);
    $router->get('/photo/download/{id}', [PhotoController::class, 'download']);

    // ---- Авторизація ----
    $router->get('/login',  [AuthController::class, 'loginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    // logout — лише POST + CSRF, щоб ніхто не зміг повісити <img src="/logout">
    $router->post('/logout', [AuthController::class, 'logout']);
    // /admin/... — додамо на Етапі 7

    // ---- Діагностика (тільки в debug=true) ----
    $router->get('/db-check',      [DbCheckController::class, 'index']);
};

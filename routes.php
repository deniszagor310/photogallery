<?php
/**
 * routes.php
 *
 * Усі маршрути проєкту в одному файлі — щоб їх легко переглядати.
 * Файл повертає замикання, яке отримує екземпляр Router і реєструє маршрути.
 */

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\GalleryController;
use App\Controllers\AlbumController;
use App\Controllers\PhotoController;
use App\Controllers\AuthController;
use App\Controllers\DbCheckController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\AlbumController    as AdminAlbumController;
use App\Controllers\Admin\PhotoController    as AdminPhotoController;
use App\Controllers\Admin\TagController      as AdminTagController;

return function (Router $router): void {
    // ---- Публічна частина ----
    $router->get('/',                     [HomeController::class,    'index']);
    $router->get('/gallery',              [GalleryController::class, 'index']);
    $router->get('/albums',               [AlbumController::class,   'index']);
    $router->get('/album/{id}',           [AlbumController::class,   'show']);
    $router->get('/photo/{id}',           [PhotoController::class,   'show']);
    $router->get('/photo/download/{id}',  [PhotoController::class,   'download']);

    // ---- Авторизація ----
    $router->get('/login',  [AuthController::class, 'loginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    // logout — лише POST + CSRF, щоб ніхто не зміг повісити <img src="/logout">
    $router->post('/logout', [AuthController::class, 'logout']);

    // ---- Адмінка (захист — у конструкторі AbstractAdminController) ----
    // Огляд
    $router->get('/admin',                        [AdminDashboardController::class, 'index']);

    // Альбоми (CRUD)
    $router->get('/admin/albums',                 [AdminAlbumController::class,     'index']);
    $router->get('/admin/albums/new',             [AdminAlbumController::class,     'create']);
    $router->post('/admin/albums',                [AdminAlbumController::class,     'store']);
    $router->get('/admin/albums/{id}/edit',       [AdminAlbumController::class,     'edit']);
    $router->post('/admin/albums/{id}',           [AdminAlbumController::class,     'update']);
    $router->post('/admin/albums/{id}/delete',    [AdminAlbumController::class,     'destroy']);

    // Фото (без аплоаду — це Етап 8)
    $router->get('/admin/photos',                 [AdminPhotoController::class,     'index']);
    $router->get('/admin/photos/{id}/edit',       [AdminPhotoController::class,     'edit']);
    $router->post('/admin/photos/{id}',           [AdminPhotoController::class,     'update']);
    $router->post('/admin/photos/{id}/delete',    [AdminPhotoController::class,     'destroy']);

    // Теги
    $router->get('/admin/tags',                   [AdminTagController::class,       'index']);
    $router->post('/admin/tags',                  [AdminTagController::class,       'store']);
    $router->post('/admin/tags/{id}/delete',      [AdminTagController::class,       'destroy']);

    // ---- Діагностика (тільки в debug=true) ----
    $router->get('/db-check', [DbCheckController::class, 'index']);
};

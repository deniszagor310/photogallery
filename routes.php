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

return function (Router $router): void {
    // Перевірочний маршрут.
    // Запит до '/' має відкрити "Привіт, MVC" від HomeController.
    $router->get('/', [HomeController::class, 'index']);
};

<?php
namespace App\Controllers;

use App\Core\Controller;

/**
 * app/Controllers/HomeController.php
 *
 * Тимчасовий стартовий контролер для Етапу 2.
 * На Етапі 4 ми перепишемо index() так, щоб він
 * показував реальну головну сторінку з останніми фото.
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        // Поки ще немає шаблонів і БД — просто текст,
        // щоб переконатись: маршрут спрацював, контролер викликався.
        header('Content-Type: text/html; charset=utf-8');
        echo "<!doctype html><meta charset='utf-8'>";
        echo "<title>Photo Gallery</title>";
        echo "<h1>Привіт, MVC 👋</h1>";
        echo "<p>Базова структура працює. ";
        echo "Наступний крок — Етап 3: підключення до MySQL через PDO.</p>";
    }
}

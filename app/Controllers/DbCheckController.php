<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * app/Controllers/DbCheckController.php
 *
 * Утилітарний контролер для діагностики БД у режимі розробки.
 * Маршрут /db-check показує:
 *  - чи живе з'єднання з MySQL,
 *  - версію MySQL,
 *  - які таблиці існують у photogallery,
 *  - скільки рядків у кожній з ключових таблиць.
 *
 * На Етапі 11 (безпека) ми сховаємо цей маршрут так, щоб він
 * відповідав 404, коли debug=false.
 */
final class DbCheckController extends Controller
{
    public function index(): void
    {
        if (!config('debug')) {
            // У продакшені цієї сторінки нібито не існує.
            $this->abort(404, 'Сторінку не знайдено');
        }

        header('Content-Type: text/html; charset=utf-8');
        echo "<!doctype html><meta charset='utf-8'><title>DB check</title>";
        echo "<style>body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.5}";
        echo "code{background:#eef;padding:2px 5px;border-radius:3px}";
        echo ".ok{color:#1b7d2c}.fail{color:#b00020}table{border-collapse:collapse;margin-top:8px}";
        echo "th,td{border:1px solid #ccc;padding:4px 10px;text-align:left}</style>";
        echo "<h1>Photo Gallery — DB check</h1>";

        try {
            $pdo = Database::pdo();
        } catch (\Throwable $e) {
            echo "<p class='fail'><b>Помилка:</b> " . e($e->getMessage()) . "</p>";
            echo "<p>Перевір <code>config/config.php → db</code> і чи запущено MySQL у WAMP.</p>";
            return;
        }

        $version = $pdo->query('SELECT VERSION() AS v')->fetch()['v'] ?? '?';
        echo "<p class='ok'>З'єднання з MySQL працює. Версія: <code>" . e($version) . "</code></p>";

        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        if (!$tables) {
            echo "<p class='fail'>У БД ще немає жодної таблиці. ";
            echo "Виконай <code>database/schema.sql</code> у phpMyAdmin.</p>";
            return;
        }

        echo "<h2>Таблиці</h2><table><tr><th>Таблиця</th><th>Записів</th></tr>";
        $expected = ['users','albums','photos','tags','photo_tags'];
        foreach ($expected as $t) {
            if (!in_array($t, $tables, true)) {
                echo "<tr><td>" . e($t) . "</td><td class='fail'>відсутня</td></tr>";
                continue;
            }
            $count = (int)$pdo->query("SELECT COUNT(*) AS c FROM `{$t}`")->fetch()['c'];
            echo "<tr><td>" . e($t) . "</td><td>{$count}</td></tr>";
        }
        echo "</table>";

        echo "<p style='margin-top:24px'><a href='" . e(url('/')) . "'>← на головну</a></p>";
    }
}

<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Models\Photo;

/**
 * app/Controllers/Admin/DashboardController.php
 *
 *  GET /admin  — оглядова сторінка зі статистикою.
 */
final class DashboardController extends AbstractAdminController
{
    public function index(): void
    {
        $pdo = Database::pdo();

        $stats = [
            'users'   => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'albums'  => (int)$pdo->query('SELECT COUNT(*) FROM albums')->fetchColumn(),
            'photos'  => (int)$pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn(),
            'tags'    => (int)$pdo->query('SELECT COUNT(*) FROM tags')->fetchColumn(),
            // Скільки разів люди скачали оригінали — корисна метрика.
            'downloads' => (int)$pdo->query(
                'SELECT COALESCE(SUM(downloads_count), 0) FROM photos'
            )->fetchColumn(),
        ];

        $latestPhotos = (new Photo())->latest(6);

        $this->renderAdmin('admin/dashboard/index', [
            'title'        => 'Адмін',
            'activeNav'    => 'dashboard',
            'stats'        => $stats,
            'latestPhotos' => $latestPhotos,
        ]);
    }
}

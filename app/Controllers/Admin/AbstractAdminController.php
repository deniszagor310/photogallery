<?php
namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * app/Controllers/Admin/AbstractAdminController.php
 *
 * Базовий клас для будь-якого admin-контролера.
 *
 *  - У конструкторі викликає requireAuth() — гарантовано НЕ можна випадково
 *    забути захистити новий екшн.
 *  - Дефолтний layout — layouts/admin.
 *  - Метод renderAdmin() — обгортка над render() з фіксованим layout.
 */
abstract class AbstractAdminController extends Controller
{
    public function __construct()
    {
        // requireAuth() — НЕ статичний, бо успадковує метод базового контролера.
        // Якщо юзер не залогінений, метод сам зробить redirect на /login
        // і викличе exit (через redirect → header+exit), тому далі не йдемо.
        $this->requireAuth();
    }

    /**
     * Рендер admin-view усередині admin-layout.
     *
     * @param string                $view  Напр.: 'admin/albums/index'
     * @param array<string, mixed>  $data
     */
    protected function renderAdmin(string $view, array $data = []): void
    {
        // Підставимо «активний» пункт меню у layout, якщо не передано:
        // 'dashboard' | 'albums' | 'photos' | 'tags'.
        if (!array_key_exists('activeNav', $data)) {
            $data['activeNav'] = '';
        }
        $this->render($view, $data, 'layouts/admin');
    }
}

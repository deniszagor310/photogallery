<?php
namespace App\Controllers\Admin;

use App\Models\Tag;

/**
 * app/Controllers/Admin/TagController.php
 *
 *  GET  /admin/tags            — список + інлайн-форма створення.
 *  POST /admin/tags            — створити (через findOrCreate, ідемпотентно).
 *  POST /admin/tags/{id}/delete — видалити тег (звʼязки в photo_tags підуть через CASCADE).
 */
final class TagController extends AbstractAdminController
{
    public function index(): void
    {
        $tags = (new Tag())->allWithCounts();
        $this->renderAdmin('admin/tags/index', [
            'title'     => 'Теги',
            'activeNav' => 'tags',
            'tags'      => $tags,
        ]);
    }

    public function store(): void
    {
        $this->requireCsrf();
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash_set('error', 'Назва тега обовʼязкова.');
            $this->redirect(url('/admin/tags'));
        }
        if (mb_strlen($name) > 80) {
            flash_set('error', 'Назва тега ≤ 80 символів.');
            $this->redirect(url('/admin/tags'));
        }

        $tag = (new Tag())->findOrCreate($name);
        flash_set('success', "Тег «{$tag['name']}» готовий.");
        $this->redirect(url('/admin/tags'));
    }

    public function destroy(string $id): void
    {
        $this->requireCsrf();
        $id = (int)$id;
        $tag = (new Tag())->find($id);
        if (!$tag) {
            $this->abort(404, 'Тег не знайдено');
        }
        (new Tag())->delete($id);
        flash_set('success', "Тег «{$tag['name']}» видалено.");
        $this->redirect(url('/admin/tags'));
    }
}

<?php
namespace App\Controllers\Admin;

use App\Models\Album;
use App\Models\Photo;
use App\Services\Slug;

/**
 * app/Controllers/Admin/AlbumController.php
 *
 *  GET  /admin/albums              — список з лічильником фото.
 *  GET  /admin/albums/new          — форма створення.
 *  POST /admin/albums              — створити.
 *  GET  /admin/albums/{id}/edit    — форма редагування.
 *  POST /admin/albums/{id}         — оновити.
 *  POST /admin/albums/{id}/delete  — видалити (фото залишаються, album_id → NULL).
 */
final class AlbumController extends AbstractAdminController
{
    public function index(): void
    {
        $albums = (new Album())->allWithCounts();
        $this->renderAdmin('admin/albums/index', [
            'title'     => 'Альбоми',
            'activeNav' => 'albums',
            'albums'    => $albums,
        ]);
    }

    public function create(): void
    {
        $this->renderAdmin('admin/albums/form', [
            'title'     => 'Новий альбом',
            'activeNav' => 'albums',
            'album'     => null,
            'formAction'=> url('/admin/albums'),
        ]);
    }

    public function store(): void
    {
        $this->requireCsrf();

        $title       = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($title === '' || mb_strlen($title) > 150) {
            flash_set('error', 'Назва обовʼязкова і має бути ≤ 150 символів.');
            $_SESSION['_old']['title']       = $title;
            $_SESSION['_old']['description'] = $description;
            $this->redirect(url('/admin/albums/new'));
        }
        if (mb_strlen($description) > 2000) {
            flash_set('error', 'Опис надто довгий (≤ 2000 символів).');
            $_SESSION['_old']['title']       = $title;
            $_SESSION['_old']['description'] = $description;
            $this->redirect(url('/admin/albums/new'));
        }

        $albumModel = new Album();
        $slug = Slug::uniqueFor($title, fn(string $s): bool => $albumModel->slugExists($s), 160);

        $id = $albumModel->create([
            'title'       => $title,
            'slug'        => $slug,
            'description' => $description !== '' ? $description : null,
        ]);

        flash_set('success', "Альбом «{$title}» створено.");
        $this->redirect(url('/admin/albums/' . $id . '/edit'));
    }

    public function edit(string $id): void
    {
        $id = (int)$id;
        $album = (new Album())->findWithCount($id);
        if (!$album) {
            $this->abort(404, 'Альбом не знайдено');
        }

        // Список фото в альбомі — щоб у dropdown «обкладинка» було з чого вибирати.
        $photosInAlbum = (new Photo())->forAlbum($id, 200);

        $this->renderAdmin('admin/albums/form', [
            'title'         => 'Редагувати: ' . $album['title'],
            'activeNav'     => 'albums',
            'album'         => $album,
            'photosInAlbum' => $photosInAlbum,
            'formAction'    => url('/admin/albums/' . $id),
        ]);
    }

    public function update(string $id): void
    {
        $this->requireCsrf();
        $id = (int)$id;

        $albumModel = new Album();
        $existing = $albumModel->find($id);
        if (!$existing) {
            $this->abort(404, 'Альбом не знайдено');
        }

        $title         = trim((string)($_POST['title'] ?? ''));
        $description   = trim((string)($_POST['description'] ?? ''));
        $coverPhotoId  = (int)($_POST['cover_photo_id'] ?? 0);
        $regenSlug     = !empty($_POST['regen_slug']);

        if ($title === '' || mb_strlen($title) > 150 || mb_strlen($description) > 2000) {
            flash_set('error', 'Перевір довжину назви/опису.');
            $this->redirect(url('/admin/albums/' . $id . '/edit'));
        }

        // Slug: за замовчуванням НЕ чіпаємо (зміна слага = битий URL).
        $slug = (string)$existing['slug'];
        if ($regenSlug) {
            $slug = Slug::uniqueFor($title, fn(string $s): bool => $albumModel->slugExists($s, $id), 160);
        }

        // Обкладинка: дозволяємо лише фото, що належать саме цьому альбому.
        $coverValue = null;
        if ($coverPhotoId > 0) {
            $photo = (new Photo())->find($coverPhotoId);
            if ($photo && (int)$photo['album_id'] === $id) {
                $coverValue = $coverPhotoId;
            }
        }

        $albumModel->update($id, [
            'title'          => $title,
            'slug'           => $slug,
            'description'    => $description !== '' ? $description : null,
            'cover_photo_id' => $coverValue,
        ]);

        flash_set('success', 'Альбом збережено.');
        $this->redirect(url('/admin/albums/' . $id . '/edit'));
    }

    public function destroy(string $id): void
    {
        $this->requireCsrf();
        $id = (int)$id;

        $album = (new Album())->find($id);
        if (!$album) {
            $this->abort(404, 'Альбом не знайдено');
        }

        // У БД ON DELETE SET NULL на album_id у photos → видалення альбому
        // НЕ видаляє фото, лише розвʼязує їх. Це навмисно.
        (new Album())->delete($id);
        flash_set('success', "Альбом «{$album['title']}» видалено.");
        $this->redirect(url('/admin/albums'));
    }
}

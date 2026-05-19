<?php
namespace App\Controllers\Admin;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Tag;
use App\Services\Slug;
use App\Services\Upload;
use App\Services\UploadException;

/**
 * app/Controllers/Admin/PhotoController.php
 *
 *  GET  /admin/photos              — список (пагінований).
 *  GET  /admin/photos/new          — форма аплоаду (Етап 8).
 *  POST /admin/photos              — аплоад одного або кількох фото.
 *  GET  /admin/photos/{id}/edit    — форма редагування метаданих.
 *  POST /admin/photos/{id}         — оновити метадані + теги.
 *  POST /admin/photos/{id}/delete  — видалити фото (Етап 10 розширить — видаляти і файли).
 */
final class PhotoController extends AbstractAdminController
{
    public function index(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $q    = trim((string)($_GET['q'] ?? ''));
        $albumId = isset($_GET['album']) ? (int)$_GET['album'] : null;
        if ($albumId !== null && $albumId < 1) $albumId = null;

        $result = (new Photo())->paginate($page, 24, $albumId, $q !== '' ? $q : null);
        $albums = (new Album())->allWithCounts();

        $this->renderAdmin('admin/photos/index', [
            'title'     => 'Фото',
            'activeNav' => 'photos',
            'result'    => $result,
            'albums'    => $albums,
            'q'         => $q,
            'albumId'   => $albumId,
        ]);
    }

    /**
     * GET /admin/photos/new — форма аплоаду.
     */
    public function create(): void
    {
        $albums = (new Album())->allWithCounts();

        $defaultAlbumId = isset($_GET['album']) ? (int)$_GET['album'] : 0;
        if ($defaultAlbumId > 0 && !(new Album())->find($defaultAlbumId)) {
            $defaultAlbumId = 0;
        }

        $this->renderAdmin('admin/photos/new', [
            'title'         => 'Завантажити фото',
            'activeNav'     => 'photos',
            'albums'        => $albums,
            'defaultAlbum'  => $defaultAlbumId,
            'maxSize'       => (int)config('uploads.max_size', 25 * 1024 * 1024),
            'allowedExt'    => (array)config('uploads.allowed_ext', ['jpg', 'jpeg', 'png', 'webp']),
        ]);
    }

    /**
     * POST /admin/photos — обробка multipart-форми з одним або кількома файлами.
     *
     * Стратегія:
     *   - Усі файли обробляємо у циклі.
     *   - Для КОЖНОГО файлу — окрема BD-транзакція (Upload → INSERT photos → INSERT photo_tags).
     *   - Якщо один файл фейлиться — інші все одно зберігаються, користувач бачить
     *     зведення «N успішно, M помилок».
     *   - При помилці у БД ROLLBACK + видаляємо фізичні файли (Upload::rollback()).
     */
    public function store(): void
    {
        $this->requireCsrf();

        $files = $_FILES['photos'] ?? null;
        if (!is_array($files) || empty($files['name'])) {
            flash_set('error', 'Не вибрано жодного файлу.');
            $this->redirect(url('/admin/photos/new'));
        }

        // Спільні поля з форми (одні для всієї пачки).
        $albumId = (int)($_POST['album_id'] ?? 0);
        $albumValue = null;
        if ($albumId > 0 && (new Album())->find($albumId)) {
            $albumValue = $albumId;
        }

        $sharedDescription = trim((string)($_POST['description'] ?? ''));
        if (mb_strlen($sharedDescription) > 4000) {
            $sharedDescription = mb_substr($sharedDescription, 0, 4000);
        }
        $sharedTagsRaw = trim((string)($_POST['tags'] ?? ''));
        $sharedTags = $sharedTagsRaw === ''
            ? []
            : array_map('trim', explode(',', $sharedTagsRaw));

        $upload     = new Upload();
        $photoModel = new Photo();
        $tagModel   = new Tag();
        $pdo        = \App\Core\Database::pdo();

        $okCount = 0;
        $errors  = [];

        $normalized = Upload::normalizeMultiple($files);
        if (!$normalized) {
            flash_set('error', 'Не вибрано жодного файлу.');
            $this->redirect(url('/admin/photos/new'));
        }

        foreach ($normalized as $file) {
            $saved = null;
            try {
                // 1) Валідація + збереження файлу на диск.
                $saved = $upload->handle($file);

                // 2) Заголовок з оригінального імені (без розширення).
                $title = pathinfo($saved['original_filename'], PATHINFO_FILENAME);
                $title = trim((string)$title);
                if ($title === '') {
                    $title = 'Фото';
                }
                if (mb_strlen($title) > 200) {
                    $title = mb_substr($title, 0, 200);
                }

                // 3) Унікальний slug.
                $slug = Slug::uniqueFor(
                    $title,
                    static fn(string $s): bool => $photoModel->slugExists($s),
                    220
                );

                // 4) Транзакція БД.
                $pdo->beginTransaction();
                $photoId = $photoModel->create([
                    'album_id'          => $albumValue,
                    'title'             => $title,
                    'slug'              => $slug,
                    'description'       => $sharedDescription !== '' ? $sharedDescription : null,
                    'original_path'     => $saved['original_path'],
                    'large_path'        => $saved['large_path'],
                    'thumb_path'        => $saved['thumb_path'],
                    'original_filename' => $saved['original_filename'],
                    'stored_filename'   => $saved['stored_filename'],
                    'original_size'     => $saved['size'],
                    'mime_type'         => $saved['mime_type'],
                    'width'             => $saved['width'],
                    'height'            => $saved['height'],
                ]);

                if ($sharedTags) {
                    $tagModel->syncForPhoto($photoId, $sharedTags);
                }

                $pdo->commit();
                $okCount++;
            } catch (UploadException $e) {
                // Файл не зберігся — БД ще навіть не чіпали.
                $errors[] = ($file['name'] ?? '(файл)') . ': ' . $e->getMessage();
            } catch (\Throwable $e) {
                // БД-помилка вже після того, як файл ліг на диск → відкочуємо все.
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($saved !== null) {
                    $upload->rollback($saved);
                }
                $errors[] = ($file['name'] ?? '(файл)') . ': помилка збереження.';
            }
        }

        // Підсумок.
        if ($okCount > 0 && empty($errors)) {
            flash_set('success', "Завантажено: {$okCount}.");
            $this->redirect(url('/admin/photos'));
        }
        if ($okCount > 0 && !empty($errors)) {
            flash_set(
                'success',
                "Завантажено: {$okCount}. Помилки: " . implode('; ', $errors)
            );
            $this->redirect(url('/admin/photos'));
        }
        flash_set('error', 'Жодного фото не завантажено. ' . implode('; ', $errors));
        $this->redirect(url('/admin/photos/new'));
    }

    public function edit(string $id): void
    {
        $id = (int)$id;
        $photo = (new Photo())->findWithDetails($id);
        if (!$photo) {
            $this->abort(404, 'Фото не знайдено');
        }

        $albums = (new Album())->allWithCounts();

        $this->renderAdmin('admin/photos/edit', [
            'title'     => 'Редагувати: ' . $photo['title'],
            'activeNav' => 'photos',
            'photo'     => $photo,
            'albums'    => $albums,
        ]);
    }

    public function update(string $id): void
    {
        $this->requireCsrf();
        $id = (int)$id;

        $photoModel = new Photo();
        $existing = $photoModel->find($id);
        if (!$existing) {
            $this->abort(404, 'Фото не знайдено');
        }

        $title       = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $albumId     = (int)($_POST['album_id'] ?? 0);
        $regenSlug   = !empty($_POST['regen_slug']);
        $tagsRaw     = trim((string)($_POST['tags'] ?? ''));

        // Базова валідація
        if ($title === '' || mb_strlen($title) > 200) {
            flash_set('error', 'Назва обовʼязкова і має бути ≤ 200 символів.');
            $this->redirect(url('/admin/photos/' . $id . '/edit'));
        }
        if (mb_strlen($description) > 4000) {
            flash_set('error', 'Опис надто довгий (≤ 4000 символів).');
            $this->redirect(url('/admin/photos/' . $id . '/edit'));
        }

        // Опціонально: заміна файлу. Робимо ДО оновлення метаданих,
        // щоб не зберігати модифікації, якщо файл не пройшов валідацію.
        $newFile = null;
        $hasNewFile = isset($_FILES['photo']['error']) && (int)$_FILES['photo']['error'] !== \UPLOAD_ERR_NO_FILE;
        if ($hasNewFile) {
            try {
                $newFile = (new Upload())->handle($_FILES['photo']);
            } catch (UploadException $e) {
                flash_set('error', 'Не вдалося замінити файл: ' . $e->getMessage());
                $this->redirect(url('/admin/photos/' . $id . '/edit'));
            }
        }

        // album_id: лише існуючий, інакше NULL.
        $albumValue = null;
        if ($albumId > 0 && (new Album())->find($albumId)) {
            $albumValue = $albumId;
        }

        // Slug: не чіпаємо без явної відмашки.
        $slug = (string)$existing['slug'];
        if ($regenSlug) {
            $slug = Slug::uniqueFor($title, fn(string $s): bool => $photoModel->slugExists($s, $id), 220);
        }

        // EXIF-поля: усі строкові, окрім ISO (int). Дати/час — у форматі 'Y-m-d\TH:i' з форми.
        $iso = (string)($_POST['iso'] ?? '');
        $iso = ($iso !== '' && ctype_digit($iso)) ? (int)$iso : null;

        $takenAt = trim((string)($_POST['taken_at'] ?? ''));
        if ($takenAt !== '') {
            // <input type="datetime-local"> повертає 'YYYY-MM-DDTHH:MM' — конвертнемо у MySQL DATETIME.
            $ts = strtotime($takenAt);
            $takenAt = $ts ? date('Y-m-d H:i:s', $ts) : null;
        } else {
            $takenAt = null;
        }

        // Якщо файл замінювали — оновлюємо також ВСЕ файлове.
        // Транзакція тримається тільки навколо двох DB-операцій
        // (photos.update + photo_tags), а власне unlink старих файлів
        // робиться ПІСЛЯ commit, щоб не зашкодити при rollback.
        $pdo = \App\Core\Database::pdo();
        $pdo->beginTransaction();
        try {
            $photoModel->update($id, [
                'album_id'      => $albumValue,
                'title'         => $title,
                'slug'          => $slug,
                'description'   => $description !== '' ? $description : null,
                'camera_model'  => $this->trimOrNull($_POST['camera_model'] ?? null, 120),
                'lens_model'    => $this->trimOrNull($_POST['lens_model'] ?? null, 120),
                'iso'           => $iso,
                'aperture'      => $this->trimOrNull($_POST['aperture'] ?? null, 20),
                'shutter_speed' => $this->trimOrNull($_POST['shutter_speed'] ?? null, 20),
                'taken_at'      => $takenAt,
            ]);

            if ($newFile !== null) {
                $photoModel->replaceFileFields($id, [
                    'original_path'     => $newFile['original_path'],
                    'large_path'        => $newFile['large_path'],
                    'thumb_path'        => $newFile['thumb_path'],
                    'original_filename' => $newFile['original_filename'],
                    'stored_filename'   => $newFile['stored_filename'],
                    'original_size'     => $newFile['size'],
                    'mime_type'         => $newFile['mime_type'],
                    'width'             => $newFile['width'],
                    'height'            => $newFile['height'],
                ]);
            }

            // Теги: у тій же транзакції, щоб не лишилось «частково оновленого» стану.
            $tagNames = $tagsRaw === '' ? [] : array_map('trim', explode(',', $tagsRaw));
            (new Tag())->syncForPhoto($id, $tagNames);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Якщо ми вже встигли покласти НОВИЙ файл на диск, але БД не оновилась —
            // прибираємо новий файл, щоб не лишити осиротілих файлів.
            if ($newFile !== null) {
                (new Upload())->rollback($newFile);
            }
            flash_set('error', 'Помилка збереження: ' . $e->getMessage());
            $this->redirect(url('/admin/photos/' . $id . '/edit'));
        }

        // Усе збереглось → тільки тепер прибираємо старі файли (якщо була заміна)
        // і чистимо мертві теги.
        if ($newFile !== null) {
            $this->deletePhotoFiles($existing);
        }
        (new Tag())->deleteUnused();

        flash_set('success', $newFile !== null ? 'Файл і метадані оновлено.' : 'Фото збережено.');
        $this->redirect(url('/admin/photos/' . $id . '/edit'));
    }

    public function destroy(string $id): void
    {
        $this->requireCsrf();
        $id = (int)$id;

        $photoModel = new Photo();
        $photo = $photoModel->find($id);
        if (!$photo) {
            $this->abort(404, 'Фото не знайдено');
        }

        // Прибираємо запис у БД в транзакції (ON DELETE CASCADE подбає про photo_tags).
        // Файли видаляємо ПІСЛЯ commit — щоб при rollback бази файли не були вже
        // прибрані «наперед». Саме видалення — через Upload::safeUnlink(),
        // який перевіряє через realpath, що шлях лежить у дозволених теках.
        $pdo = \App\Core\Database::pdo();
        try {
            $pdo->beginTransaction();
            $photoModel->delete($id);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash_set('error', 'Помилка видалення: ' . $e->getMessage());
            $this->redirect(url('/admin/photos'));
        }

        $this->deletePhotoFiles($photo);
        (new Tag())->deleteUnused();

        flash_set('success', "Фото «{$photo['title']}» видалено.");
        $this->redirect(url('/admin/photos'));
    }

    /**
     * Безпечно прибрати 3 фізичних файли фото (з реальних локацій).
     * Повертає кількість фактично видалених файлів.
     */
    private function deletePhotoFiles(array $photo): int
    {
        $allowed = [
            (string)config('paths.originals'),
            (string)config('paths.large'),
            (string)config('paths.thumbs'),
        ];
        $deleted = 0;
        foreach (['original_path', 'large_path', 'thumb_path'] as $k) {
            $p = (string)($photo[$k] ?? '');
            if ($p !== '' && Upload::safeUnlink($p, $allowed)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    private function trimOrNull(mixed $value, int $maxLen): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        if ($value === '') return null;
        if (mb_strlen($value) > $maxLen) $value = mb_substr($value, 0, $maxLen);
        return $value;
    }
}

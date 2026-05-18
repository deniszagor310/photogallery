<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Photo;

/**
 * app/Controllers/PhotoController.php
 *
 *  GET /photo/{id}          — сторінка фото (показуємо ОПТИМІЗОВАНУ large-версію).
 *  GET /photo/download/{id} — скачування ОРИГІНАЛУ зі storage/originals/.
 */
final class PhotoController extends Controller
{
    public function show(string $id): void
    {
        $id = (int)$id;
        if ($id < 1) $this->abort(404, 'Фото не знайдено');

        $photo = (new Photo())->findWithDetails($id);
        if (!$photo) $this->abort(404, 'Фото не знайдено');

        $this->render('photo/show', [
            'title' => $photo['title'],
            'photo' => $photo,
        ]);
    }

    /**
     * Стрімить оригінал з НЕпублічної папки storage/originals/.
     *
     * Захист:
     *  1) ід — лише цифри (це гарантує роутер за регуляркою {id}).
     *  2) Перевіряємо, що рядок у БД існує.
     *  3) Беремо чисте ім'я файлу (basename) і збираємо абсолютний шлях
     *     виключно через config('paths.originals'). Реальне ім'я з БД
     *     ми контролюємо (генеруємо самі на Етапі 8) — але робимо ще
     *     один realpath-чек, щоб виключити path traversal у разі багу.
     *  4) Заголовок Content-Disposition: attachment з юнікод-ім'ям файлу
     *     за RFC 6266 — щоб кириличні назви теж нормально вантажилися.
     *  5) Скидаємо всі вихідні буфери і стрімимо readfile() — щоб великі
     *     RAW-файли (50 МБ+) не з'їли пам'ять PHP.
     */
    public function download(string $id): void
    {
        $id = (int)$id;
        if ($id < 1) $this->abort(404, 'Фото не знайдено');

        $photoModel = new Photo();
        $photo = $photoModel->find($id);
        if (!$photo) $this->abort(404, 'Фото не знайдено');

        // Абсолютна папка з оригіналами (підставляється у index.php).
        $originalsDir = (string)config('paths.originals');
        $realDir = realpath($originalsDir);
        if ($realDir === false) {
            $this->abort(500, 'Папка з оригіналами не існує');
        }

        // Беремо ЛИШЕ ім'я файлу зі шляху в БД, ігноруючи будь-які підпапки.
        $stored = (string)($photo['stored_filename'] ?? '');
        if ($stored === '') {
            // Запасний варіант — витягнути ім'я з original_path.
            $stored = basename((string)($photo['original_path'] ?? ''));
        }
        $stored = basename($stored); // подвійний запобіжник проти ../

        if ($stored === '' || $stored === '.' || $stored === '..') {
            $this->abort(404, 'Файл оригіналу не знайдено');
        }

        $candidate = $realDir . DIRECTORY_SEPARATOR . $stored;
        $real = realpath($candidate);

        if ($real === false || !is_file($real) || !is_readable($real)) {
            $this->abort(404, 'Файл оригіналу не знайдено на диску');
        }
        // Контрольна перевірка: фінальний шлях ОБОВ'ЯЗКОВО всередині $realDir.
        if (!str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            $this->abort(403, 'Доступ заборонено');
        }

        // Інкрементуємо лічильник до старту стріму.
        try {
            $photoModel->incrementDownloads($id);
        } catch (\Throwable $e) {
            // Лічильник не критичний для самого завантаження — продовжуємо.
        }

        // Готуємо ім'я файлу для користувача:
        // ASCII-частина для старих клієнтів + filename* для UTF-8.
        $userFilename = (string)($photo['original_filename'] ?? ('photo-' . $id));
        // Прибираємо керівні символи, подвійні лапки і слеш — заборонено в HTTP-заголовку.
        $asciiSafe = preg_replace('/[\x00-\x1F"\\\\\/]+/u', '_', $userFilename) ?: ('photo-' . $id);
        $utf8Safe  = rawurlencode($userFilename);

        $mime = (string)($photo['mime_type'] ?? 'application/octet-stream');
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
            $mime = 'application/octet-stream';
        }

        // Закриваємо ВСІ open output buffers — readfile() стрімитиме напряму.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        clearstatcache(true, $real);
        $size = filesize($real);

        header('Content-Type: ' . $mime);
        if ($size !== false) {
            header('Content-Length: ' . $size);
        }
        header(
            'Content-Disposition: attachment; '
            . 'filename="' . $asciiSafe . '"; '
            . 'filename*=UTF-8\'\'' . $utf8Safe
        );
        // Кеш — не зберігаємо у проміжних кешах, бо це приватний контент.
        header('Cache-Control: private, no-store');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');

        // На HEAD-запит тіло не потрібне (роутер мапить HEAD на GET, тому перевіряємо явно).
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            return;
        }

        readfile($real);
    }
}

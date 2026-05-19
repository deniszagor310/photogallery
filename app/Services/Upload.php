<?php
namespace App\Services;

/**
 * app/Services/Upload.php
 *
 * Безпечна обробка одного завантаженого фото.
 *
 * Що перевіряємо (у такому порядку):
 *  1) $_FILES error-код — чи не зріс PHP-ліміт upload_max_filesize / post_max_size.
 *  2) is_uploaded_file() — захист від підставлення довільного шляху замість tmp_name.
 *  3) Розмір файлу ≤ config('uploads.max_size').
 *  4) MIME через finfo_file() — НЕ через $_FILES['type'] (клієнт може брехати).
 *  5) MIME у whitelist (image/jpeg|png|webp).
 *  6) Розширення у whitelist (захист другого рівня).
 *  7) getimagesize() підтверджує, що файл — реальне зображення з відповідним MIME.
 *
 * Що робимо після перевірок:
 *  - Генеруємо випадкове ім'я (bin2hex(random_bytes(16))) — користувачеве
 *    оригінальне ім'я НЕ потрапляє на диск.
 *  - move_uploaded_file() у storage/originals/.
 *  - Тимчасово (до Етапу 9 з ImageService) копіюємо оригінал
 *    у public/uploads/large/ і public/uploads/thumbs/, щоб публічна
 *    сторінка не була порожньою. Етап 9 переписуватиме ці копії
 *    на справжні ресайзи через GD.
 *
 * Що НЕ робить цей сервіс:
 *  - Не пише в БД (це робить контролер у транзакції).
 *  - Не зчитує EXIF (це робиться окремим викликом у контролері).
 */
final class Upload
{
    /**
     * Обробити один завантажений файл і повернути готовий до INSERT масив.
     *
     * @param array{name:string, type:string, tmp_name:string, error:int, size:int} $file
     *
     * @return array{
     *   stored_filename: string,
     *   original_filename: string,
     *   mime_type: string,
     *   size: int,
     *   width: int,
     *   height: int,
     *   ext: string,
     *   original_path: string,
     *   large_path: string,
     *   thumb_path: string,
     *   absolute_original: string,
     *   absolute_large: string,
     *   absolute_thumb: string
     * }
     *
     * @throws UploadException якщо файл не пройшов валідацію або не вдалося зберегти
     */
    public function handle(array $file): array
    {
        // ---------- 1. PHP-рівневі помилки upload ----------
        $err = (int)($file['error'] ?? \UPLOAD_ERR_NO_FILE);
        if ($err !== \UPLOAD_ERR_OK) {
            throw new UploadException($this->describeError($err));
        }

        $tmp  = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $clientName = (string)($file['name'] ?? '');

        // ---------- 2. Захист від підставлення довільного шляху ----------
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new UploadException('Файл не пройшов перевірку безпеки.');
        }

        // ---------- 3. Розмір ----------
        $maxSize = (int)config('uploads.max_size', 25 * 1024 * 1024);
        if ($size <= 0) {
            throw new UploadException('Порожній файл.');
        }
        if ($size > $maxSize) {
            $mb = number_format($maxSize / (1024 * 1024), 1, '.', '');
            throw new UploadException("Файл завеликий (максимум {$mb} МБ).");
        }
        // Подвійна перевірка — реальний розмір на диску.
        $realSize = filesize($tmp);
        if ($realSize === false || $realSize <= 0 || $realSize > $maxSize) {
            throw new UploadException('Файл завеликий або порожній.');
        }

        // ---------- 4. MIME через finfo (а не $_FILES['type']) ----------
        if (!class_exists('\\finfo')) {
            throw new UploadException('На сервері недоступне розширення fileinfo.');
        }
        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmp);
        if (!is_string($mime) || $mime === '') {
            throw new UploadException('Не вдалося визначити тип файлу.');
        }

        // ---------- 5. MIME-whitelist ----------
        $allowedMime = (array)config('uploads.allowed_mime', ['image/jpeg', 'image/png', 'image/webp']);
        if (!in_array($mime, $allowedMime, true)) {
            throw new UploadException('Дозволені лише JPEG, PNG або WebP.');
        }

        // ---------- 6. Розширення-whitelist (захист другого рівня) ----------
        // Беремо лише останнє розширення з імені, нормалізуємо у lowercase.
        $clientExt = strtolower((string)pathinfo($clientName, PATHINFO_EXTENSION));
        $allowedExt = (array)config('uploads.allowed_ext', ['jpg', 'jpeg', 'png', 'webp']);

        // Якщо клієнт не передав розширення — беремо канонічне з MIME.
        if ($clientExt === '' || !in_array($clientExt, $allowedExt, true)) {
            // Не блокуємо — просто беремо з MIME, бо MIME ми вже довіряємо.
            $clientExt = $this->extFromMime($mime);
        }
        // Нормалізуємо JPEG → jpg.
        $ext = $clientExt === 'jpeg' ? 'jpg' : $clientExt;

        // ---------- 7. getimagesize() — гарантуємо, що це справді картинка ----------
        // Захист від «PHP-файл, перейменований у .jpg»: finfo_file бачить
        // магічні байти, але getimagesize ще й верифікує структуру і дає w/h.
        $info = @getimagesize($tmp);
        if ($info === false || !is_array($info) || empty($info[0]) || empty($info[1])) {
            throw new UploadException('Файл не є коректним зображенням.');
        }
        $width  = (int)$info[0];
        $height = (int)$info[1];

        // Підтвердження, що MIME з getimagesize не суперечить finfo.
        if (isset($info['mime']) && is_string($info['mime'])
            && $info['mime'] !== '' && $info['mime'] !== $mime) {
            throw new UploadException('Тип файлу неузгоджений.');
        }

        // ---------- 8. Генерація випадкового імені ----------
        $stored = bin2hex(random_bytes(16)) . '.' . $ext; // 32 hex + .ext
        $originalsDir = $this->ensureDir((string)config('paths.originals'));
        $largeDir     = $this->ensureDir((string)config('paths.large'));
        $thumbsDir    = $this->ensureDir((string)config('paths.thumbs'));

        $absOriginal = $originalsDir . DIRECTORY_SEPARATOR . $stored;
        $absLarge    = $largeDir     . DIRECTORY_SEPARATOR . $stored;
        $absThumb    = $thumbsDir    . DIRECTORY_SEPARATOR . $stored;

        // ---------- 9. Збереження ----------
        if (!@move_uploaded_file($tmp, $absOriginal)) {
            throw new UploadException('Не вдалося зберегти файл на диск.');
        }

        // Поки що (до Етапу 9 з реальним resize) — копіюємо байт-у-байт.
        // Це робить публічну сторінку фото робочою одразу після Етапу 8.
        // Етап 9 перепише ці копії на оптимізовані WebP/JPEG.
        if (!@copy($absOriginal, $absLarge) || !@copy($absOriginal, $absThumb)) {
            // Якщо preview-копії не вдались — повертаємо стан назад, щоб
            // у БД не зберігся «частковий» запис.
            @unlink($absOriginal);
            @unlink($absLarge);
            @unlink($absThumb);
            throw new UploadException('Не вдалося підготувати превʼю.');
        }

        return [
            'stored_filename'   => $stored,
            'original_filename' => $this->safeOriginalName($clientName, $ext),
            'mime_type'         => $mime,
            'size'              => (int)$realSize,
            'width'             => $width,
            'height'            => $height,
            'ext'               => $ext,
            // Шляхи відносно кореня проєкту (так лежать у БД).
            'original_path'     => 'storage/originals/'  . $stored,
            'large_path'        => 'public/uploads/large/'  . $stored,
            'thumb_path'        => 'public/uploads/thumbs/' . $stored,
            // Абсолютні — щоб ImageService у Етапі 9 знав, де лежать файли.
            'absolute_original' => $absOriginal,
            'absolute_large'    => $absLarge,
            'absolute_thumb'    => $absThumb,
        ];
    }

    /**
     * Видалити три файли (originals + large + thumb), якщо вони існують.
     * Використовується контролером при rollback БД-транзакції.
     */
    public function rollback(array $paths): void
    {
        foreach (['absolute_original', 'absolute_large', 'absolute_thumb'] as $k) {
            if (!empty($paths[$k]) && is_file($paths[$k])) {
                @unlink($paths[$k]);
            }
        }
    }

    /**
     * Розбити $_FILES['photos'] (multiple) на список «однофайлових» масивів.
     * PHP зберігає multiple file uploads як паралельні масиви, що незручно.
     *
     * @param array $multi  $_FILES['photos']
     * @return array<int, array{name:string, type:string, tmp_name:string, error:int, size:int}>
     */
    public static function normalizeMultiple(array $multi): array
    {
        if (!isset($multi['name'])) {
            return [];
        }
        // Якщо це одиничний input (без []) — повертаємо як один елемент.
        if (!is_array($multi['name'])) {
            return [[
                'name'     => (string)$multi['name'],
                'type'     => (string)($multi['type'] ?? ''),
                'tmp_name' => (string)($multi['tmp_name'] ?? ''),
                'error'    => (int)($multi['error'] ?? \UPLOAD_ERR_NO_FILE),
                'size'     => (int)($multi['size'] ?? 0),
            ]];
        }
        $count = count($multi['name']);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            // Пропускаємо порожні слоти (multiple-форма може дати «пустий» файл).
            $err = (int)($multi['error'][$i] ?? \UPLOAD_ERR_NO_FILE);
            if ($err === \UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => (string)($multi['name'][$i] ?? ''),
                'type'     => (string)($multi['type'][$i] ?? ''),
                'tmp_name' => (string)($multi['tmp_name'][$i] ?? ''),
                'error'    => $err,
                'size'     => (int)($multi['size'][$i] ?? 0),
            ];
        }
        return $out;
    }

    // ---------- helpers ----------

    /**
     * Перевести PHP UPLOAD_ERR_* у дружнє повідомлення українською.
     */
    private function describeError(int $code): string
    {
        return match ($code) {
            \UPLOAD_ERR_INI_SIZE   => 'Файл перевищує ліміт сервера (upload_max_filesize).',
            \UPLOAD_ERR_FORM_SIZE  => 'Файл перевищує ліміт форми.',
            \UPLOAD_ERR_PARTIAL    => 'Файл був завантажений лише частково.',
            \UPLOAD_ERR_NO_FILE    => 'Файл не вибрано.',
            \UPLOAD_ERR_NO_TMP_DIR => 'На сервері відсутня тимчасова тека для аплоаду.',
            \UPLOAD_ERR_CANT_WRITE => 'Не вдалося записати файл на диск.',
            \UPLOAD_ERR_EXTENSION  => 'Завантаження зупинене PHP-розширенням.',
            default                => 'Невідома помилка аплоаду.',
        };
    }

    private function extFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'bin',
        };
    }

    /**
     * Гарантуємо, що тека існує, доступна на запис, і повертаємо її абсолютний шлях.
     */
    private function ensureDir(string $dir): string
    {
        if ($dir === '') {
            throw new UploadException('Не сконфігуровано теку для завантажень.');
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new UploadException('Не вдалося створити теку для збереження.');
        }
        if (!is_writable($dir)) {
            throw new UploadException('Тека для збереження не доступна на запис.');
        }
        return $dir;
    }

    /**
     * Очистити оригінальне ім'я файлу для зберігання у БД.
     * Дозволяємо лише букви/цифри/пробіли/кириличні/.,-_ () — все інше міняємо.
     * Це ім'я попадає у Content-Disposition при скачуванні, тому має бути сумісним.
     */
    private function safeOriginalName(string $name, string $ext): string
    {
        // Беремо лише basename — захист від «../etc/passwd».
        $name = basename($name);
        // Обмежимо довжину.
        if (mb_strlen($name) > 200) {
            $name = mb_substr($name, 0, 200);
        }
        // Заміна керівних символів і слешів.
        $name = preg_replace('/[\\\\\\/\\x00-\\x1F\\x7F]+/u', '_', $name) ?? '';
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            // Якщо ім'я повністю не зрозуміле — назвемо за розширенням.
            $name = 'photo.' . $ext;
        }
        return $name;
    }
}

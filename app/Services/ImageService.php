<?php
namespace App\Services;

/**
 * app/Services/ImageService.php
 *
 * Генерація оптимізованих копій («large» і «thumb») з вихідного зображення
 * за допомогою розширення GD.
 *
 * Чому GD, а не Imagick?
 *  - GD є в стандартній збірці PHP на WAMP/Ubuntu/Docker — він майже завжди наявний.
 *  - Imagick потужніший, але вимагає окремої установки.
 *  - У навчальному проєкті простіше тримати один бекенд без розгалужень.
 *
 * Чого ми хочемо досягти:
 *  - large: довша сторона ≤ config('image.large_max') (за замовч. 2400px).
 *    Якщо оригінал менший — НЕ збільшуємо (це лише зіпсує якість),
 *    просто перезжимаємо у відповідний формат.
 *  - thumb: довша сторона ≤ config('image.thumb_max') (за замовч. 500px).
 *
 * Зберігаємо формат:
 *  - JPEG → JPEG (з конфігурованою якістю; для thumb трохи нижчою).
 *  - PNG  → PNG (без потери, але з нашим resampler-ом).
 *  - WebP → WebP.
 *
 *  Це навмисно: PNG може мати прозорий фон, який втратиться при конверсії
 *  у JPEG; WebP менший за обидва, але вимагає його ж і на виході. Конверсія
 *  «все у WebP» — ідея на майбутнє, але вимагає підтримки браузерами і
 *  fallback-логіки (<picture>). Тримаймо MVP простим.
 *
 * Альфа-канал:
 *  - Для PNG/WebP робимо imagealphablending(false) + imagesavealpha(true).
 *  - Для нового полотна заливаємо повністю прозорим, щоб ресайз не дав
 *    чорну рамку навколо прозорих країв.
 *
 * Що НЕ робить цей сервіс:
 *  - НЕ кропає, НЕ повертає, НЕ читає EXIF orientation (це окрема історія —
 *    у Етапі 10 можна додати auto-rotate за EXIF).
 *  - НЕ перевіряє ще раз MIME / розмір файлу — це робить Upload до виклику.
 */
final class ImageService
{
    /**
     * Створити «large»-копію з оригіналу.
     * Якщо оригінал менший за large_max — копіюємо без зменшення,
     * але все одно перезжимаємо у потрібному форматі.
     *
     * @throws \RuntimeException якщо зчитати/записати не вдалося
     */
    public function makeLarge(string $absSrc, string $absDst, string $mime): void
    {
        $maxSide = (int)config('image.large_max', 2400);
        $quality = [
            'image/jpeg' => (int)config('image.jpeg_quality', 88),
            'image/png'  => 6,   // 0..9, де 9 — максимальне стиснення; 6 — баланс
            'image/webp' => (int)config('image.webp_quality_large', 85),
        ];
        $this->resize($absSrc, $absDst, $mime, $maxSide, $quality);
    }

    /**
     * Створити «thumb»-копію з оригіналу.
     * Якість і формат — як для large, тільки розмір менший і якість трохи нижча.
     */
    public function makeThumb(string $absSrc, string $absDst, string $mime): void
    {
        $maxSide = (int)config('image.thumb_max', 500);
        $quality = [
            'image/jpeg' => max(60, (int)config('image.jpeg_quality', 88) - 8),
            'image/png'  => 6,
            'image/webp' => (int)config('image.webp_quality_thumb', 78),
        ];
        $this->resize($absSrc, $absDst, $mime, $maxSide, $quality);
    }

    /**
     * Загальний ресайз: створює нове полотно потрібного розміру,
     * якісно ресемплить вихідне, зберігає у відповідному форматі.
     *
     * @param array<string,int> $quality   мапа MIME → числова якість/рівень стиснення
     */
    private function resize(string $absSrc, string $absDst, string $mime, int $maxSide, array $quality): void
    {
        if (!is_file($absSrc) || !is_readable($absSrc)) {
            throw new \RuntimeException('Source image not readable');
        }
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('GD extension is not loaded');
        }

        // 1. Зчитуємо вихідне зображення.
        $src = $this->loadImage($absSrc, $mime);
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);
            throw new \RuntimeException('Invalid source dimensions');
        }

        // 2. Розраховуємо нові розміри (зберігаючи пропорції).
        [$dstW, $dstH] = $this->fitInside($srcW, $srcH, $maxSide);

        // 3. Створюємо полотно. Для PNG/WebP турбуємось про альфа-канал.
        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            imagedestroy($src);
            throw new \RuntimeException('Failed to create destination canvas');
        }

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
        }

        // 4. Ресемпл (imagecopyresampled — це bicubic-варіант від GD).
        if (!imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH)) {
            imagedestroy($src);
            imagedestroy($dst);
            throw new \RuntimeException('Failed to resample image');
        }

        // 5. Зберігаємо у тому ж форматі, що й оригінал.
        $this->saveImage($dst, $absDst, $mime, $quality[$mime] ?? 85);

        imagedestroy($src);
        imagedestroy($dst);
    }

    /**
     * Порахувати розміри так, щоб довша сторона не перевищувала $maxSide,
     * пропорції збереглись, мінімум 1 піксель з кожного боку.
     * Не збільшуємо, якщо оригінал менший — це лише марнотратство.
     *
     * @return array{0:int, 1:int}  [width, height]
     */
    private function fitInside(int $srcW, int $srcH, int $maxSide): array
    {
        $longer = max($srcW, $srcH);
        if ($longer <= $maxSide) {
            return [$srcW, $srcH];
        }
        $ratio = $maxSide / $longer;
        $w = max(1, (int)round($srcW * $ratio));
        $h = max(1, (int)round($srcH * $ratio));
        return [$w, $h];
    }

    /**
     * Зчитати зображення відповідно до MIME у ресурс GD.
     */
    private function loadImage(string $absSrc, string $mime)
    {
        $img = false;
        switch ($mime) {
            case 'image/jpeg':
                $img = @imagecreatefromjpeg($absSrc);
                break;
            case 'image/png':
                $img = @imagecreatefrompng($absSrc);
                break;
            case 'image/webp':
                if (!function_exists('imagecreatefromwebp')) {
                    throw new \RuntimeException('GD built without WebP support');
                }
                $img = @imagecreatefromwebp($absSrc);
                break;
            default:
                throw new \RuntimeException('Unsupported MIME type: ' . $mime);
        }
        if ($img === false) {
            throw new \RuntimeException('Failed to decode source image');
        }
        return $img;
    }

    /**
     * Записати GD-ресурс у файл відповідно до MIME.
     */
    private function saveImage($img, string $absDst, string $mime, int $quality): void
    {
        $dir = dirname($absDst);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Failed to create destination directory');
        }

        $ok = false;
        switch ($mime) {
            case 'image/jpeg':
                // Якість для JPEG: 0..100.
                $ok = imagejpeg($img, $absDst, max(0, min(100, $quality)));
                break;
            case 'image/png':
                // Рівень стиснення для PNG: 0..9.
                $ok = imagepng($img, $absDst, max(0, min(9, $quality)));
                break;
            case 'image/webp':
                if (!function_exists('imagewebp')) {
                    throw new \RuntimeException('GD built without WebP support');
                }
                $ok = imagewebp($img, $absDst, max(0, min(100, $quality)));
                break;
        }

        if (!$ok || !is_file($absDst)) {
            throw new \RuntimeException('Failed to write destination image');
        }
    }
}

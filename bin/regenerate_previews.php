#!/usr/bin/env php
<?php
/**
 * bin/regenerate_previews.php
 *
 * CLI-утиліта: перегенерувати «large» і «thumb»-копії для всіх фото у БД.
 *
 * Навіщо це:
 *  - У Етапі 8 large/thumb були байт-копіями оригіналу — тобто, по суті,
 *    дублікатами розміру 5-20 МБ замість оптимізованих превʼю.
 *  - У Етапі 9 ми перейшли на справжній resize через GD. Існуючі фото
 *    треба «оновити», щоб теж скористатися оптимізацією.
 *
 * Використання:
 *   php bin/regenerate_previews.php           # всі фото
 *   php bin/regenerate_previews.php --force   # навіть якщо large/thumb уже існують
 *   php bin/regenerate_previews.php --only=15 # лише photo_id = 15
 *
 * Що НЕ робить:
 *  - НЕ переписує оригінал.
 *  - НЕ змінює запис у БД (шляхи й розмір залишаються старі).
 *  - НЕ видаляє фото, для яких немає оригіналу — лише пропускає з warning.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Цей скрипт треба запускати з командного рядка.\n");
    exit(1);
}

// --------- Парсимо аргументи ---------
$force = false;
$onlyId = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--force') {
        $force = true;
    } elseif (strncmp($arg, '--only=', 7) === 0) {
        $onlyId = (int)substr($arg, 7);
        if ($onlyId <= 0) {
            fwrite(STDERR, "Невалідний --only=ID\n");
            exit(1);
        }
    } else {
        fwrite(STDERR, "Невідомий аргумент: $arg\n");
        fwrite(STDERR, "Використання: php bin/regenerate_previews.php [--force] [--only=ID]\n");
        exit(1);
    }
}

// --------- Bootstrap (як у public/index.php, без HTTP-частини) ---------
$root = dirname(__DIR__);
define('BASE_PATH', $root);

// Простий PSR-4 автозавантажувач — повторюємо із index.php.
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $relative = str_replace('\\', '/', $relative);
    $file = BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require $root . '/app/Helpers/helpers.php';

$config = require $root . '/config/config.php';
$localCfg = $root . '/config/config.local.php';
if (file_exists($localCfg)) {
    $local = require $localCfg;
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}
// Хелпер config() читає з $GLOBALS['config'].
$GLOBALS['config'] = $config;

try {
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::pdo();
} catch (\Throwable $e) {
    fwrite(STDERR, "Не вдалося підключитись до БД: " . $e->getMessage() . "\n");
    exit(2);
}

// --------- Знаходимо фото ---------
if ($onlyId !== null) {
    $stmt = $pdo->prepare('SELECT id, stored_filename, mime_type, original_path, large_path, thumb_path FROM photos WHERE id = ?');
    $stmt->execute([$onlyId]);
} else {
    $stmt = $pdo->query('SELECT id, stored_filename, mime_type, original_path, large_path, thumb_path FROM photos ORDER BY id');
}
$photos = $stmt->fetchAll();
$total = count($photos);

if ($total === 0) {
    echo "Жодного фото не знайдено в БД.\n";
    exit(0);
}

echo "Знайдено фото: {$total}\n";
echo "Режим: " . ($force ? 'force (переписуємо все)' : 'incremental (пропускаємо вже згенеровані)') . "\n";
echo str_repeat('-', 60) . "\n";

$images = new \App\Services\ImageService();
$ok = 0;
$skipped = 0;
$failed = 0;

foreach ($photos as $p) {
    $id    = (int)$p['id'];
    $mime  = (string)$p['mime_type'];
    $absOrig  = $root . '/' . ltrim((string)$p['original_path'], '/');
    $absLarge = $root . '/' . ltrim((string)$p['large_path'], '/');
    $absThumb = $root . '/' . ltrim((string)$p['thumb_path'], '/');

    if (!is_file($absOrig)) {
        echo "[#{$id}] WARN: оригінал не знайдено: " . $p['original_path'] . "\n";
        $failed++;
        continue;
    }

    // Якщо обидва превʼю вже на місці і не --force — пропускаємо.
    // Перевіряємо також, що файл не байт-копія оригіналу (за розміром).
    if (!$force) {
        $origSize = (int)filesize($absOrig);
        $largeSize = is_file($absLarge) ? (int)filesize($absLarge) : 0;
        $thumbSize = is_file($absThumb) ? (int)filesize($absThumb) : 0;
        // Якщо обидва превʼю помітно МЕНШІ за оригінал — вважаємо їх вже згенерованими.
        if ($largeSize > 0 && $thumbSize > 0
            && $largeSize < $origSize && $thumbSize < $largeSize) {
            echo "[#{$id}] skip (large={$largeSize}b, thumb={$thumbSize}b)\n";
            $skipped++;
            continue;
        }
    }

    try {
        $images->makeLarge($absOrig, $absLarge, $mime);
        $images->makeThumb($absOrig, $absThumb, $mime);
        $origSize  = (int)filesize($absOrig);
        $largeSize = (int)filesize($absLarge);
        $thumbSize = (int)filesize($absThumb);
        echo sprintf(
            "[#%d] OK  orig=%s, large=%s, thumb=%s  (%s)\n",
            $id,
            humanSize($origSize),
            humanSize($largeSize),
            humanSize($thumbSize),
            $mime
        );
        $ok++;
    } catch (\Throwable $e) {
        echo "[#{$id}] FAIL: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo str_repeat('-', 60) . "\n";
echo "Готово. OK: {$ok}, пропущено: {$skipped}, помилок: {$failed}\n";
exit($failed > 0 ? 1 : 0);

function humanSize(int $bytes): string
{
    if ($bytes < 1024)        return $bytes . ' B';
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / (1024 * 1024), 2) . ' MB';
}

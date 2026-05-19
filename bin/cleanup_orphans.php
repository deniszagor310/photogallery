#!/usr/bin/env php
<?php
/**
 * bin/cleanup_orphans.php
 *
 * CLI-утиліта: знаходить файли у `storage/originals/`, `public/uploads/large/`
 * і `public/uploads/thumbs/`, для яких НЕМАЄ відповідного запису у photos.stored_filename.
 *
 * Навіщо це:
 *  - Якщо щось пішло не так на півдорозі завантаження (наприклад, аварія сервера
 *    між move_uploaded_file() і commit()), файл міг лишитися на диску, а запису
 *    в БД нема — це і є orphan.
 *  - Також, якщо хтось вручну видалив рядок з photos через mysql/phpMyAdmin,
 *    без виклику адмінського DELETE, файли теж стануть orphan.
 *
 * Використання:
 *   php bin/cleanup_orphans.php          # dry-run: лише ПОКАЗАТИ orphan-файли
 *   php bin/cleanup_orphans.php --apply  # реально видалити orphan-файли
 *
 * Безпека:
 *  - Скрипт читає каталоги через scandir, але ВИДАЛЯЄ через Upload::safeUnlink(),
 *    тобто з realpath-перевіркою, що шлях справді лежить у дозволеній теці.
 *  - dry-run за замовчуванням — щоб ти бачив, що буде видалено, перш ніж робити.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Цей скрипт треба запускати з командного рядка.\n");
    exit(1);
}

$apply = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "Невідомий аргумент: $arg\n");
        fwrite(STDERR, "Використання: php bin/cleanup_orphans.php [--apply]\n");
        exit(1);
    }
}

// --------- Bootstrap (як у regenerate_previews.php) ---------
$root = dirname(__DIR__);
define('BASE_PATH', $root);

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
$GLOBALS['config'] = $config;

try {
    \App\Core\Database::init($config['db']);
    $pdo = \App\Core\Database::pdo();
} catch (\Throwable $e) {
    fwrite(STDERR, "Не вдалося підключитись до БД: " . $e->getMessage() . "\n");
    exit(2);
}

// --------- Збираємо «відомі» stored_filename з БД ---------
$known = [];
$stmt = $pdo->query('SELECT stored_filename FROM photos WHERE stored_filename IS NOT NULL');
foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $name) {
    $known[(string)$name] = true;
}
echo "У БД відомих файлів: " . count($known) . "\n";
echo "Режим: " . ($apply ? 'apply (буду видаляти)' : 'dry-run (тільки показую)') . "\n";
echo str_repeat('-', 60) . "\n";

// --------- Обходимо 3 дозволені теки ---------
$dirs = [
    'originals' => (string)config('paths.originals'),
    'large'     => (string)config('paths.large'),
    'thumbs'    => (string)config('paths.thumbs'),
];
$allowedRealDirs = array_values($dirs);

$totalOrphans = 0;
$deleted = 0;

foreach ($dirs as $label => $absDir) {
    echo "== {$label}: {$absDir} ==\n";
    if (!is_dir($absDir)) {
        echo "  (теки нема, пропускаємо)\n";
        continue;
    }
    $entries = @scandir($absDir);
    if ($entries === false) {
        echo "  (не вдалось прочитати теку)\n";
        continue;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.htaccess' || $entry === '.gitkeep') {
            continue;
        }
        $full = $absDir . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($full)) {
            continue;
        }
        if (isset($known[$entry])) {
            continue; // у БД є запис із таким stored_filename → НЕ orphan.
        }
        $totalOrphans++;
        $size = (int)filesize($full);
        echo sprintf("  ORPHAN  %s  (%s)\n", $entry, humanSize($size));

        if ($apply) {
            if (\App\Services\Upload::safeUnlink($full, $allowedRealDirs)) {
                $deleted++;
                echo "  - видалено\n";
            } else {
                echo "  - НЕ видалено (realpath-перевірка не пропустила або файл уже зник)\n";
            }
        }
    }
}

echo str_repeat('-', 60) . "\n";
echo "Знайдено orphans: {$totalOrphans}\n";
if ($apply) {
    echo "Видалено: {$deleted}\n";
} else {
    echo "Це був dry-run. Запусти з --apply, щоб видалити.\n";
}

function humanSize(int $bytes): string
{
    if ($bytes < 1024)        return $bytes . ' B';
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / (1024 * 1024), 2) . ' MB';
}

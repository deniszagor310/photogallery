<?php
/**
 * app/Views/layouts/public.php
 *
 * Загальний layout для відвідувача сайту.
 *
 * Очікувані змінні (з контролера):
 *   $title    — для <title>
 *   $content  — згенерований view (підставляє Controller::render())
 */
?><!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Photo Gallery') ?></title>
    <meta name="theme-color" content="#0f1115">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="brand-dot"></span>
            Photo Gallery
        </a>

        <nav class="site-nav" aria-label="Головна навігація">
            <a href="<?= e(url('/')) ?>">Головна</a>
            <a href="<?= e(url('/gallery')) ?>">Галерея</a>
            <a href="<?= e(url('/albums')) ?>">Альбоми</a>
            <a href="<?= e(url('/login')) ?>" class="nav-login">Вхід</a>
        </nav>
    </div>
</header>

<?php if ($flash = flash('success')): ?>
    <div class="container"><div class="flash flash-success"><?= e($flash) ?></div></div>
<?php endif; ?>
<?php if ($flash = flash('error')): ?>
    <div class="container"><div class="flash flash-error"><?= e($flash) ?></div></div>
<?php endif; ?>

<main class="site-main">
    <?= $content ?? '' ?>
</main>

<footer class="site-footer">
    <div class="container">
        <small>© <?= date('Y') ?> Photo Gallery · навчальний проєкт</small>
    </div>
</footer>

<script src="<?= e(asset('js/main.js')) ?>" defer></script>
</body>
</html>

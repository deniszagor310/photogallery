<?php
/**
 * app/Views/layouts/admin.php
 *
 *  - Бокова навігація з 4-х пунктів.
 *  - $activeNav підсвічує поточний.
 *  - Flash success / error виводяться вгорі.
 *  - Кнопка «На сайт» і кнопка logout (POST + CSRF).
 */
$activeNav = $activeNav ?? '';
$currentUser = auth()->user();
?><!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Адмін') ?> — Photo Gallery</title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="admin-body">

<div class="admin-shell">

    <aside class="admin-side">
        <div class="admin-brand">
            <span class="brand-dot"></span>
            <span>Адмінка</span>
        </div>

        <nav class="admin-nav" aria-label="Адмін-навігація">
            <a href="<?= e(url('/admin')) ?>"        class="<?= $activeNav === 'dashboard' ? 'is-active' : '' ?>">Огляд</a>
            <a href="<?= e(url('/admin/albums')) ?>" class="<?= $activeNav === 'albums'    ? 'is-active' : '' ?>">Альбоми</a>
            <a href="<?= e(url('/admin/photos')) ?>" class="<?= $activeNav === 'photos'    ? 'is-active' : '' ?>">Фото</a>
            <a href="<?= e(url('/admin/tags')) ?>"   class="<?= $activeNav === 'tags'      ? 'is-active' : '' ?>">Теги</a>
        </nav>

        <div class="admin-side-footer">
            <div class="admin-user">
                <small>Залогінено:</small>
                <strong><?= e($currentUser['username'] ?? 'admin') ?></strong>
            </div>
            <a class="btn btn-link" href="<?= e(url('/')) ?>">↗ На сайт</a>
            <form action="<?= e(url('/logout')) ?>" method="post" class="admin-logout-form">
                <?= csrf_input() ?>
                <button type="submit" class="btn btn-block btn-ghost">Вийти</button>
            </form>
        </div>
    </aside>

    <main class="admin-main">
        <?php if ($flash = flash('success')): ?>
            <div class="flash flash-success"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($flash = flash('error')): ?>
            <div class="flash flash-error"><?= e($flash) ?></div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </main>
</div>

</body>
</html>

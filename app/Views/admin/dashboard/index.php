<?php /** @var array $stats; @var array $latestPhotos */ ?>
<header class="admin-page-header">
    <h1>Огляд</h1>
    <p class="admin-muted">Швидка панель з основною статистикою.</p>
</header>

<section class="stats-grid">
    <a class="stat-card" href="<?= e(url('/admin/albums')) ?>">
        <div class="stat-num"><?= (int)$stats['albums'] ?></div>
        <div class="stat-label">альбомів</div>
    </a>
    <a class="stat-card" href="<?= e(url('/admin/photos')) ?>">
        <div class="stat-num"><?= (int)$stats['photos'] ?></div>
        <div class="stat-label">фото</div>
    </a>
    <a class="stat-card" href="<?= e(url('/admin/tags')) ?>">
        <div class="stat-num"><?= (int)$stats['tags'] ?></div>
        <div class="stat-label">тегів</div>
    </a>
    <div class="stat-card">
        <div class="stat-num"><?= (int)$stats['downloads'] ?></div>
        <div class="stat-label">завантажень оригіналів</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= (int)$stats['users'] ?></div>
        <div class="stat-label">адмінів</div>
    </div>
</section>

<section class="admin-block">
    <header class="admin-block-header">
        <h2>Останні фото</h2>
        <a class="admin-link" href="<?= e(url('/admin/photos')) ?>">Усі →</a>
    </header>

    <?php if (!$latestPhotos): ?>
        <p class="admin-muted">Поки що жодного фото. Завантаження зʼявиться в Етапі 8.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Назва</th>
                    <th>Альбом</th>
                    <th class="num">Завантажень</th>
                    <th class="num">Створено</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($latestPhotos as $p): ?>
                <tr>
                    <td class="thumb-cell">
                        <img src="<?= e(public_url((string)$p['thumb_path'])) ?>" alt="">
                    </td>
                    <td><?= e($p['title']) ?></td>
                    <td><?= e($p['album_title'] ?? '—') ?></td>
                    <td class="num"><?= (int)$p['downloads_count'] ?></td>
                    <td class="num"><?= e(substr((string)$p['created_at'], 0, 16)) ?></td>
                    <td><a class="admin-link" href="<?= e(url('/admin/photos/' . (int)$p['id'] . '/edit')) ?>">Редаг.</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

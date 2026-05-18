<?php
/** @var array<int, array<string, mixed>> $recentPhotos */
?>
<section class="hero">
    <div class="container hero-inner">
        <h1 class="hero-title">Моя фотогалерея</h1>
        <p class="hero-subtitle">
            Кадри, які я зберігаю з фотоапарата — впорядковані за альбомами,
            у красивій сітці і з можливістю завантажити повний оригінал
            на сторінці кожного фото.
        </p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="<?= e(url('/gallery')) ?>">Відкрити галерею</a>
            <a class="btn btn-ghost"   href="<?= e(url('/albums')) ?>">Усі альбоми</a>
        </div>
    </div>
</section>

<section class="container section">
    <header class="section-header">
        <h2>Останні додані</h2>
        <a class="link-muted" href="<?= e(url('/gallery')) ?>">Усі фото →</a>
    </header>

    <?php if (!$recentPhotos): ?>
        <p class="muted">У галереї ще немає фото. Зайди в адмінку і завантаж перше.</p>
    <?php else: ?>
        <div class="photo-grid">
            <?php foreach ($recentPhotos as $p):
                include __DIR__ . '/../partials/photo_card.php';
            endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php
/**
 * @var array<string, mixed>             $album
 * @var array<int, array<string, mixed>> $photos
 */
?>
<section class="container section">
    <p class="breadcrumbs">
        <a href="<?= e(url('/albums')) ?>">← Усі альбоми</a>
    </p>

    <header class="section-header">
        <div>
            <h1><?= e($album['title']) ?></h1>
            <?php if (!empty($album['description'])): ?>
                <p class="muted"><?= e($album['description']) ?></p>
            <?php endif; ?>
        </div>
        <span class="muted"><?= (int)$album['photos_count'] ?> фото</span>
    </header>

    <?php if (!$photos): ?>
        <p class="muted">У цьому альбомі ще немає фото.</p>
    <?php else: ?>
        <div class="photo-grid">
            <?php foreach ($photos as $p):
                include __DIR__ . '/../partials/photo_card.php';
            endforeach; ?>
        </div>
    <?php endif; ?>
</section>

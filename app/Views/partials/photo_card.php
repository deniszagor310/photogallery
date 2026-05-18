<?php
/**
 * app/Views/partials/photo_card.php
 *
 * Карточка фото у сітці. Очікує змінну $p — рядок з таблиці photos
 * (може містити додаткове поле album_title через JOIN).
 *
 * Використання у іншому view:
 *   <?php foreach ($items as $p): include __DIR__ . '/../partials/photo_card.php'; endforeach; ?>
 */
?>
<a class="photo-card" href="<?= e(url('/photo/' . $p['id'])) ?>" aria-label="<?= e($p['title']) ?>">
    <div class="photo-card-img-wrap">
        <img
            class="photo-card-img"
            src="<?= e(public_url($p['thumb_path'])) ?>"
            alt="<?= e($p['title']) ?>"
            loading="lazy"
            <?= !empty($p['width']) ? 'width="500"' : '' ?>
            <?= !empty($p['height']) ? 'height="500"' : '' ?>
        >
    </div>
    <div class="photo-card-meta">
        <span class="photo-card-title"><?= e($p['title']) ?></span>
        <?php if (!empty($p['album_title'])): ?>
            <span class="photo-card-album"><?= e($p['album_title']) ?></span>
        <?php endif; ?>
    </div>
</a>

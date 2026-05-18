<?php
/**
 * @var array<string, mixed> $photo
 */
$exifRows = [
    ['Камера',    $photo['camera_model']  ?? null],
    ['Об\'єктив', $photo['lens_model']    ?? null],
    ['ISO',       $photo['iso']           ?? null],
    ['Діафрагма', $photo['aperture']      ?? null],
    ['Витримка',  $photo['shutter_speed'] ?? null],
];
$hasExif = false;
foreach ($exifRows as [, $v]) {
    if ($v !== null && $v !== '') { $hasExif = true; break; }
}
?>
<section class="container section photo-page">
    <p class="breadcrumbs">
        <a href="<?= e(url('/gallery')) ?>">← Галерея</a>
    </p>

    <article class="photo-detail">
        <div class="photo-detail-img-wrap">
            <img
                class="photo-detail-img"
                src="<?= e(public_url($photo['large_path'])) ?>"
                alt="<?= e($photo['title']) ?>"
            >
        </div>

        <aside class="photo-detail-side">
            <h1 class="photo-detail-title"><?= e($photo['title']) ?></h1>

            <?php if (!empty($photo['description'])): ?>
                <p class="photo-detail-desc"><?= nl2br(e($photo['description'])) ?></p>
            <?php endif; ?>

            <dl class="photo-detail-list">
                <?php if (!empty($photo['album_title'])): ?>
                    <dt>Альбом</dt>
                    <dd>
                        <a href="<?= e(url('/album/' . $photo['album_id'])) ?>">
                            <?= e($photo['album_title']) ?>
                        </a>
                    </dd>
                <?php endif; ?>

                <?php if (!empty($photo['taken_at'])): ?>
                    <dt>Дата зйомки</dt>
                    <dd><?= e(date('d.m.Y H:i', strtotime((string)$photo['taken_at']))) ?></dd>
                <?php endif; ?>

                <?php if (!empty($photo['created_at'])): ?>
                    <dt>Завантажено</dt>
                    <dd><?= e(date('d.m.Y', strtotime((string)$photo['created_at']))) ?></dd>
                <?php endif; ?>

                <?php if (!empty($photo['tags'])): ?>
                    <dt>Теги</dt>
                    <dd class="tag-list">
                        <?php foreach ($photo['tags'] as $t): ?>
                            <span class="tag"><?= e($t['name']) ?></span>
                        <?php endforeach; ?>
                    </dd>
                <?php endif; ?>
            </dl>

            <?php if ($hasExif): ?>
                <h2 class="photo-detail-h2">EXIF</h2>
                <dl class="photo-detail-list">
                    <?php foreach ($exifRows as [$label, $value]):
                        if ($value === null || $value === '') continue;
                    ?>
                        <dt><?= e($label) ?></dt>
                        <dd><?= e((string)$value) ?></dd>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <a class="btn btn-primary btn-download"
               href="<?= e(url('/photo/download/' . $photo['id'])) ?>"
               rel="nofollow"
               aria-label="Завантажити оригінальний файл">
                ⬇ Завантажити оригінал
            </a>
            <p class="hint">
                Оригінал зберігається окремо і віддається через окремий маршрут.
                На сайті завжди показується оптимізована копія для перегляду.
            </p>
        </aside>
    </article>
</section>

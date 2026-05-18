<?php
/** @var array<int, array<string, mixed>> $albums */
?>
<section class="container section">
    <header class="section-header">
        <h1>Альбоми</h1>
        <span class="muted"><?= count($albums) ?> альбомів</span>
    </header>

    <?php if (!$albums): ?>
        <p class="muted">Альбомів ще немає. Створи перший у адмін-панелі.</p>
    <?php else: ?>
        <div class="album-grid">
            <?php foreach ($albums as $a): ?>
                <a class="album-card" href="<?= e(url('/album/' . $a['id'])) ?>">
                    <div class="album-cover">
                        <?php if (!empty($a['cover_thumb'])): ?>
                            <img src="<?= e(public_url($a['cover_thumb'])) ?>"
                                 alt="<?= e($a['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="album-cover-empty">Без обкладинки</div>
                        <?php endif; ?>
                    </div>
                    <div class="album-meta">
                        <h3 class="album-title"><?= e($a['title']) ?></h3>
                        <span class="album-count"><?= (int)$a['photos_count'] ?> фото</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

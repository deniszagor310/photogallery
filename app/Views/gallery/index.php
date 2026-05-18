<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var int                              $total
 * @var int                              $page
 * @var int                              $lastPage
 * @var array<int, array<string, mixed>> $albums
 * @var int|null                         $selectedAlbum
 * @var string                           $searchQuery
 */
?>
<section class="container section">
    <header class="section-header">
        <h1>Галерея</h1>
        <span class="muted">знайдено <?= (int)$total ?> фото</span>
    </header>

    <form class="filters" method="get" action="<?= e(url('/gallery')) ?>">
        <input
            class="filter-search"
            type="search"
            name="q"
            value="<?= e($searchQuery) ?>"
            placeholder="Пошук по назві, опису або тегу…"
            maxlength="100"
            autocomplete="off"
        >

        <select class="filter-select" name="album" aria-label="Альбом">
            <option value="">Усі альбоми</option>
            <?php foreach ($albums as $a): ?>
                <option value="<?= (int)$a['id'] ?>"
                    <?= ($selectedAlbum === (int)$a['id']) ? 'selected' : '' ?>>
                    <?= e($a['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary" type="submit">Застосувати</button>
        <?php if ($searchQuery !== '' || $selectedAlbum !== null): ?>
            <a class="btn btn-ghost" href="<?= e(url('/gallery')) ?>">Скинути</a>
        <?php endif; ?>
    </form>

    <?php if (!$items): ?>
        <p class="muted" style="margin-top:24px">
            За цими параметрами нічого не знайдено.
        </p>
    <?php else: ?>
        <div class="photo-grid" style="margin-top:24px">
            <?php foreach ($items as $p):
                include __DIR__ . '/../partials/photo_card.php';
            endforeach; ?>
        </div>

        <?php
        $baseUrl   = url('/gallery');
        $queryArgs = ['q' => $searchQuery, 'album' => $selectedAlbum];
        include __DIR__ . '/../partials/pagination.php';
        ?>
    <?php endif; ?>
</section>

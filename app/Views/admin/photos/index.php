<?php
/**
 * @var array $result    — {items,total,page,per_page,last_page}
 * @var array $albums
 * @var string $q
 * @var ?int   $albumId
 */
$items = $result['items'];
?>
<header class="admin-page-header">
    <h1>Фото</h1>
    <span class="admin-muted">Усього: <?= (int)$result['total'] ?></span>
</header>

<form class="admin-filter" method="get" action="<?= e(url('/admin/photos')) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Пошук по назві / опису / тегу">
    <select name="album">
        <option value="">— усі альбоми —</option>
        <?php foreach ($albums as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $albumId === (int)$a['id'] ? 'selected' : '' ?>>
                <?= e($a['title']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-sm">Фільтр</button>
</form>

<?php if (!$items): ?>
    <p class="admin-muted">Нічого не знайдено. Аплоад фото зʼявиться в Етапі 8.</p>
<?php else: ?>
<table class="admin-table">
    <thead>
        <tr>
            <th></th>
            <th>Назва</th>
            <th>Альбом</th>
            <th class="num">Розмір</th>
            <th class="num">⬇</th>
            <th class="num">Створено</th>
            <th class="actions"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $p): ?>
        <tr>
            <td class="thumb-cell">
                <img src="<?= e(public_url((string)$p['thumb_path'])) ?>" alt="" loading="lazy">
            </td>
            <td>
                <a class="admin-link" href="<?= e(url('/admin/photos/' . (int)$p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a>
                <div class="admin-muted-small"><?= e($p['mime_type']) ?></div>
            </td>
            <td><?= e($p['album_title'] ?? '—') ?></td>
            <td class="num"><?= number_format((int)$p['original_size'] / 1024, 0, '.', ' ') ?> KB</td>
            <td class="num"><?= (int)$p['downloads_count'] ?></td>
            <td class="num"><?= e(substr((string)$p['created_at'], 0, 16)) ?></td>
            <td class="actions">
                <a class="btn btn-sm" href="<?= e(url('/photo/' . (int)$p['id'])) ?>" target="_blank">↗</a>
                <a class="btn btn-sm" href="<?= e(url('/admin/photos/' . (int)$p['id'] . '/edit')) ?>">Редаг.</a>
                <form action="<?= e(url('/admin/photos/' . (int)$p['id'] . '/delete')) ?>"
                      method="post" class="inline-form"
                      onsubmit="return confirm('Видалити фото «<?= e(addslashes($p['title'])) ?>»? (фізичний файл поки залишиться)');">
                    <?= csrf_input() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Видалити</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($result['last_page'] > 1): ?>
    <?php
    // Збираємо querystring без 'page'.
    $qs = $_GET; unset($qs['page']);
    $base = url('/admin/photos') . '?' . http_build_query($qs);
    $sep  = strpos($base, '?') === strlen($base) - 1 ? '' : '&';
    ?>
    <nav class="pagination" aria-label="Сторінки">
        <?php for ($i = 1; $i <= $result['last_page']; $i++): ?>
            <a class="page <?= $i === $result['page'] ? 'is-active' : '' ?>"
               href="<?= e($base . $sep . 'page=' . $i) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<?php endif; ?>

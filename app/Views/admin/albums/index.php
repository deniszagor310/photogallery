<?php /** @var array $albums */ ?>
<header class="admin-page-header">
    <h1>Альбоми</h1>
    <a class="btn btn-primary" href="<?= e(url('/admin/albums/new')) ?>">+ Новий альбом</a>
</header>

<?php if (!$albums): ?>
    <p class="admin-muted">Поки що жодного альбому. Створи перший.</p>
<?php else: ?>
<table class="admin-table">
    <thead>
        <tr>
            <th>Назва</th>
            <th>Slug</th>
            <th class="num">Фото</th>
            <th class="num">Створено</th>
            <th class="actions"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($albums as $a): ?>
        <tr>
            <td>
                <a class="admin-link" href="<?= e(url('/admin/albums/' . (int)$a['id'] . '/edit')) ?>">
                    <?= e($a['title']) ?>
                </a>
            </td>
            <td><code><?= e($a['slug']) ?></code></td>
            <td class="num"><?= (int)$a['photos_count'] ?></td>
            <td class="num"><?= e(substr((string)$a['created_at'], 0, 16)) ?></td>
            <td class="actions">
                <a class="btn btn-sm" href="<?= e(url('/album/' . (int)$a['id'])) ?>" target="_blank" rel="noopener">↗ Сайт</a>
                <a class="btn btn-sm" href="<?= e(url('/admin/albums/' . (int)$a['id'] . '/edit')) ?>">Редаг.</a>
                <form action="<?= e(url('/admin/albums/' . (int)$a['id'] . '/delete')) ?>"
                      method="post" class="inline-form"
                      onsubmit="return confirm('Видалити альбом «<?= e(addslashes($a['title'])) ?>»? Фото залишаться, але втратять привʼязку.');">
                    <?= csrf_input() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Видалити</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

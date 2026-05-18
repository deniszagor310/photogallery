<?php /** @var array $tags */ ?>
<header class="admin-page-header">
    <h1>Теги</h1>
</header>

<form class="admin-form admin-inline-form" method="post" action="<?= e(url('/admin/tags')) ?>">
    <?= csrf_input() ?>
    <div class="form-row">
        <label class="form-label">Створити новий тег</label>
        <div class="inline-row">
            <input type="text" name="name" required maxlength="80" placeholder="природа">
            <button class="btn btn-primary" type="submit">Створити</button>
        </div>
    </div>
</form>

<?php if (!$tags): ?>
    <p class="admin-muted">Тегів ще немає. Створи перший вище — або вони зʼявляться автоматично, коли додаси теги до фото.</p>
<?php else: ?>
<table class="admin-table">
    <thead>
        <tr>
            <th>Назва</th>
            <th>Slug</th>
            <th class="num">Фото</th>
            <th class="actions"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($tags as $t): ?>
        <tr>
            <td><?= e($t['name']) ?></td>
            <td><code><?= e($t['slug']) ?></code></td>
            <td class="num"><?= (int)$t['photos_count'] ?></td>
            <td class="actions">
                <form action="<?= e(url('/admin/tags/' . (int)$t['id'] . '/delete')) ?>"
                      method="post" class="inline-form"
                      onsubmit="return confirm('Видалити тег «<?= e(addslashes($t['name'])) ?>»? Привʼязка до фото зникне.');">
                    <?= csrf_input() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Видалити</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

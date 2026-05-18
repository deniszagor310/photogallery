<?php
/**
 * Форма створення/редагування альбому.
 * @var array|null $album
 * @var string $formAction
 * @var array $photosInAlbum (опційно, тільки при edit)
 */
$isEdit = $album !== null;
$title = $isEdit ? (string)$album['title'] : old('title');
$descr = $isEdit ? (string)($album['description'] ?? '') : old('description');
?>
<header class="admin-page-header">
    <h1><?= $isEdit ? 'Редагувати альбом' : 'Новий альбом' ?></h1>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/albums')) ?>">← До списку</a>
</header>

<form action="<?= e($formAction) ?>" method="post" class="admin-form">
    <?= csrf_input() ?>

    <div class="form-row">
        <label class="form-label">Назва</label>
        <input type="text" name="title" required maxlength="150" value="<?= e($title) ?>">
    </div>

    <?php if ($isEdit): ?>
    <div class="form-row">
        <label class="form-label">Slug</label>
        <input type="text" value="<?= e((string)$album['slug']) ?>" disabled>
        <label class="form-check">
            <input type="checkbox" name="regen_slug" value="1">
            <span>Перегенерувати slug на основі нової назви</span>
        </label>
        <small class="admin-muted">
            Увага: зміна slug ламає старі публічні посилання вигляду <code>/album/&lt;id&gt;</code> не лама́є,
            бо ми досі ходимо за <code>id</code>; але <code>album_slug</code> у пошукових індексах оновиться.
        </small>
    </div>
    <?php endif; ?>

    <div class="form-row">
        <label class="form-label">Опис</label>
        <textarea name="description" rows="5" maxlength="2000"><?= e($descr) ?></textarea>
    </div>

    <?php if ($isEdit && !empty($photosInAlbum)): ?>
    <div class="form-row">
        <label class="form-label">Обкладинка</label>
        <select name="cover_photo_id">
            <option value="0">— автоматично (найновіше фото) —</option>
            <?php foreach ($photosInAlbum as $p): ?>
                <option value="<?= (int)$p['id'] ?>"
                    <?= (int)($album['cover_photo_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= e($p['title']) ?> (#<?= (int)$p['id'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <?= $isEdit ? 'Зберегти' : 'Створити альбом' ?>
        </button>
        <?php if ($isEdit): ?>
            <a class="btn btn-ghost" href="<?= e(url('/album/' . (int)$album['id'])) ?>" target="_blank">↗ Відкрити публічно</a>
        <?php endif; ?>
    </div>
</form>

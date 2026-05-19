<?php
/**
 * @var array<int, array<string, mixed>> $albums
 * @var int   $defaultAlbum
 * @var int   $maxSize        — у байтах
 * @var array $allowedExt
 */
$mb = (int)round($maxSize / (1024 * 1024));
?>
<header class="admin-page-header">
    <h1>Завантажити фото</h1>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/photos')) ?>">← До списку</a>
</header>

<form class="admin-form admin-upload-form"
      method="post"
      action="<?= e(url('/admin/photos')) ?>"
      enctype="multipart/form-data">
    <?= csrf_input() ?>

    <div class="form-row">
        <label class="form-label" for="upload-files">Файли</label>
        <input id="upload-files"
               type="file"
               name="photos[]"
               accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
               multiple
               required>
        <p class="admin-muted-small">
            Дозволено: <?= e(strtoupper(implode(', ', $allowedExt))) ?>.
            Максимальний розмір одного файлу: <?= $mb ?> МБ.
            Можна вибрати кілька файлів одразу.
        </p>
    </div>

    <div class="form-row">
        <label class="form-label" for="upload-album">Альбом</label>
        <select id="upload-album" name="album_id">
            <option value="0">— без альбому —</option>
            <?php foreach ($albums as $a): ?>
                <option value="<?= (int)$a['id'] ?>"
                    <?= (int)$defaultAlbum === (int)$a['id'] ? 'selected' : '' ?>>
                    <?= e($a['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row">
        <label class="form-label" for="upload-desc">Опис <span class="admin-muted">(спільний для всієї пачки)</span></label>
        <textarea id="upload-desc" name="description" rows="3" maxlength="4000"></textarea>
    </div>

    <div class="form-row">
        <label class="form-label" for="upload-tags">Теги <span class="admin-muted">(через кому)</span></label>
        <input id="upload-tags" type="text" name="tags" placeholder="природа, гори, схід сонця">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Завантажити</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/photos')) ?>">Скасувати</a>
    </div>
</form>

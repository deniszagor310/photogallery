<?php
/**
 * @var array $photo  — з findWithDetails() (включно з tags)
 * @var array $albums
 */
$tagsCsv = implode(', ', array_map(static fn($t) => (string)$t['name'], $photo['tags'] ?? []));
$takenAt = $photo['taken_at'] ?: '';
if ($takenAt !== '') {
    // Конвертуємо MySQL DATETIME 'Y-m-d H:i:s' у формат для <input type="datetime-local">.
    $takenAt = str_replace(' ', 'T', substr((string)$takenAt, 0, 16));
}
?>
<header class="admin-page-header">
    <h1>Редагувати фото</h1>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/photos')) ?>">← До списку</a>
</header>

<div class="admin-edit-photo">
    <aside class="admin-edit-photo-preview">
        <img src="<?= e(public_url((string)$photo['large_path'])) ?>" alt="<?= e($photo['title']) ?>">
        <dl class="admin-meta">
            <div><dt>ID</dt>           <dd>#<?= (int)$photo['id'] ?></dd></div>
            <div><dt>MIME</dt>         <dd><?= e($photo['mime_type']) ?></dd></div>
            <div><dt>Розмір</dt>       <dd><?= number_format((int)$photo['original_size'] / 1024, 1, '.', ' ') ?> KB</dd></div>
            <div><dt>Розмірність</dt>  <dd><?= (int)($photo['width'] ?? 0) ?> × <?= (int)($photo['height'] ?? 0) ?></dd></div>
            <div><dt>Завантажень</dt>  <dd><?= (int)$photo['downloads_count'] ?></dd></div>
            <div><dt>Створено</dt>     <dd><?= e(substr((string)$photo['created_at'], 0, 16)) ?></dd></div>
        </dl>
    </aside>

    <form class="admin-form admin-edit-photo-form"
          method="post"
          action="<?= e(url('/admin/photos/' . (int)$photo['id'])) ?>">
        <?= csrf_input() ?>

        <div class="form-row">
            <label class="form-label">Назва</label>
            <input type="text" name="title" required maxlength="200" value="<?= e($photo['title']) ?>">
        </div>

        <div class="form-row">
            <label class="form-label">Slug</label>
            <input type="text" value="<?= e($photo['slug']) ?>" disabled>
            <label class="form-check">
                <input type="checkbox" name="regen_slug" value="1">
                <span>Перегенерувати slug</span>
            </label>
        </div>

        <div class="form-row">
            <label class="form-label">Альбом</label>
            <select name="album_id">
                <option value="0">— без альбому —</option>
                <?php foreach ($albums as $a): ?>
                    <option value="<?= (int)$a['id'] ?>"
                        <?= (int)$photo['album_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                        <?= e($a['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label class="form-label">Опис</label>
            <textarea name="description" rows="4" maxlength="4000"><?= e($photo['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <label class="form-label">Теги <span class="admin-muted">(через кому)</span></label>
            <input type="text" name="tags" value="<?= e($tagsCsv) ?>"
                   placeholder="природа, схід сонця, Карпати">
        </div>

        <fieldset class="admin-fieldset">
            <legend>EXIF / технічні поля</legend>
            <div class="form-grid-2">
                <div class="form-row">
                    <label class="form-label">Камера</label>
                    <input type="text" name="camera_model" maxlength="120" value="<?= e($photo['camera_model'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label class="form-label">Обʼєктив</label>
                    <input type="text" name="lens_model" maxlength="120" value="<?= e($photo['lens_model'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label class="form-label">ISO</label>
                    <input type="number" name="iso" min="0" max="409600" value="<?= e((string)($photo['iso'] ?? '')) ?>">
                </div>
                <div class="form-row">
                    <label class="form-label">Діафрагма</label>
                    <input type="text" name="aperture" maxlength="20" placeholder="f/2.8" value="<?= e($photo['aperture'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label class="form-label">Витримка</label>
                    <input type="text" name="shutter_speed" maxlength="20" placeholder="1/250" value="<?= e($photo['shutter_speed'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label class="form-label">Знято</label>
                    <input type="datetime-local" name="taken_at" value="<?= e($takenAt) ?>">
                </div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Зберегти</button>
            <a class="btn btn-ghost" href="<?= e(url('/photo/' . (int)$photo['id'])) ?>" target="_blank">↗ Публічна сторінка</a>
        </div>
    </form>
</div>

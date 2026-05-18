<?php /** @var string $title */ ?>
<section class="auth">
    <div class="auth-card">
        <h1 class="auth-title">Вхід в адмінку</h1>
        <p class="auth-hint">
            Доступ мають лише облікові записи з таблиці <code>users</code>.
        </p>

        <?php if ($err = flash('error')): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endif; ?>

        <form action="<?= e(url('/login')) ?>" method="post" class="auth-form" autocomplete="on">
            <?= csrf_input() ?>

            <label class="form-row">
                <span class="form-label">Логін або email</span>
                <input
                    type="text"
                    name="login"
                    value="<?= e(old('login')) ?>"
                    autocomplete="username"
                    required
                    maxlength="100"
                    autofocus
                >
            </label>

            <label class="form-row">
                <span class="form-label">Пароль</span>
                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    maxlength="200"
                >
            </label>

            <button type="submit" class="btn btn-primary btn-block">Увійти</button>
        </form>
    </div>
</section>

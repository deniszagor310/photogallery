<?php
/**
 * app/Views/partials/pagination.php
 *
 * Прості "← попередня | сторінка X з Y | наступна →" посилання.
 *
 * Очікувані змінні:
 *   $page      — поточна сторінка
 *   $lastPage  — остання
 *   $baseUrl   — URL без query-string ('/gallery')
 *   $queryArgs — асоц. масив параметрів, які треба зберігати ('album', 'q')
 */
$queryArgs = $queryArgs ?? [];
$mkUrl = static function (int $page) use ($baseUrl, $queryArgs): string {
    $args = $queryArgs;
    $args['page'] = $page;
    $qs = http_build_query(array_filter($args, fn($v) => $v !== null && $v !== ''));
    return $baseUrl . ($qs ? ('?' . $qs) : '');
};
?>
<?php if ($lastPage > 1): ?>
<nav class="pagination" aria-label="Пагінація">
    <?php if ($page > 1): ?>
        <a class="page-link" href="<?= e($mkUrl($page - 1)) ?>" rel="prev">← Попередня</a>
    <?php else: ?>
        <span class="page-link page-disabled">← Попередня</span>
    <?php endif; ?>

    <span class="page-info">Сторінка <?= (int)$page ?> з <?= (int)$lastPage ?></span>

    <?php if ($page < $lastPage): ?>
        <a class="page-link" href="<?= e($mkUrl($page + 1)) ?>" rel="next">Наступна →</a>
    <?php else: ?>
        <span class="page-link page-disabled">Наступна →</span>
    <?php endif; ?>
</nav>
<?php endif; ?>

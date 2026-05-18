<?php
namespace App\Core;

/**
 * app/Core/Controller.php
 *
 * Базовий контролер. Усі реальні контролери (HomeController, GalleryController,
 * PhotoController, AdminController, ...) успадковуються від нього.
 *
 * Дає три зручні методи:
 *  - render($view, $data, $layout): рендер PHP-шаблону всередині layout
 *  - redirect($url, $code): редірект
 *  - abort($code, $msg): припинити обробку запиту з помилкою
 */
abstract class Controller
{
    /**
     * Рендер шаблону.
     *
     * @param string                $view   Напр.: 'home/index' → app/Views/home/index.php
     * @param array<string, mixed>  $data   Змінні, доступні у шаблоні
     * @param string|null           $layout Напр.: 'layouts/public'. Якщо null — без layout.
     */
    protected function render(string $view, array $data = [], ?string $layout = 'layouts/public'): void
    {
        $viewPath = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException("View не знайдено: {$viewPath}");
        }

        // Перетворюємо ['title' => 'X'] у змінну $title всередині шаблону.
        extract($data, EXTR_SKIP);

        // Рендеримо view у буфер, потім підставимо у layout як $content.
        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutPath = BASE_PATH . '/app/Views/' . $layout . '.php';
        if (!is_file($layoutPath)) {
            throw new \RuntimeException("Layout не знайдено: {$layoutPath}");
        }

        require $layoutPath;
    }

    /**
     * Редірект з опційним кодом (302 за замовчуванням).
     */
    protected function redirect(string $url, int $code = 302): never
    {
        header('Location: ' . $url, true, $code);
        exit;
    }

    /**
     * Завершити обробку з HTTP-кодом помилки.
     */
    protected function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        echo "<!doctype html><meta charset='utf-8'><title>{$code}</title>";
        echo "<h1>{$code}</h1>";
        if ($message !== '') {
            echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        exit;
    }
}

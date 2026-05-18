<?php
namespace App\Core;

/**
 * app/Core/Router.php
 *
 * Простий router з підтримкою:
 *  - HTTP-методів GET / POST
 *  - плейсхолдерів виду {id} (тільки цифри) та {slug} (літери/цифри/-/_)
 *  - 404 для невідомих маршрутів
 *
 * Як це працює:
 *  1) Реєструємо маршрути:
 *       $router->get('/photo/{id}', [PhotoController::class, 'show']);
 *  2) Шаблон '/photo/{id}' перетворюється у regex
 *       '#^/photo/(?P<id>\d+)$#'
 *  3) При dispatch() порівнюємо шлях запиту з регулярками
 *     і викликаємо контролер, передавши параметри:
 *       (new PhotoController())->show($id)
 */
final class Router
{
    /**
     * @var array<string, array<int, array{pattern:string, handler:array{0:string,1:string}, params:string[]}>>
     */
    private array $routes = [
        'GET'  => [],
        'POST' => [],
    ];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): void
    {
        // Із '/photo/{id}' робимо '#^/photo/(?P<id>\d+)$#'
        // {id}   → лише цифри
        // {slug} → латиниця/цифри/-/_
        $params = [];
        $regex = preg_replace_callback(
            '/\{(\w+)\}/',
            function (array $m) use (&$params): string {
                $params[] = $m[1];
                // id → \d+, інакше [A-Za-z0-9_-]+
                $pattern = ($m[1] === 'id') ? '\d+' : '[A-Za-z0-9_-]+';
                return '(?P<' . $m[1] . '>' . $pattern . ')';
            },
            $path
        );

        $this->routes[$method][] = [
            'pattern' => '#^' . $regex . '$#',
            'handler' => $handler,
            'params'  => $params,
        ];
    }

    /**
     * Запустити пошук маршруту та виклик контролера.
     */
    public function dispatch(string $method, string $uri): void
    {
        // Прибираємо querystring і trailing slash (крім кореня).
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // GET та HEAD обробляємо однаково.
        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        if (!isset($this->routes[$method])) {
            $this->abort(405, 'Method Not Allowed');
            return;
        }

        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                // Витягуємо лише іменовані параметри у тому порядку,
                // у якому вони були в шаблоні.
                $args = [];
                foreach ($route['params'] as $name) {
                    $args[] = $matches[$name] ?? null;
                }

                [$class, $action] = $route['handler'];
                if (!class_exists($class)) {
                    $this->abort(500, "Контролер {$class} не знайдено");
                    return;
                }

                $controller = new $class();
                if (!method_exists($controller, $action)) {
                    $this->abort(500, "Метод {$class}::{$action}() не існує");
                    return;
                }

                $controller->{$action}(...$args);
                return;
            }
        }

        $this->abort(404, 'Сторінку не знайдено');
    }

    private function abort(int $code, string $message): void
    {
        http_response_code($code);
        // Просте текстове повідомлення, щоб не залежати від view-шару тут.
        header('Content-Type: text/html; charset=utf-8');
        echo "<!doctype html><meta charset='utf-8'>";
        echo "<title>{$code}</title>";
        echo "<h1>{$code}</h1>";
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    }
}

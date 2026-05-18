<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Services\Auth;

/**
 * app/Controllers/AuthController.php
 *
 *  GET  /login   — форма входу.
 *  POST /login   — обробка форми.
 *  POST /logout  — вихід (тільки POST, з CSRF).
 */
final class AuthController extends Controller
{
    /**
     * Сторінка з формою.
     * Якщо вже залогінений — одразу редірект на головну.
     */
    public function loginForm(): void
    {
        if (auth()->check()) {
            $this->redirect(url('/'));
        }

        $this->render('auth/login', [
            'title' => 'Вхід',
        ]);
    }

    /**
     * Обробка форми входу.
     *
     * Послідовність перевірок ВАЖЛИВА:
     *   1) CSRF (інакше зловмисник з іншого сайту може спамити логіни).
     *   2) Тротлінг (5 невдалих → 30 с пауза).
     *   3) Спроба автентифікації.
     *   4) Запис флеш + редірект (POST-Redirect-GET — не залишаємо POST у історії).
     */
    public function login(): void
    {
        $this->requireCsrf();

        $auth = auth();

        // Якщо ще під блокуванням — повертаємо на форму з повідомленням.
        $th = $auth->throttleStatus();
        if ($th['blocked']) {
            flash_set('error', "Забагато невдалих спроб. Зачекай {$th['wait']} с і спробуй знов.");
            $_SESSION['_old']['login'] = (string)($_POST['login'] ?? '');
            $this->redirect(url('/login'));
        }

        $login    = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($login === '' || $password === '') {
            flash_set('error', 'Введи логін і пароль.');
            $_SESSION['_old']['login'] = $login;
            $this->redirect(url('/login'));
        }

        // Обмежимо довжину, щоб не дати бомбардувати password_verify() мегабайтами.
        if (mb_strlen($login) > 100 || strlen($password) > 200) {
            flash_set('error', 'Занадто довгі поля.');
            $this->redirect(url('/login'));
        }

        if ($auth->attempt($login, $password)) {
            // Усе ок: на цей момент сесія вже регенерована, CSRF reset зроблено.
            flash_set('success', 'Вітаю, ' . ($_SESSION['_user_login'] ?? 'адмін') . '!');
            $this->redirect(url('/'));
        }

        // Невдала спроба.
        $wait = $auth->noteFailedAttempt();
        if ($wait > 0) {
            flash_set('error', "Забагато невдалих спроб. Спробуй за {$wait} с.");
        } else {
            flash_set('error', 'Неправильний логін або пароль.');
        }
        $_SESSION['_old']['login'] = $login;
        $this->redirect(url('/login'));
    }

    /**
     * Вихід. Завжди тільки POST + CSRF.
     */
    public function logout(): void
    {
        $this->requireCsrf();
        auth()->logout();
        flash_set('success', 'Сесію завершено.');
        $this->redirect(url('/'));
    }
}

<?php
namespace App\Services;

use App\Models\User;

/**
 * app/Services/Auth.php
 *
 * Сервіс автентифікації.
 *
 * Цей сервіс — єдиний шар у коді, який знає, як саме перевіряти пароль
 * і де зберігати ідентифікатор поточного користувача (у $_SESSION).
 * Контролери ніколи не лазять напряму в $_SESSION['_user_id'].
 *
 * Захист, який тут є:
 *   1) password_verify() з реальним хешем + dummy-verify при відсутньому
 *      користувачі — щоб не дати timing-oracle (час відповіді не залежить
 *      від того, чи існує юзер з таким логіном).
 *   2) password_needs_rehash() — якщо адмін кладе у БД новий хеш зі
 *      слабшим cost, після першого вдалого логіну ми його непомітно
 *      проапгрейдимо до поточного.
 *   3) session_regenerate_id(true) одразу після успіху — захист від
 *      session fixation.
 *   4) Тротлінг невдалих спроб: після 5 фейлів — пауза 30 секунд.
 *      Чисто на стороні сервера, прив'язано до сесії.
 */
final class Auth
{
    /** Кеш користувача у пам'яті процесу. */
    private ?array $userCache = null;

    /** Скільки невдалих спроб поспіль допускаємо без затримки. */
    private const MAX_ATTEMPTS = 5;

    /** Тривалість «бану» після перевищення MAX_ATTEMPTS, секунд. */
    private const LOCKOUT_SECONDS = 30;

    /**
     * Спроба входу. Повертає true, якщо логін успішний.
     *
     * При невдачі НЕ розкриваємо, чи існує користувач — це інформація
     * для атакувальника.
     */
    public function attempt(string $login, string $password): bool
    {
        $userModel = new User();
        $user = $userModel->findByLogin($login);

        if ($user === null) {
            // Юзера немає — все одно витрачаємо час на password_verify(),
            // щоб ззовні не можна було відрізнити «не існує» від «неправильний пароль».
            $dummyHash = '$2y$10$' . str_repeat('a', 53);
            password_verify($password, $dummyHash);
            return false;
        }

        $storedHash = (string)($user['password_hash'] ?? '');
        if ($storedHash === '' || !password_verify($password, $storedHash)) {
            return false;
        }

        // Логін успішний. Чи треба переписати хеш у БД?
        if (password_needs_rehash($storedHash, PASSWORD_BCRYPT, ['cost' => 10])) {
            try {
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                $userModel->updateHash((int)$user['id'], $newHash);
            } catch (\Throwable $e) {
                // Апгрейд хеша — не критичний для самого логіну. Залишимо як є.
            }
        }

        // Зберігаємо id у сесії і одразу регенеруємо session_id.
        session_regenerate_id(true);
        $_SESSION['_user_id']   = (int)$user['id'];
        $_SESSION['_user_login'] = (string)$user['username'];

        // Скидаємо токен і тротлінг — попередні значення прив'язані до
        // «гостьової» сесії.
        Csrf::reset();
        $this->resetThrottle();

        // Не тримаємо хеш у RAM-кеші.
        unset($user['password_hash']);
        $this->userCache = $user;

        return true;
    }

    public function check(): bool
    {
        return !empty($_SESSION['_user_id']);
    }

    /**
     * Поточний користувач (без password_hash) або null.
     */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }
        if ($this->userCache !== null) {
            return $this->userCache;
        }

        $u = (new User())->find((int)$_SESSION['_user_id']);
        if ($u === null) {
            // Юзера видалили — рвемо сесію.
            $this->logout();
            return null;
        }
        unset($u['password_hash']);
        $this->userCache = $u;
        return $u;
    }

    /**
     * Закрити сесію повністю.
     */
    public function logout(): void
    {
        $_SESSION = [];

        // Видалити cookie сесії, якщо він був.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path']     ?? '/',
                    'domain'   => $params['domain']   ?? '',
                    'secure'   => $params['secure']   ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
        $this->userCache = null;
    }

    /**
     * Перевіряє стан тротлінгу. Повертає [blocked, wait_seconds].
     * Контролер показує юзеру повідомлення «спробуйте через X секунд».
     */
    public function throttleStatus(): array
    {
        $unlockAt = (int)($_SESSION['_auth_throttle']['unlock_at'] ?? 0);
        $wait = $unlockAt - time();
        if ($wait > 0) {
            return ['blocked' => true, 'wait' => $wait];
        }
        return ['blocked' => false, 'wait' => 0];
    }

    /**
     * Реєструє ОДНУ невдалу спробу.
     * Повертає кількість секунд блокування (0, якщо ще не заблоковано).
     */
    public function noteFailedAttempt(): int
    {
        $now = time();
        $th  = $_SESSION['_auth_throttle'] ?? ['count' => 0, 'unlock_at' => 0];

        // Якщо ще під блокуванням — нічого не змінюємо.
        if ((int)$th['unlock_at'] > $now) {
            return (int)$th['unlock_at'] - $now;
        }

        $th['count'] = (int)$th['count'] + 1;

        if ($th['count'] >= self::MAX_ATTEMPTS) {
            $th['unlock_at'] = $now + self::LOCKOUT_SECONDS;
            $th['count'] = 0; // починаємо лічити з нуля після паузи
        }

        $_SESSION['_auth_throttle'] = $th;
        return max(0, (int)$th['unlock_at'] - $now);
    }

    public function resetThrottle(): void
    {
        unset($_SESSION['_auth_throttle']);
    }
}

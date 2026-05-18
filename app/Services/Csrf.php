<?php
namespace App\Services;

/**
 * app/Services/Csrf.php
 *
 * Простий per-session CSRF-токен.
 *
 *  - token():      повертає поточний токен; створює, якщо ще немає.
 *  - check($t):    constant-time-порівняння переданого з токеном у сесії.
 *  - reset():      примусово викинути токен (виклик після login/logout).
 *
 * Чому per-session, а не per-form?
 *  - Простіше для навчального проєкту: одна сесія = один токен.
 *  - Достатньо проти класичних CSRF-атак: зловмисник не може прочитати
 *    cookie сесії і, відповідно, не може дізнатися токен.
 *  - Якщо колись захочеться — легко перетворити у per-form, додавши масив
 *    у $_SESSION['_csrf_tokens'][$formId].
 *
 * Як використовувати:
 *  - У формах:    <input type="hidden" name="_csrf" value="<?= Csrf::token() ?>">
 *  - У контролері: $this->requireCsrf();
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    /**
     * Повернути поточний токен, або згенерувати новий.
     * 32 байти = 256 біт ентропії — більш ніж достатньо.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION[self::KEY];
    }

    /**
     * Перевірити переданий токен. Завжди ВЖИВАЄМО hash_equals(),
     * щоб не дати атаці на timing-side-channel.
     */
    public static function check(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        $expected = $_SESSION[self::KEY] ?? '';
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    /**
     * Видалити поточний токен. Робимо це:
     *  - після успішного логіну (щоб старий cookie-токен втратив силу),
     *  - після logout (тут і так session_unset(), але страховка).
     */
    public static function reset(): void
    {
        unset($_SESSION[self::KEY]);
    }
}

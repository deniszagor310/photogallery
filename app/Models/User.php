<?php
namespace App\Models;

use App\Core\Model;

/**
 * app/Models/User.php
 *
 * Адміни сайту. Інших ролей у проєкті поки немає — будь-який запис
 * у таблиці users автоматично має доступ до /admin/* (Етап 7).
 */
final class User extends Model
{
    protected string $table = 'users';

    /**
     * Знайти користувача за логіном АБО email.
     * Дозволяємо обидва варіанти, щоб не плутатись на формі логіну.
     */
    public function findByLogin(string $login): ?array
    {
        $sql = 'SELECT * FROM users WHERE username = :u OR email = :e LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $login, ':e' => $login]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Перезаписати password_hash. Викликаємо після успішного логіну,
     * якщо password_needs_rehash() каже, що алгоритм/cost застарів.
     */
    public function updateHash(int $id, string $newHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = :h WHERE id = :id'
        );
        $stmt->execute([':h' => $newHash, ':id' => $id]);
    }
}

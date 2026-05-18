<?php
namespace App\Core;

use PDO;

/**
 * app/Core/Model.php
 *
 * Тонкий базовий клас для моделей. Кожна модель вказує:
 *   protected string $table = 'photos';
 * і отримує безкоштовно методи all() / find() / delete().
 *
 * Складніша логіка (JOINи, пошук, фільтри) лежить у конкретних
 * моделях (Photo, Album, Tag, User).
 *
 * Принципово: усе через prepared statements. Жодних рядкових
 * конкатенацій SQL з $_GET / $_POST.
 */
abstract class Model
{
    /** Назва таблиці у БД */
    protected string $table = '';

    /** Назва первинного ключа */
    protected string $primaryKey = 'id';

    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * Усі записи (з опційним сортуванням і лімітом).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(string $orderBy = 'id DESC', ?int $limit = null): array
    {
        // $orderBy НЕ беремо ззовні — це безпечно лише тому,
        // що викликаючий код передає літерал.
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy}";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Знайти запис за первинним ключем.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Видалити запис за первинним ключем.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?"
        );
        return $stmt->execute([$id]);
    }
}

<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * app/Core/Database.php
 *
 * Тонкий singleton поверх PDO.
 * Один екземпляр з'єднання на весь запит.
 *
 * Використання:
 *   $pdo = Database::pdo();
 *   $stmt = $pdo->prepare("SELECT * FROM photos WHERE id = ?");
 *   $stmt->execute([$id]);
 *
 * Чому singleton:
 *  - PDO відкриває реальне TCP-з'єднання з MySQL — невигідно
 *    створювати його у кожній моделі.
 *  - Усі моделі мають отримувати ОДНЕ й те саме з'єднання.
 */
final class Database
{
    /** @var PDO|null */
    private static ?PDO $pdo = null;

    /** Заборонити створення екземпляра */
    private function __construct() {}
    private function __clone() {}

    /**
     * Ініціалізація з масиву налаштувань (з config/config.php).
     * Викликається один раз у public/index.php.
     */
    public static function init(array $config): void
    {
        if (self::$pdo !== null) {
            return;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $config['name'],
            $config['charset'] ?? 'utf8mb4'
        );

        $options = [
            // Кидати виключення, а не повертати false на помилки SQL
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Асоціативні масиви за замовчуванням
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Справжні prepared statements, без емуляції
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Тримати постійні значення як рядки (важливо для bcrypt-хешів)
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $config['user'], $config['password'], $options);
        } catch (PDOException $e) {
            // Не показуємо пароль у трейсі назовні.
            // Просто кидаємо вище — головний обробник вирішить, що робити.
            throw new \RuntimeException(
                'Не вдалось підключитись до MySQL: ' . $e->getMessage(),
                (int)$e->getCode()
            );
        }
    }

    /**
     * Отримати готове PDO-з'єднання.
     */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException(
                'Database::init() ще не викликано. Перевір public/index.php.'
            );
        }
        return self::$pdo;
    }
}

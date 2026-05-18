<?php
namespace App\Models;

use App\Core\Model;
use App\Services\Slug;

/**
 * app/Models/Tag.php
 *
 * Робота з таблицею tags та зв'язком photo_tags.
 */
final class Tag extends Model
{
    protected string $table = 'tags';

    /**
     * Усі теги, відсортовані за іменем, з кількістю фото.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWithCounts(): array
    {
        $sql = "SELECT t.*,
                       (SELECT COUNT(*) FROM photo_tags pt WHERE pt.tag_id = t.id) AS photos_count
                  FROM tags t
                 ORDER BY t.name";
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Знайти тег за нормалізованим іменем, або створити новий.
     *
     * @return array{id:int, name:string, slug:string}
     */
    public function findOrCreate(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Tag name cannot be empty');
        }

        $stmt = $this->pdo->prepare('SELECT id, name, slug FROM tags WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return [
                'id'   => (int)$row['id'],
                'name' => (string)$row['name'],
                'slug' => (string)$row['slug'],
            ];
        }

        $slug = Slug::uniqueFor($name, function (string $s): bool {
            $stmt = $this->pdo->prepare('SELECT 1 FROM tags WHERE slug = ?');
            $stmt->execute([$s]);
            return (bool)$stmt->fetchColumn();
        }, 100);

        $stmt = $this->pdo->prepare(
            'INSERT INTO tags (name, slug) VALUES (?, ?)'
        );
        $stmt->execute([$name, $slug]);

        return [
            'id'   => (int)$this->pdo->lastInsertId(),
            'name' => $name,
            'slug' => $slug,
        ];
    }

    /**
     * Привʼязати масив імен тегів до фото.
     * Стара привʼязка видаляється повністю — простіше і безпечніше,
     * ніж diff-логіка.
     *
     * @param string[] $names
     */
    public function syncForPhoto(int $photoId, array $names): void
    {
        // Нормалізуємо: trim, прибираємо порожні, дедуплікуємо без регістру.
        $unique = [];
        foreach ($names as $n) {
            $n = trim((string)$n);
            if ($n === '') continue;
            $key = mb_strtolower($n, 'UTF-8');
            if (!isset($unique[$key])) {
                $unique[$key] = $n;
            }
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM photo_tags WHERE photo_id = ?');
            $del->execute([$photoId]);

            if ($unique) {
                $ins = $this->pdo->prepare('INSERT INTO photo_tags (photo_id, tag_id) VALUES (?, ?)');
                foreach ($unique as $name) {
                    $tag = $this->findOrCreate($name);
                    $ins->execute([$photoId, $tag['id']]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Видалити теги, які більше нікому не належать.
     * Викликаємо після правок фото, щоб не накопичувати «мертві» теги.
     */
    public function deleteUnused(): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM tags
              WHERE id NOT IN (SELECT DISTINCT tag_id FROM photo_tags)'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}

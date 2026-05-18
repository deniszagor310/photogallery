<?php
namespace App\Models;

use App\Core\Model;

/**
 * app/Models/Album.php
 *
 * Робота з таблицею albums.
 */
final class Album extends Model
{
    protected string $table = 'albums';

    /**
     * Усі альбоми з кількістю фото і шляхом до обкладинки.
     * Обкладинка береться:
     *   1) з albums.cover_photo_id, якщо задана;
     *   2) інакше — найновіше фото в альбомі.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWithCounts(): array
    {
        $sql = "
            SELECT
                a.*,
                (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id) AS photos_count,
                (SELECT thumb_path FROM photos p
                   WHERE p.album_id = a.id
                   ORDER BY (p.id = a.cover_photo_id) DESC, p.created_at DESC
                   LIMIT 1) AS cover_thumb
            FROM albums a
            ORDER BY a.created_at DESC
        ";
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Альбом за id з кількістю фото.
     */
    public function findWithCount(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id) AS photos_count
               FROM albums a
              WHERE a.id = ?
              LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Перевірка унікальності slug. Можемо опційно ігнорувати один запис
     * (потрібно під час edit, щоб альбом не «зіткнувся» сам із собою).
     */
    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM albums WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM albums WHERE slug = ? AND id <> ? LIMIT 1'
            );
            $stmt->execute([$slug, $exceptId]);
        }
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Створити альбом. Повертає id нового запису.
     *
     * @param array{title:string, slug:string, description:?string} $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO albums (title, slug, description) VALUES (:title, :slug, :description)'
        );
        $stmt->execute([
            ':title'       => $data['title'],
            ':slug'        => $data['slug'],
            ':description' => $data['description'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Оновити альбом.
     *
     * @param array{title:string, slug:string, description:?string, cover_photo_id?:?int} $data
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE albums
                SET title          = :title,
                    slug           = :slug,
                    description    = :description,
                    cover_photo_id = :cover
              WHERE id = :id'
        );
        $stmt->execute([
            ':title'       => $data['title'],
            ':slug'        => $data['slug'],
            ':description' => $data['description'] ?? null,
            ':cover'       => $data['cover_photo_id'] ?? null,
            ':id'          => $id,
        ]);
    }
}

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
}

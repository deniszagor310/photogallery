<?php
namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * app/Models/Photo.php
 *
 * Робота з таблицею photos. Усі запити — через prepared statements.
 * Жодного SQL з конкатенацією користувацьких значень.
 */
final class Photo extends Model
{
    protected string $table = 'photos';

    /**
     * Останні N фото — для головної.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 12): array
    {
        $limit = max(1, min(60, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT p.*, a.title AS album_title, a.slug AS album_slug
               FROM photos p
               LEFT JOIN albums a ON a.id = p.album_id
              ORDER BY p.created_at DESC
              LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Список фото з пагінацією, опційним фільтром по альбому
     * і опційним пошуком по назві/опису/тегу.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 24,
        ?int $albumId = null,
        ?string $search = null
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(60, $perPage));
        $offset  = ($page - 1) * $perPage;

        // Збираємо WHERE-частину динамічно, але значення — лише через :placeholders.
        $where  = [];
        $params = [];

        if ($albumId !== null) {
            $where[] = 'p.album_id = :album_id';
            $params[':album_id'] = $albumId;
        }

        if ($search !== null && trim($search) !== '') {
            // Простий і безпечний LIKE-пошук. Шукаємо ще і по тегах
            // через підзапит — щоб одна форма шукала і по тексту, і по тегу.
            // Увага: при ATTR_EMULATE_PREPARES=false один іменований placeholder
            // не можна використовувати кілька разів — тому беремо 3 різні.
            $needle = '%' . $search . '%';
            $where[] = '(
                p.title       LIKE :q1
             OR p.description LIKE :q2
             OR EXISTS (
                  SELECT 1 FROM photo_tags pt
                  JOIN tags t ON t.id = pt.tag_id
                  WHERE pt.photo_id = p.id AND t.name LIKE :q3
                )
            )';
            $params[':q1'] = $needle;
            $params[':q2'] = $needle;
            $params[':q3'] = $needle;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Загальна кількість для пагінації
        $countSql = "SELECT COUNT(*) FROM photos p {$whereSql}";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // Дані сторінки (LIMIT/OFFSET підставляємо як int, не через bindValue,
        // бо емуляція вимкнена і деякі версії MySQL не люблять "?" у LIMIT).
        $sql = "SELECT p.*, a.title AS album_title, a.slug AS album_slug
                  FROM photos p
                  LEFT JOIN albums a ON a.id = p.album_id
                {$whereSql}
                ORDER BY p.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return [
            'items'     => $stmt->fetchAll(),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * Фото з усіма деталями (включно з альбомом і тегами).
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*,
                    a.id    AS album_id,
                    a.title AS album_title,
                    a.slug  AS album_slug
               FROM photos p
               LEFT JOIN albums a ON a.id = p.album_id
              WHERE p.id = ?
              LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) return null;

        $row['tags'] = $this->tagsForPhoto($id);
        return $row;
    }

    /**
     * Фото з конкретного альбому.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forAlbum(int $albumId, ?int $limit = null): array
    {
        $sql = "SELECT * FROM photos WHERE album_id = ? ORDER BY created_at DESC";
        if ($limit !== null) {
            $limit = max(1, (int)$limit);
            $sql  .= " LIMIT {$limit}";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$albumId]);
        return $stmt->fetchAll();
    }

    /**
     * Кількість фото в альбомі.
     */
    public function countForAlbum(int $albumId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM photos WHERE album_id = ?");
        $stmt->execute([$albumId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Назви тегів для фото.
     *
     * @return array<int, array<string, string>>
     */
    public function tagsForPhoto(int $photoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.name, t.slug
               FROM tags t
               JOIN photo_tags pt ON pt.tag_id = t.id
              WHERE pt.photo_id = ?
              ORDER BY t.name"
        );
        $stmt->execute([$photoId]);
        return $stmt->fetchAll();
    }

    /**
     * Інкрементувати лічильник скачувань.
     * Викликається з PhotoController::download() (Етап 5).
     */
    public function incrementDownloads(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE photos SET downloads_count = downloads_count + 1 WHERE id = ?"
        );
        $stmt->execute([$id]);
    }

    /**
     * Чи існує запис з таким slug (для перевірки унікальності перед UPDATE).
     */
    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM photos WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM photos WHERE slug = ? AND id <> ? LIMIT 1');
            $stmt->execute([$slug, $exceptId]);
        }
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Оновити метадані фото. Шляхи / розміри / mime НЕ чіпаємо — їх
     * виставляє Upload-логіка (Етап 8) і ImageService (Етап 9).
     *
     * @param array{
     *   album_id:?int, title:string, slug:string, description:?string,
     *   camera_model:?string, lens_model:?string,
     *   iso:?int, aperture:?string, shutter_speed:?string, taken_at:?string
     * } $data
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE photos
                SET album_id      = :album_id,
                    title         = :title,
                    slug          = :slug,
                    description   = :description,
                    camera_model  = :camera_model,
                    lens_model    = :lens_model,
                    iso           = :iso,
                    aperture      = :aperture,
                    shutter_speed = :shutter_speed,
                    taken_at      = :taken_at
              WHERE id = :id'
        );
        $stmt->execute([
            ':album_id'      => $data['album_id'] ?? null,
            ':title'         => $data['title'],
            ':slug'          => $data['slug'],
            ':description'   => $data['description'] ?? null,
            ':camera_model'  => $data['camera_model'] ?? null,
            ':lens_model'    => $data['lens_model'] ?? null,
            ':iso'           => $data['iso'] ?? null,
            ':aperture'      => $data['aperture'] ?? null,
            ':shutter_speed' => $data['shutter_speed'] ?? null,
            ':taken_at'      => $data['taken_at'] ?? null,
            ':id'            => $id,
        ]);
    }

    /**
     * Створити новий запис фото. Викликається після того, як Upload-сервіс
     * уже зберіг фізичні файли на диск (storage/originals, public/uploads/...).
     *
     * @param array{
     *   album_id:?int, title:string, slug:string, description:?string,
     *   original_path:string, large_path:string, thumb_path:string,
     *   original_filename:string, stored_filename:string,
     *   original_size:int, mime_type:string,
     *   width:?int, height:?int,
     *   camera_model:?string, lens_model:?string,
     *   iso:?int, aperture:?string, shutter_speed:?string, taken_at:?string
     * } $data
     *
     * @return int id новоствореного фото
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO photos
                (album_id, title, slug, description,
                 original_path, large_path, thumb_path,
                 original_filename, stored_filename,
                 original_size, mime_type, width, height,
                 camera_model, lens_model, iso, aperture, shutter_speed, taken_at)
             VALUES
                (:album_id, :title, :slug, :description,
                 :original_path, :large_path, :thumb_path,
                 :original_filename, :stored_filename,
                 :original_size, :mime_type, :width, :height,
                 :camera_model, :lens_model, :iso, :aperture, :shutter_speed, :taken_at)'
        );
        $stmt->execute([
            ':album_id'          => $data['album_id'] ?? null,
            ':title'             => $data['title'],
            ':slug'              => $data['slug'],
            ':description'       => $data['description'] ?? null,
            ':original_path'     => $data['original_path'],
            ':large_path'        => $data['large_path'],
            ':thumb_path'        => $data['thumb_path'],
            ':original_filename' => $data['original_filename'],
            ':stored_filename'   => $data['stored_filename'],
            ':original_size'     => (int)$data['original_size'],
            ':mime_type'         => $data['mime_type'],
            ':width'             => $data['width'] ?? null,
            ':height'            => $data['height'] ?? null,
            ':camera_model'      => $data['camera_model'] ?? null,
            ':lens_model'        => $data['lens_model'] ?? null,
            ':iso'               => $data['iso'] ?? null,
            ':aperture'          => $data['aperture'] ?? null,
            ':shutter_speed'     => $data['shutter_speed'] ?? null,
            ':taken_at'          => $data['taken_at'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Найновіші N — для адмін-дашборду.
     *
     * @return array<int, array<string, mixed>>
     */
    public function latest(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $sql = "SELECT p.id, p.title, p.slug, p.thumb_path, p.created_at, p.downloads_count,
                       a.title AS album_title
                  FROM photos p
                  LEFT JOIN albums a ON a.id = p.album_id
                 ORDER BY p.created_at DESC
                 LIMIT {$limit}";
        return $this->pdo->query($sql)->fetchAll();
    }
}

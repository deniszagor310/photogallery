-- database/schema.sql
--
-- Повна схема БД для photogallery + seed-користувач адмін.
--
-- Як виконати на WAMP:
--   1) Відкрити phpMyAdmin (іконка WAMP-tray → phpMyAdmin).
--   2) У вкладці "SQL" вставити вміст цього файлу і натиснути Go.
--   3) Або з командного рядка:
--        mysql -u root -p < database/schema.sql
--
-- ВАЖЛИВО про адмін-пароль:
--   Внизу файлу — INSERT для адміна з готовим bcrypt-хешем (login: admin,
--   пароль: admin12345). Це лише для розробки! Після першого входу
--   ОБОВ'ЯЗКОВО згенеруй власний хеш і заміни його:
--     php bin/hash.php "новий-пароль"
--   А потім UPDATE password_hash у БД.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS photogallery
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE photogallery;

-- =============================
-- 1) Користувачі (тільки адміни)
-- =============================
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================
-- 2) Альбоми
-- =============================
CREATE TABLE IF NOT EXISTS albums (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(150) NOT NULL,
  slug           VARCHAR(160) NOT NULL UNIQUE,
  description    TEXT NULL,
  cover_photo_id INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_albums_slug (slug)
) ENGINE=InnoDB;

-- =============================
-- 3) Фото
-- =============================
CREATE TABLE IF NOT EXISTS photos (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id          INT UNSIGNED NULL,

  title             VARCHAR(200) NOT NULL,
  slug              VARCHAR(220) NOT NULL UNIQUE,
  description       TEXT NULL,

  -- Шляхи відносно кореня проєкту, напр. 'storage/originals/abcd.jpg'
  original_path     VARCHAR(255) NOT NULL,
  large_path        VARCHAR(255) NOT NULL,
  thumb_path        VARCHAR(255) NOT NULL,

  original_filename VARCHAR(255) NOT NULL, -- як назвав файл користувач
  stored_filename   VARCHAR(120) NOT NULL, -- наша випадкова назва на диску
  original_size     INT UNSIGNED NOT NULL, -- байти
  mime_type         VARCHAR(50)  NOT NULL, -- image/jpeg, image/png, image/webp
  width             INT UNSIGNED NULL,
  height            INT UNSIGNED NULL,

  -- EXIF
  camera_model      VARCHAR(120) NULL,
  lens_model        VARCHAR(120) NULL,
  iso               INT UNSIGNED NULL,
  aperture          VARCHAR(20)  NULL,     -- "f/2.8"
  shutter_speed     VARCHAR(20)  NULL,     -- "1/250"
  taken_at          DATETIME     NULL,

  downloads_count   INT UNSIGNED NOT NULL DEFAULT 0,

  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_photos_album
    FOREIGN KEY (album_id) REFERENCES albums(id)
    ON DELETE SET NULL ON UPDATE CASCADE,

  INDEX idx_photos_album   (album_id),
  INDEX idx_photos_created (created_at),
  INDEX idx_photos_taken   (taken_at),
  FULLTEXT KEY ft_photos_title_desc (title, description)
) ENGINE=InnoDB;

-- Зв'язок "альбом → обкладинка-фото".
-- Додаємо ПІСЛЯ створення photos, щоб уникнути проблеми "курка чи яйце".
-- Перевіряємо існування FK, щоб скрипт можна було запускати повторно.
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'albums'
    AND CONSTRAINT_NAME = 'fk_albums_cover'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE albums
     ADD CONSTRAINT fk_albums_cover
     FOREIGN KEY (cover_photo_id) REFERENCES photos(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =============================
-- 4) Теги
-- =============================
CREATE TABLE IF NOT EXISTS tags (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80)  NOT NULL UNIQUE,
  slug       VARCHAR(100) NOT NULL UNIQUE,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================
-- 5) Зв'язок many-to-many фото ↔ теги
-- =============================
CREATE TABLE IF NOT EXISTS photo_tags (
  photo_id INT UNSIGNED NOT NULL,
  tag_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (photo_id, tag_id),
  CONSTRAINT fk_pt_photo
    FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_tag
    FOREIGN KEY (tag_id)   REFERENCES tags(id)   ON DELETE CASCADE,
  INDEX idx_pt_tag (tag_id)
) ENGINE=InnoDB;

-- =============================
-- Seed: адмін
-- login:    admin
-- password: admin12345
-- Хеш згенеровано так:
--   php -r "echo password_hash('admin12345', PASSWORD_BCRYPT, ['cost'=>10]);"
-- Знадобиться змінити після першого входу!
-- =============================
INSERT INTO users (username, email, password_hash)
SELECT 'admin', 'admin@example.com',
       '$2y$10$M0c2.cam2S5qrW62fBgCdevtoCspuZ0w0yZ4GmPxVFQA/r1UBQzX2'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

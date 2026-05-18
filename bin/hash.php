#!/usr/bin/env php
<?php
/**
 * bin/hash.php
 *
 * Маленька CLI-утиліта для генерації bcrypt-хешу пароля.
 *
 * Використання:
 *   php bin/hash.php "мій-пароль"
 *
 * Виведе хеш, який можна вставити у users.password_hash в БД.
 *
 * Чому це потрібно: ніколи НЕ зберігай паролі у відкритому вигляді.
 * PHP password_hash() сама додає сіль і використовує bcrypt.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Цей скрипт треба запускати з командного рядка.\n");
    exit(1);
}

if ($argc < 2 || trim((string)$argv[1]) === '') {
    fwrite(STDERR, "Використання: php bin/hash.php \"новий-пароль\"\n");
    exit(1);
}

$password = (string)$argv[1];
if (strlen($password) < 8) {
    fwrite(STDERR, "Попередження: пароль коротший за 8 символів — це слабко.\n");
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
if ($hash === false) {
    fwrite(STDERR, "Помилка генерації хешу.\n");
    exit(2);
}

echo $hash . PHP_EOL;

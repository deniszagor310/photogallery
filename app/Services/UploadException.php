<?php
namespace App\Services;

/**
 * app/Services/UploadException.php
 *
 * Виняток із зрозумілим повідомленням для користувача, який можна
 * безпечно показати у flash-меседжі. Усі шляхи й технічні деталі
 * НЕ потрапляють сюди — лише причина «чому файл відхилили».
 */
final class UploadException extends \RuntimeException
{
}

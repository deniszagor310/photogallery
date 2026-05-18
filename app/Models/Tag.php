<?php
namespace App\Models;

use App\Core\Model;

/**
 * app/Models/Tag.php
 *
 * Робота з таблицею tags та зв'язком photo_tags.
 */
final class Tag extends Model
{
    protected string $table = 'tags';
}

<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Photo;

/**
 * app/Controllers/PhotoController.php
 *
 *  GET /photo/{id}          — сторінка фото (показуємо ОПТИМІЗОВАНУ large-версію!)
 *  GET /photo/download/{id} — скачування оригіналу (Етап 5).
 */
final class PhotoController extends Controller
{
    public function show(string $id): void
    {
        $id = (int)$id;
        if ($id < 1) $this->abort(404, 'Фото не знайдено');

        $photo = (new Photo())->findWithDetails($id);
        if (!$photo) $this->abort(404, 'Фото не знайдено');

        $this->render('photo/show', [
            'title' => $photo['title'],
            'photo' => $photo,
        ]);
    }
}

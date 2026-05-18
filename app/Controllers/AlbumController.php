<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Album;
use App\Models\Photo;

/**
 * app/Controllers/AlbumController.php
 *
 *  GET /albums      — список альбомів з обкладинками і кількістю фото
 *  GET /album/{id}  — сторінка окремого альбому з його фото
 */
final class AlbumController extends Controller
{
    public function index(): void
    {
        $albums = (new Album())->allWithCounts();
        $this->render('albums/index', [
            'title'  => 'Альбоми',
            'albums' => $albums,
        ]);
    }

    public function show(string $id): void
    {
        $id = (int)$id;
        if ($id < 1) $this->abort(404, 'Альбом не знайдено');

        $albumModel = new Album();
        $album = $albumModel->findWithCount($id);
        if (!$album) $this->abort(404, 'Альбом не знайдено');

        $photos = (new Photo())->forAlbum($id);

        $this->render('albums/show', [
            'title'  => $album['title'],
            'album'  => $album,
            'photos' => $photos,
        ]);
    }
}

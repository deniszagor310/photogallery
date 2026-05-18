<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Album;
use App\Models\Photo;

/**
 * app/Controllers/GalleryController.php
 *
 * /gallery — сітка з усіма фото:
 *   ?album={id} — фільтр по альбому
 *   ?q=...     — пошук по назві/опису/тегу
 *   ?page=N    — пагінація (по 24 фото на сторінці)
 */
final class GalleryController extends Controller
{
    public function index(): void
    {
        $albumId = isset($_GET['album']) ? (int)$_GET['album'] : null;
        if ($albumId !== null && $albumId < 1) $albumId = null;

        $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        // Захист від занадто довгих query string і ALC-знаків
        if ($q !== '') {
            $q = mb_substr($q, 0, 100, 'UTF-8');
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $photo = new Photo();
        $result = $photo->paginate(
            page:    $page,
            perPage: 24,
            albumId: $albumId,
            search:  $q !== '' ? $q : null,
        );

        $albums = (new Album())->all();

        $this->render('gallery/index', [
            'title'        => 'Галерея',
            'items'        => $result['items'],
            'total'        => $result['total'],
            'page'         => $result['page'],
            'lastPage'     => $result['last_page'],
            'perPage'      => $result['per_page'],
            'albums'       => $albums,
            'selectedAlbum'=> $albumId,
            'searchQuery'  => $q,
        ]);
    }
}

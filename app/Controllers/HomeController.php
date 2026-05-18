<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Photo;

/**
 * app/Controllers/HomeController.php
 *
 * Головна сторінка: вступний блок + останні N фото у сітці +
 * посилання на /gallery та /albums.
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        $photoModel = new Photo();

        $this->render('home/index', [
            'title'       => 'Photo Gallery',
            'recentPhotos' => $photoModel->recent(12),
        ]);
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Services\CategoryService;

final class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories)
    {
    }

    public function findMany(Request $request): Response
    {
        // Referentiel stable : cache partage 1 h (une modification du back-office, SCRUM-78, se voit au plus 1 h apres).
        return $this->ok($this->categories->findActive())->withHeader('Cache-Control', 'public, max-age=3600');
    }
}

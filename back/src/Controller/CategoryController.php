<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Http\CacheControl;
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
        return $this->ok($this->categories->findActive())->withHeader('Cache-Control', CacheControl::REFERENCE_DATA);
    }
}

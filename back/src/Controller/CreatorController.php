<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Query\CreatorFindQuery;
use App\Model\Services\CreatorService;

final class CreatorController extends Controller
{
    private ?CreatorService $creators;

    public function __construct(?CreatorService $creators = null)
    {
        $this->creators = $creators;
    }

    public function index(Request $request): Response
    {
        $query = CreatorFindQuery::fromQuery($request->query());

        return $this->ok($this->creators()->searchPage($query->toParams()));
    }

    private function creators(): CreatorService
    {
        return $this->creators ??= new CreatorService();
    }
}

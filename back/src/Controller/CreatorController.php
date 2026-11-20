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
    public function __construct(private readonly CreatorService $creators)
    {
    }

    public function index(Request $request): Response
    {
        $query = CreatorFindQuery::fromQuery($request->query());

        return $this->ok($this->creators->searchPage($query->toParams()));
    }
}

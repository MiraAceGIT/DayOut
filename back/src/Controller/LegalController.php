<?php declare(strict_types=1);

namespace App\Controller;

use App\Model\Query\LegalPageQuery;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Services\LegalService;

final class LegalController extends Controller
{
    private LegalService $legalService;

    public function __construct(?LegalService $legalService = null)
    {
        $this->legalService = $legalService ?? new LegalService();
    }

    public function show(Request $request): Response
    {
    $query = LegalPageQuery::fromParams(['type' => $request->param('type')]);

    return $this->ok($this->legalService->getByType($query->type));
    }

}

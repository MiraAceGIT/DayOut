<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Dto\Faq\FaqCreateDto;
use App\Model\Dto\Faq\FaqUpdateDto;
use App\Model\Query\FaqFindQuery;
use App\Model\Services\FaqService;

final class FaqController extends Controller
{
    public function __construct(private readonly FaqService $faq)
    {
    }

    public function findMany(Request $request): Response
    {
        $query = FaqFindQuery::fromQuery($request->query());

        return $this->ok($this->faq->findMany($query->toParams()));
    }

    public function findOne(Request $request): Response
    {
        $id = $this->faqId($request);

        return $this->ok($this->faq->findOne($id));
    }

    public function create(Request $request): Response
    {
        $userId = $this->userId($request);
        $dto = FaqCreateDto::fromArray($request->json());

        return $this->ok($this->faq->create($dto, $userId), 201);
    }

    public function update(Request $request): Response
    {
        $userId = $this->userId($request);
        $id = $this->faqId($request);
        $dto = FaqUpdateDto::fromArray($request->json());

        return $this->ok($this->faq->update($id, $dto, $userId));
    }

    public function delete(Request $request): Response
    {
        $userId = $this->userId($request);
        $this->faq->delete($this->faqId($request), $userId);

        return $this->noContent();
    }

    private function faqId(Request $request): int
    {
        return $this->idParam($request, 'FAQ entry not found');
    }
}

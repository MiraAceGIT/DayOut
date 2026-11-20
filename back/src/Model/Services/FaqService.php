<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Core\Log\Log;
use App\Model\Dao\Faq\Faq;
use App\Model\Dao\Faq\FaqAdmin;
use App\Model\Dao\Faq\FaqSearchParams;
use App\Model\Dto\Faq\FaqCreateDto;
use App\Model\Dto\Faq\FaqDto;
use App\Model\Dto\Faq\FaqUpdateDto;
use App\Model\Dto\PageDto;
use App\Model\Exceptions\FaqException;
use App\Model\Mappers\FaqMapper;

final class FaqService
{
    private Faq $faq;
    private FaqAdmin $admins;

    public function __construct(?Faq $faq = null, ?FaqAdmin $admins = null)
    {
        $this->faq    = $faq ?? new Faq();
        $this->admins = $admins ?? new FaqAdmin();
    }

    /** @return PageDto<FaqDto> */
    public function findMany(FaqSearchParams $params): PageDto
    {
        [$rows, $total] = $this->faq->findManyVisible($params);

        return PageDto::of(FaqMapper::toDtos($rows), $params->limit, $params->offset, $total);
    }

    public function findOne(int $id): FaqDto
    {
        return FaqMapper::toDto($this->faq->findVisibleById($id) ?? throw FaqException::notFound($id));
    }

    public function create(FaqCreateDto $dto, int $userId): FaqDto
    {
        $this->assertCanManageFaq($userId);
        $id = $this->faq->create(FaqMapper::toRow($dto));
        Log::audit('faq.create', 'faq', $id, 'success', ['user_id' => $userId]);

        return $this->findForWrite($id);
    }

    public function update(int $id, FaqUpdateDto $dto, int $userId): FaqDto
    {
        $this->assertCanManageFaq($userId);
        $this->findForWrite($id);
        $this->faq->updateById($id, FaqMapper::toUpdateRow($dto));
        Log::audit('faq.update', 'faq', $id, 'success', ['user_id' => $userId, 'fields' => $dto->provided]);

        return $this->findForWrite($id);
    }

    public function delete(int $id, int $userId): void
    {
        $this->assertCanManageFaq($userId);
        if ($this->faq->deleteById($id) === 0) {
            throw FaqException::notFound($id);
        }
        Log::audit('faq.delete', 'faq', $id, 'success', ['user_id' => $userId]);
    }

    private function assertCanManageFaq(int $userId): void
    {
        if (!$this->admins->existsById($userId)) {
            throw FaqException::adminRequired();
        }
    }

    private function findForWrite(int $id): FaqDto
    {
        return FaqMapper::toDto($this->faq->findForWriteById($id) ?? throw FaqException::notFound($id));
    }
}

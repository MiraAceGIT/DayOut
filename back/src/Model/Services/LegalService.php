<?php declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Dto\Legal\LegalPageDto;
use App\Model\Dao\Legal\LegalPage;
use App\Model\Exceptions\LegalException;
use App\Model\Mappers\LegalPageMapper;

final class LegalService
{
    private LegalPage $legalPages;

    public function __construct(?LegalPage $legalPages =null)
    {
        $this->legalPages = $legalPages ?? new LegalPage();
    }

    public function getByType(string $type): LegalPageDto
    {
        $row = $this->legalPages->findBySlug($type) ?? throw LegalException::notFound($type);

        return LegalPageMapper::toDo($row);
    }
}
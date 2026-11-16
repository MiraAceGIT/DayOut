<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Dao\Faq\Faq;
use App\Model\Dto\Faq\FaqDto;

final class FaqService
{
    private Faq $faq;

    public function __construct(?Faq $faq = null)
    {
        $this->faq = $faq ?? new Faq();
    }

    /** @return array<int, FaqDto> */
    public function listVisible(): array
    {
        return array_map(
            static fn (array $row): FaqDto => new FaqDto($row['id'], $row['question'], $row['answer']),
            $this->faq->findVisible(),
        );
    }
}

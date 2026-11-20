<?php
declare(strict_types=1);

namespace App\Model\Dto\Faq;

use JsonSerializable;

final readonly class FaqDto implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $question,
        public string $answer,
        public int $sortOrder,
        public bool $active,
    ) {
    }

    /** @return array{id: int, question: string, answer: string, sort_order: int, active: bool} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'sort_order' => $this->sortOrder,
            'active' => $this->active,
        ];
    }
}

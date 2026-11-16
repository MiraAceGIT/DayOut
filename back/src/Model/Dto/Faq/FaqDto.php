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
    ) {
    }

    /** @return array{id: int, question: string, answer: string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'question' => $this->question, 'answer' => $this->answer];
    }
}

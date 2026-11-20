<?php
declare(strict_types=1);

namespace App\Model\Dto\Faq;

use App\Core\Validator;

final readonly class FaqCreateDto
{
    public const RULES = [
        'question'   => 'required|string|max:255',
        'answer'     => 'required|string|max:16000',
        'sort_order' => 'int|min:0|max:' . Validator::INT_UNSIGNED_MAX,
        'active'     => 'bool',
    ];

    public function __construct(
        public string $question,
        public string $answer,
        public int $sortOrder = 0,
        public bool $active = true,
    ) {
    }

    public static function fromArray(array $json): self
    {
        $clean = Validator::check($json, self::RULES);

        return new self(
            question: $clean['question'],
            answer: $clean['answer'],
            sortOrder: $clean['sort_order'] ?? 0,
            active: $clean['active'] ?? true,
        );
    }
}

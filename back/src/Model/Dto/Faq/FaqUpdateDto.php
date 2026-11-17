<?php
declare(strict_types=1);

namespace App\Model\Dto\Faq;

use App\Core\ValidationException;
use App\Core\Validator;

final readonly class FaqUpdateDto
{
    public const RULES = [
        'question'   => 'filled|string|max:255',
        'answer'     => 'filled|string|max:16000',
        'sort_order' => 'int|min:0|max:4294967295',
        'active'     => 'bool',
    ];

    /** @param list<string> $provided */
    public function __construct(
        public array $provided = [],
        public ?string $question = null,
        public ?string $answer = null,
        public ?int $sortOrder = null,
        public ?bool $active = null,
    ) {
    }

    public static function fromArray(array $json): self
    {
        $clean = Validator::check($json, self::RULES);
        if ($clean === []) {
            throw ValidationException::field('body', 'No writable field (allowed: ' . implode(', ', array_keys(self::RULES)) . ')');
        }

        return new self(
            provided: array_keys($clean),
            question: $clean['question'] ?? null,
            answer: $clean['answer'] ?? null,
            sortOrder: $clean['sort_order'] ?? null,
            active: $clean['active'] ?? null,
        );
    }
}

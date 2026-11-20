<?php
declare(strict_types=1);

namespace App\Model\Dto\Faq;

use App\Core\ValidationException;
use App\Core\Validator;

final readonly class FaqUpdateDto
{
    /** @return array<string, string> regles de FaqCreateDto, rien d'obligatoire */
    public static function rules(): array
    {
        return Validator::forUpdate(FaqCreateDto::RULES);
    }

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
        $rules = self::rules();
        $clean = Validator::check($json, $rules);
        if ($clean === []) {
            throw ValidationException::field('body', 'No writable field (allowed: ' . implode(', ', array_keys($rules)) . ')');
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

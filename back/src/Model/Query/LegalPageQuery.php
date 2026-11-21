<?php
declare(strict_types=1);

namespace App\Model\Query;

use App\Core\Validator;

final readonly class LegalPageQuery
{
    public const RULES = [
        'type' => 'required|string|in:cgu,mentions',
    ];

    public function __construct(
        public string $type,
    ) {
    }

    /** @param array<string, mixed> $params */
    
    public static function fromParams(array $params): self
    {
        $clean = Validator::check($params, self::RULES);

        return new self(
            type: (string) $clean['type'],
        );
    }
}

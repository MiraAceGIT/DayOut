<?php
declare(strict_types=1);

namespace App\Model\DomainServices;

use DateTimeImmutable;

/** Jeton tout juste emis : `value` part dans le lien (jamais stocke), `hash` va en base. */
final readonly class IssuedToken
{
    public function __construct(
        public string $value,
        public string $hash,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}

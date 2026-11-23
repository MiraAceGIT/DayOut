<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/** Heure courante, injectee (SystemClock en vrai, horloge fixe en test) : expirations testables sans attendre ni toucher la base. */
interface Clock
{
    /** Toujours en UTC. */
    public function now(): DateTimeImmutable;
}

<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\Clock;
use DateTimeImmutable;
use DateTimeZone;

/** Horloge des tests : figee, avancee a la main (expirations sans SQL ni attente). */
final class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $now = '2026-11-23 10:00:00')
    {
        $this->now = new DateTimeImmutable($now, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /** @param string $interval format DateInterval, ex. PT24H */
    public function advance(string $interval): void
    {
        $this->now = $this->now->add(new \DateInterval($interval));
    }
}

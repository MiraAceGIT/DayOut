<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Clock;
use App\Core\Orm\Connection;
use DateInterval;

/**
 * Compteur de requetes par cle (IP) et par usage (`bucket`), en base (table `rate_limit_hits`) : partage entre
 * processus PHP, sans dependance. La cle est hashee (SHA-256) : aucune IP en clair.
 */
final class RateLimiter
{
    private const TABLE = 'rate_limit_hits';

    public function __construct(private readonly Connection $connection, private readonly Clock $clock)
    {
    }

    public function isExceeded(string $bucket, string $key, int $maxHits, int $windowSeconds): bool
    {
        return $this->connection->table(self::TABLE)
            ->where('bucket', $bucket)
            ->where('key_hash', hash('sha256', $key))
            ->where('hit_at', '>', $this->windowStart($windowSeconds))
            ->count() >= $maxHits;
    }

    /** Compte une requete ; purge au passage ce qui est sorti de la fenetre (pas de cron). */
    public function hit(string $bucket, string $key, int $windowSeconds): void
    {
        $this->connection->table(self::TABLE)->insert(['bucket' => $bucket, 'key_hash' => hash('sha256', $key), 'hit_at' => $this->clock->now()]);
        $this->connection->table(self::TABLE)->where('bucket', $bucket)->where('hit_at', '<=', $this->windowStart($windowSeconds))->delete();
    }

    private function windowStart(int $windowSeconds): \DateTimeImmutable
    {
        return $this->clock->now()->sub(new DateInterval('PT' . $windowSeconds . 'S'));
    }
}

<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Clock;
use App\Core\Orm\Connection;
use DateInterval;
use DateTimeImmutable;

/**
 * Compteur de requetes par cle (adresse du client) et par usage (`bucket`), en base (table `rate_limit_hits`) :
 * partage entre processus PHP, sans dependance. La cle est hashee (SHA-256) : pas d'adresse lisible d'un coup d'oeil
 * (pseudonymisation : une IPv4 se retrouve en essayant toutes les adresses).
 */
final class RateLimiter
{
    private const TABLE = 'rate_limit_hits';

    public function __construct(private readonly Connection $connection, private readonly Clock $clock)
    {
    }

    /** Compte une requete et renvoie son id (pour cancel) ; purge au passage ce qui est sorti de la fenetre (pas de cron). */
    public function hit(string $bucket, string $key, int $windowSeconds): int
    {
        $id = $this->connection->table(self::TABLE)->insert(['bucket' => $bucket, 'key_hash' => self::hash($key), 'hit_at' => $this->clock->now()]);
        $this->connection->table(self::TABLE)->where('bucket', $bucket)->where('hit_at', '<=', $this->windowStart($windowSeconds))->delete();
        return $id;
    }

    /** Requetes comptees pour cette cle dans la fenetre, y compris celles encore en cours de traitement. */
    public function count(string $bucket, string $key, int $windowSeconds): int
    {
        return $this->connection->table(self::TABLE)
            ->where('bucket', $bucket)
            ->where('key_hash', self::hash($key))
            ->where('hit_at', '>', $this->windowStart($windowSeconds))
            ->count();
    }

    /** Retire une requete du compte (refusee par la limite, ou qui ne devait finalement pas compter). */
    public function cancel(int $hitId): void
    {
        $this->connection->table(self::TABLE)->where('id', $hitId)->delete();
    }

    private static function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    private function windowStart(int $windowSeconds): DateTimeImmutable
    {
        return $this->clock->now()->sub(new DateInterval('PT' . $windowSeconds . 'S'));
    }
}

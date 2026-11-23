<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Orm\Connection;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Compteur de requetes par cle (adresse du client) et par usage (`bucket`), en base (table `rate_limit_hits`) :
 * partage entre processus PHP, sans dependance. La cle est stockee en HMAC-SHA256 avec RATE_LIMIT_SECRET : sans le
 * secret, impossible de retrouver une adresse en essayant toutes les IPv4 (un simple SHA-256 se renverse ainsi).
 */
final class RateLimiter
{
    private const TABLE = 'rate_limit_hits';

    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
        private readonly Config $config,
    ) {
    }

    /** Compte une requete et renvoie son id (pour cancel) ; purge au passage ce qui est sorti de la fenetre (pas de cron). */
    public function hit(string $bucket, string $key, int $windowSeconds): int
    {
        $id = $this->connection->table(self::TABLE)->insert(['bucket' => $bucket, 'key_hash' => $this->hash($key), 'hit_at' => $this->clock->now()]);
        $this->connection->table(self::TABLE)->where('bucket', $bucket)->where('hit_at', '<=', $this->windowStart($windowSeconds))->delete();
        return $id;
    }

    /** Requetes comptees pour cette cle dans la fenetre, y compris celles encore en cours de traitement. */
    public function count(string $bucket, string $key, int $windowSeconds): int
    {
        return $this->connection->table(self::TABLE)
            ->where('bucket', $bucket)
            ->where('key_hash', $this->hash($key))
            ->where('hit_at', '>', $this->windowStart($windowSeconds))
            ->count();
    }

    /** Secondes avant que la plus ancienne requete de la fenetre n'en sorte (et libere une place) ; au moins 1. */
    public function secondsUntilFree(string $bucket, string $key, int $windowSeconds): int
    {
        $oldest = $this->connection->table(self::TABLE)
            ->where('bucket', $bucket)
            ->where('key_hash', $this->hash($key))
            ->where('hit_at', '>', $this->windowStart($windowSeconds))
            ->orderBy('hit_at')
            ->value('hit_at');
        if ($oldest === null) {
            return 1;
        }
        $freeAt = (new DateTimeImmutable((string) $oldest, new DateTimeZone('UTC')))->getTimestamp() + $windowSeconds;
        return max(1, $freeAt - $this->clock->now()->getTimestamp());
    }

    /** Retire une requete du compte (refusee par la limite, ou qui ne devait finalement pas compter). */
    public function cancel(int $hitId): void
    {
        $this->connection->table(self::TABLE)->where('id', $hitId)->delete();
    }

    private function hash(string $key): string
    {
        return hash_hmac('sha256', $key, $this->config->rateLimitSecret);
    }

    private function windowStart(int $windowSeconds): DateTimeImmutable
    {
        return $this->clock->now()->sub(new DateInterval('PT' . $windowSeconds . 'S'));
    }
}

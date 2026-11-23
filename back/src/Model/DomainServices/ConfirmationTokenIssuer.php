<?php
declare(strict_types=1);

namespace App\Model\DomainServices;

use App\Core\Clock;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Jetons des liens de confirmation : 32 octets aleatoires (64 caracteres hexadecimaux) envoyes par mail, dont seul
 * le SHA-256 est stocke, valables 24 h. Reutilisable pour d'autres liens a usage unique (mot de passe oublie).
 */
final class ConfirmationTokenIssuer
{
    public const TTL = 'PT24H';

    private const FORMAT = '/^[0-9a-f]{64}$/D';

    public function __construct(private readonly Clock $clock)
    {
    }

    public function issue(): IssuedToken
    {
        $token = bin2hex(random_bytes(32));
        return new IssuedToken($token, self::hash($token), $this->clock->now()->add(new DateInterval(self::TTL)));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Verifie avant la base : un lien tronque ou modifie ne coute aucune requete. */
    public static function isWellFormed(string $token): bool
    {
        return preg_match(self::FORMAT, $token) === 1;
    }

    /** @param string $expiresAt DATETIME UTC lu en base */
    public function isExpired(string $expiresAt): bool
    {
        return new DateTimeImmutable($expiresAt, new DateTimeZone('UTC')) <= $this->clock->now();
    }
}

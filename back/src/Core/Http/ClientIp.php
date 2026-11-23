<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Config;
use App\Core\Request;

/**
 * Adresse du client, pour la limitation de debit. Sans proxy, c'est REMOTE_ADDR. Derriere un reverse proxy (Nginx,
 * hebergeur), REMOTE_ADDR est celle du proxy pour tout le monde : on lit alors X-Forwarded-For, mais seulement quand
 * la requete vient d'un proxy declare dans TRUSTED_PROXIES (sinon l'en-tete se falsifie et contourne la limite).
 */
final class ClientIp
{
    public function __construct(private readonly Config $config)
    {
    }

    /** Cle de limitation de debit du client de $request (voir rateLimitKey). */
    public function keyOf(Request $request): string
    {
        return self::rateLimitKey(self::resolve(
            (string) $request->attribute(Request::REMOTE_ADDR, ''),
            $request->header('x-forwarded-for'),
            $this->config->trustedProxies,
        ));
    }

    /**
     * Chaque proxy ajoute a droite de X-Forwarded-For l'adresse de qui lui a parle : on remonte la chaine de droite a
     * gauche tant que l'adresse est celle d'un proxy de confiance. Ce qu'un client ecrit lui-meme dans l'en-tete est
     * a gauche, donc jamais lu. Entree illisible : on s'arrete au dernier proxy de confiance.
     *
     * @param list<string> $trustedProxies adresses ou plages CIDR
     * @return string adresse du client ('' si REMOTE_ADDR est absente ou illisible)
     */
    public static function resolve(string $remoteAddr, ?string $forwardedFor, array $trustedProxies): string
    {
        $client = self::normalize($remoteAddr);
        $hops = $forwardedFor === null ? [] : explode(',', $forwardedFor);
        while ($client !== '' && $hops !== [] && self::isTrusted($client, $trustedProxies)) {
            $previous = self::normalize(trim((string) array_pop($hops)));
            if ($previous === '') {
                break;
            }
            $client = $previous;
        }
        return $client;
    }

    /**
     * IPv4 : l'adresse. IPv6 : son /64, car un abonne recoit tout un /64 et changerait d'adresse a volonte pour
     * contourner une limite par adresse.
     */
    public static function rateLimitKey(string $ip): string
    {
        $binary = IpRange::binary($ip);
        if ($binary === null || strlen($binary) === 4) {
            return $ip;
        }
        return inet_ntop(IpRange::prefix($binary, 64)) . '/64';
    }

    /** @param list<string> $trustedProxies */
    private static function isTrusted(string $ip, array $trustedProxies): bool
    {
        foreach ($trustedProxies as $range) {
            if (IpRange::contains($range, $ip)) {
                return true;
            }
        }
        return false;
    }

    /** Forme canonique, '' si ce n'est pas une IP ; `::ffff:1.2.3.4` (IPv4 vue en IPv6) devient `1.2.3.4`. */
    private static function normalize(string $ip): string
    {
        $binary = IpRange::binary($ip);
        if ($binary === null) {
            return '';
        }
        if (strlen($binary) === 16 && str_starts_with($binary, str_repeat("\0", 10) . "\xFF\xFF")) {
            $binary = substr($binary, 12);
        }
        return (string) inet_ntop($binary);
    }
}

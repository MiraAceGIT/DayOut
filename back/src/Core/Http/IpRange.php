<?php
declare(strict_types=1);

namespace App\Core\Http;

/** Adresses IPv4 / IPv6 et plages CIDR (`10.0.0.0/8`, `2001:db8::/32`, ou une adresse seule). */
final class IpRange
{
    public static function isValid(string $range): bool
    {
        return self::parse($range) !== null;
    }

    /** Vrai si $ip appartient a $range ; une IPv4 n'appartient jamais a une plage IPv6 (et inversement). */
    public static function contains(string $range, string $ip): bool
    {
        $network = self::parse($range);
        $address = self::binary($ip);
        if ($network === null || $address === null || strlen($network[0]) !== strlen($address)) {
            return false;
        }
        return self::prefix($address, $network[1]) === self::prefix($network[0], $network[1]);
    }

    /** Forme binaire (4 ou 16 octets), null si ce n'est pas une adresse IP. */
    public static function binary(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $binary = inet_pton($ip);
        return $binary === false ? null : $binary;
    }

    /** Garde les $bits premiers bits, met les autres a zero (longueur inchangee). */
    public static function prefix(string $binary, int $bits): string
    {
        $bytes = intdiv($bits, 8);
        $kept = substr($binary, 0, $bytes);
        if ($bits % 8 !== 0) {
            $kept .= chr(ord($binary[$bytes]) & (0xFF << (8 - $bits % 8)) & 0xFF);
        }
        return str_pad($kept, strlen($binary), "\0");
    }

    /** @return array{0: string, 1: int}|null reseau binaire et longueur du prefixe */
    private static function parse(string $range): ?array
    {
        $parts = explode('/', trim($range), 2);
        $network = self::binary($parts[0]);
        if ($network === null) {
            return null;
        }
        $max = strlen($network) * 8;
        if (!isset($parts[1])) {
            return [$network, $max];
        }
        if (preg_match('/^\d{1,3}$/D', $parts[1]) !== 1 || (int) $parts[1] > $max) {
            return null;
        }
        return [$network, (int) $parts[1]];
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

final class Password
{
    private const COST = 12;

    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }
}

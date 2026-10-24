<?php
declare(strict_types=1);

namespace App\Core\Orm;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use LogicException;

/**
 * Casts appliques a la lecture, pour un contrat JSON coherent :
 *  - booleens -> bool (plutot que 0/1 renvoyes par PDO MySQL)
 *  - entiers  -> int
 *  - dates    -> ISO 8601 UTC
 */
final class Caster
{
    /**
     * @param array<string, string> $casts colonne => int | float | bool | datetime | json
     * @param string $owner classe declarante, pour les messages d'erreur
     */
    public static function apply(array $row, array $casts, string $owner): array
    {
        foreach ($casts as $column => $type) {
            // Colonne non selectionnee ou NULL SQL : on ne touche pas.
            if (!array_key_exists($column, $row) || $row[$column] === null) {
                continue;
            }
            $row[$column] = self::cast($row[$column], $type, "$owner::$column");
        }
        return $row;
    }

    public static function cast(mixed $value, string $type, string $context): mixed
    {
        return match ($type) {
            'int'      => (int) $value,
            'float'    => (float) $value,
            'bool'     => (bool) $value,
            // La BDD stocke en UTC (SET time_zone='+00:00' dans le schema).
            'datetime' => (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))
                ->format(DateTimeInterface::ATOM),
            'json'     => json_decode((string) $value, true),
            default    => throw new LogicException("Unknown cast '$type' on $context"),
        };
    }
}

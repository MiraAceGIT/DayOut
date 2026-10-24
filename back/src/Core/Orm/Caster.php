<?php
declare(strict_types=1);

namespace App\Core\Orm;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use LogicException;

/** Casts a la lecture : PDO MySQL renvoie 0/1 et des chaines, l'API expose bool/int/ISO 8601 UTC. */
final class Caster
{
    /**
     * @param array<string, string> $casts colonne => int | float | bool | datetime | json
     * @param string $owner classe declarante, pour les messages d'erreur
     */
    public static function apply(array $row, array $casts, string $owner): array
    {
        foreach ($casts as $column => $type) {
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
            // Session MySQL en UTC (cf. Database::connection()).
            'datetime' => (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))
                ->format(DateTimeInterface::ATOM),
            'json'     => json_decode((string) $value, true),
            default    => throw new LogicException("Unknown cast '$type' on $context"),
        };
    }
}

<?php
declare(strict_types=1);

namespace App\Core\Orm;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOStatement;

/** Applique a tous les bindings (WHERE compris) : where('start_at', '>=', $date) fonctionne. */
final class ValueBinder
{
    /** Placeholders positionnels `?` : les cles du tableau sont ignorees. */
    public static function bindAll(PDOStatement $stmt, array $bindings): void
    {
        foreach (array_values($bindings) as $i => $value) {
            $stmt->bindValue($i + 1, self::normalize($value), self::pdoType($value));
        }
    }

    /** DateTime converti en UTC (fuseau de la session MySQL). */
    public static function normalize(mixed $value): mixed
    {
        if (is_bool($value)) {
            return (int) $value;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        }
        if ($value !== null && !is_scalar($value)) {
            // Typiquement where('id', $ids) au lieu de whereIn('id', $ids)
            throw new InvalidArgumentException('Unsupported binding type: ' . get_debug_type($value));
        }
        return $value;
    }

    public static function pdoType(mixed $value): int
    {
        if ($value === null) {
            return PDO::PARAM_NULL;
        }
        if (is_int($value) || is_bool($value)) {
            return PDO::PARAM_INT;
        }
        return PDO::PARAM_STR;
    }
}

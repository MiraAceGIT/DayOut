<?php
declare(strict_types=1);

namespace App\Core\Orm;

use LogicException;

/** Rattache en PHP des enfants charges en une requete whereIn() (anti N+1). */
final class Rows
{
    /** La derniere ligne gagne en cas de doublon. */
    public static function keyBy(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[self::keyOf($row, $key)] = $row;
        }
        return $indexed;
    }

    public static function groupBy(array $rows, string $key): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[self::keyOf($row, $key)][] = $row;
        }
        return $groups;
    }

    private static function keyOf(array $row, string $key): int|string
    {
        // Colonne oubliee dans le SELECT : echouer plutot que tout regrouper sous ''.
        if (!array_key_exists($key, $row)) {
            throw new LogicException("Missing key '$key' in row");
        }
        $value = $row[$key];
        return is_int($value) ? $value : (string) $value;
    }
}

<?php
declare(strict_types=1);

namespace App\Orm;

use LogicException;

/**
 * Assemblage de chargements batch (anti N+1) : on charge les enfants en une
 * requete whereIn(), puis on les rattache aux parents en PHP.
 *
 *   $byEvent = Rows::groupBy($registrations, 'event_id');
 *   $event['registrations'] = $byEvent[$event['id']] ?? [];
 */
final class Rows
{
    /** Une ligne par cle (la derniere gagne en cas de doublon). */
    public static function keyBy(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[self::keyOf($row, $key)] = $row;
        }
        return $indexed;
    }

    /** Liste de lignes par cle. */
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
        // Cle absente = colonne oubliee dans le SELECT : on echoue bruyamment
        // plutot que de tout regrouper sous ''.
        if (!array_key_exists($key, $row)) {
            throw new LogicException("Missing key '$key' in row");
        }
        $value = $row[$key];
        return is_int($value) ? $value : (string) $value;
    }
}

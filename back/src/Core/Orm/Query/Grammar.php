<?php
declare(strict_types=1);

namespace App\Core\Orm\Query;

use InvalidArgumentException;

/** Validation des fragments non bindables : identifiants par regex, mots-cles par whitelist. */
final class Grammar
{
    public const OPERATORS = ['=', '<>', '!=', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];

    /** Identifiant simple ou qualifie : `col`, `table.col`, `table.*`. */
    private const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*(\.([A-Za-z_][A-Za-z0-9_]*|\*))?$/';

    private const DIRECTIONS = ['ASC', 'DESC'];
    private const JOIN_TYPES = ['INNER', 'LEFT', 'RIGHT'];

    /** Accepte aussi 'col AS alias' -> `col` AS `alias`. */
    public static function wrap(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '*') {
            return '*';
        }

        if (preg_match('/^(.+)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $identifier, $m)) {
            return self::wrap($m[1]) . ' AS `' . $m[2] . '`';
        }

        if (!preg_match(self::IDENTIFIER, $identifier)) {
            throw new InvalidArgumentException("Invalid SQL identifier: $identifier");
        }

        return implode('.', array_map(
            static fn (string $part) => $part === '*' ? '*' : "`$part`",
            explode('.', $identifier)
        ));
    }

    /** @param string[] $identifiers */
    public static function wrapAll(array $identifiers): array
    {
        return array_map([self::class, 'wrap'], array_values($identifiers));
    }

    public static function operator(string $operator): string
    {
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Invalid SQL operator: $operator");
        }
        return $operator;
    }

    public static function direction(string $direction): string
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, self::DIRECTIONS, true)) {
            throw new InvalidArgumentException("Invalid order direction: $direction");
        }
        return $direction;
    }

    public static function joinType(string $type): string
    {
        $type = strtoupper($type);
        if (!in_array($type, self::JOIN_TYPES, true)) {
            throw new InvalidArgumentException("Invalid join type: $type");
        }
        return $type;
    }
}

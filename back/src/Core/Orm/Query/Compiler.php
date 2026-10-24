<?php
declare(strict_types=1);

namespace App\Core\Orm\Query;

use LogicException;

final class Compiler
{
    public static function select(QueryParts $q): string
    {
        $sql = 'SELECT ' . implode(', ', $q->columns) . ' FROM ' . Grammar::wrap($q->table);

        if ($q->joins !== []) {
            $sql .= ' ' . implode(' ', $q->joins);
        }
        if (!$q->wheres->isEmpty()) {
            $sql .= ' WHERE ' . $q->wheres->toSql();
        }
        if ($q->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $q->groups);
        }
        if ($q->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $q->orders);
        }
        if ($q->limit !== null) {
            $sql .= ' LIMIT ' . $q->limit;
        }
        if ($q->offset !== null) {
            // MySQL n'accepte pas OFFSET sans LIMIT
            $sql .= ($q->limit === null ? ' LIMIT 18446744073709551615' : '') . ' OFFSET ' . $q->offset;
        }

        return $sql;
    }

    public static function count(QueryParts $parts): string
    {
        $q = clone $parts;
        $q->orders = [];
        $q->limit = $q->offset = null;

        // Avec GROUP BY, COUNT(*) compterait par groupe : on compte les groupes.
        if ($q->groups !== []) {
            // ['1'] : deux colonnes `id` (jointure) feraient echouer la sous-requete (MySQL 1060)
            $q->columns = ['1'];
            return 'SELECT COUNT(*) FROM (' . self::select($q) . ') AS sub';
        }
        $q->columns = ['COUNT(*)'];
        return self::select($q);
    }

    public static function exists(QueryParts $parts): string
    {
        $q = clone $parts;
        $q->columns = ['1'];
        $q->orders = [];
        $q->limit = 1;
        $q->offset = null;
        return self::select($q);
    }

    /** @param string[] $columns noms bruts, valides ici */
    public static function insert(string $table, array $columns): string
    {
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        return 'INSERT INTO ' . Grammar::wrap($table)
            . ' (' . implode(', ', Grammar::wrapAll($columns)) . ") VALUES ($placeholders)";
    }

    /** @param string[] $columns noms bruts, valides ici */
    public static function update(string $table, array $columns, WhereClause $where): string
    {
        self::assertHasWhere('update', $table, $where);
        $sets = implode(', ', array_map(
            static fn (string $col) => Grammar::wrap($col) . ' = ?',
            $columns
        ));
        return 'UPDATE ' . Grammar::wrap($table) . " SET $sets WHERE " . $where->toSql();
    }

    public static function delete(string $table, WhereClause $where): string
    {
        self::assertHasWhere('delete', $table, $where);
        return 'DELETE FROM ' . Grammar::wrap($table) . ' WHERE ' . $where->toSql();
    }

    private static function assertHasWhere(string $action, string $table, WhereClause $where): void
    {
        if ($where->isEmpty()) {
            throw new LogicException("Refusing to $action without a WHERE clause on $table");
        }
    }
}

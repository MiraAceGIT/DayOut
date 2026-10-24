<?php
declare(strict_types=1);

namespace App\Orm\Query;

use InvalidArgumentException;

/**
 * Methodes terminales du QueryBuilder (lecture + ecriture). Trait reserve a
 * QueryBuilder : il s'appuie sur $parts, $hydrator et connection().
 */
trait ExecutesQueries
{
    /** @return array<int, array<string, mixed>> */
    public function get(): array
    {
        $rows = $this->connection()->select($this->toSql(), $this->getBindings());
        return $this->hydrator ? array_map($this->hydrator, $rows) : $rows;
    }

    public function first(): ?array
    {
        $rows = (clone $this)->limit(1)->get();
        return $rows[0] ?? null;
    }

    /** Valeur d'une seule colonne de la premiere ligne. */
    public function value(string $column): mixed
    {
        $row = (clone $this)->select($column)->first();
        return $row === null ? null : reset($row);
    }

    /** Liste des valeurs d'une colonne : pluck('id') -> [1, 2, 3]. */
    public function pluck(string $column): array
    {
        $rows = (clone $this)->select($column)->get();
        return array_map(static fn (array $row) => reset($row), $rows);
    }

    /** Agregat : pas d'hydratation (la ligne n'est pas une entite). */
    public function count(): int
    {
        return (int) $this->connection()->scalar(Compiler::count($this->parts), $this->getBindings());
    }

    public function exists(): bool
    {
        return $this->connection()->scalar(Compiler::exists($this->parts), $this->getBindings()) !== false;
    }

    /** INSERT d'une ligne. Retourne l'id auto-increment (0 si cle composite). */
    public function insert(array $data): int
    {
        if ($data === []) {
            throw new InvalidArgumentException('Cannot insert an empty row');
        }
        $sql = Compiler::insert($this->parts->table, array_keys($data));
        return $this->connection()->insert($sql, array_values($data));
    }

    /** UPDATE sur les lignes filtrees. Retourne le nombre de lignes modifiees. */
    public function update(array $data): int
    {
        if ($data === []) {
            throw new InvalidArgumentException('Cannot update with an empty data set');
        }
        $wheres = $this->parts->wheres;
        $sql = Compiler::update($this->parts->table, array_keys($data), $wheres);
        return $this->connection()->affecting($sql, array_merge(array_values($data), $wheres->bindings()));
    }

    /** DELETE sur les lignes filtrees. Retourne le nombre de lignes supprimees. */
    public function delete(): int
    {
        $wheres = $this->parts->wheres;
        return $this->connection()->affecting(Compiler::delete($this->parts->table, $wheres), $wheres->bindings());
    }
}

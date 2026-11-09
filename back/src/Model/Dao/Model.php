<?php
declare(strict_types=1);

namespace App\Model\Dao;

use App\Core\Database;
use App\Core\Orm\Caster;
use App\Core\Orm\Connection;
use App\Core\Orm\Query\QueryBuilder;
use App\Core\Orm\Rows;
use App\Core\ValidationException;
use LogicException;

abstract class Model
{
    protected const TABLE = '';

    protected const PRIMARY_KEY = 'id';

    /** Colonnes lues ; le contrat API est fixe par les DTO (src/Model/Dto). Eviter '*' sur les tables sensibles. */
    protected const COLUMNS = ['*'];

    /** Saisie client uniquement (anti mass-assignment) ; champs serveur via le 2e argument de create()/updateById(). */
    protected const FILLABLE = [];

    /** colonne => int | float | bool | datetime | json */
    protected const CASTS = [];

    private Connection $connection;

    public function __construct(?Connection $connection = null)
    {
        if (static::TABLE === '') {
            throw new LogicException(static::class . ' must define TABLE');
        }
        $this->connection = $connection ?? Database::orm();
    }

    // find / findMany / all ignorent la visibilite (ex. `published`) : exposer des methodes nommees.
    protected function find(int|string $id): ?array
    {
        return $this->query()->where(static::PRIMARY_KEY, $id)->first();
    }

    /** @return array<int|string, array<string, mixed>> indexe par cle primaire */
    protected function findMany(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }
        return Rows::keyBy($this->query()->whereIn(static::PRIMARY_KEY, $ids)->get(), static::PRIMARY_KEY);
    }

    /** Sans limite : reserve aux petites tables de referentiel (schools, categories). */
    protected function all(): array
    {
        return $this->query()->get();
    }

    public function create(array $input, array $server = []): int
    {
        return $this->table()->insert($this->row($input, $server));
    }

    public function updateById(int|string $id, array $input, array $server = []): int
    {
        return $this->table()
            ->where(static::PRIMARY_KEY, $id)
            ->update($this->row($input, $server));
    }

    public function deleteById(int|string $id): int
    {
        return $this->table()->where(static::PRIMARY_KEY, $id)->delete();
    }

    protected static function containsPattern(string $text): string
    {
        return '%' . strtr($text, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }

    protected function query(): QueryBuilder
    {
        return $this->table()
            ->select(...static::COLUMNS)
            ->hydrateWith(static fn (array $row): array => Caster::apply($row, static::CASTS, static::class));
    }

    /** Builder brut (sans colonnes ni casts) : ecritures, jointures, agregats. */
    protected function table(): QueryBuilder
    {
        return $this->connection->table(static::TABLE);
    }

    /** Rien a ecrire (body vide ou sans champ FILLABLE) : erreur du client (422), pas un 500. */
    private function row(array $input, array $server): array
    {
        if (static::FILLABLE === [] && $input !== []) {
            throw new LogicException(static::class . ' has no FILLABLE columns');
        }
        $row = array_merge(array_intersect_key($input, array_flip(static::FILLABLE)), $server);
        if ($row === []) {
            throw ValidationException::field('body', 'No writable field (allowed: ' . implode(', ', static::FILLABLE) . ')');
        }
        return $row;
    }
}

<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Orm\Caster;
use App\Core\Orm\Connection;
use App\Core\Orm\Query\QueryBuilder;
use App\Core\Orm\Rows;
use LogicException;

/**
 * Base des models MVC : une sous-classe par table, une methode par besoin metier.
 * Lignes renvoyees en tableaux deja castes ; query() reste protected pour que
 * les controllers ne construisent pas de SQL.
 */
abstract class Model
{
    protected const TABLE = '';

    protected const PRIMARY_KEY = 'id';

    /** Contrat API. Eviter '*' sur les tables sensibles. */
    protected const COLUMNS = ['*'];

    /** Saisie client uniquement (anti mass-assignment) ; champs serveur via le 2e argument de create()/updateById(). */
    protected const FILLABLE = [];

    /** colonne => int | float | bool | datetime | json */
    protected const CASTS = [];

    private Connection $connection;

    /** Sans argument : connexion partagee Database::orm(). Le parametre sert aux tests. */
    public function __construct(?Connection $connection = null)
    {
        if (static::TABLE === '') {
            throw new LogicException(static::class . ' must define TABLE');
        }
        $this->connection = $connection ?? Database::orm();
    }

    /*
     * find / findMany / all sont protected : ils ignorent les regles de visibilite
     * (ex. seuls les events `published`). Chaque model expose ses propres methodes
     * nommees (findPublishedById...) qui les appliquent.
     */
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

    /**
     * $input (saisie client) est filtre par FILLABLE ; $server (creator_id,
     * status, password_hash...) est fixe par le code et n'est pas filtre.
     * Erreurs SQL (contraintes, triggers SIGNAL 45000) -> PDOException.
     */
    public function create(array $input, array $server = []): int
    {
        return $this->table()->insert($this->row($input, $server));
    }

    /** Meme separation $input / $server que create(). */
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

    /** Lecture : COLUMNS + casts appliques. */
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

    /**
     * $input vide accepte si $server suffit. Rien a ecrire (body vide ou sans champ
     * FILLABLE) : erreur du client -> 400, pas un 500.
     */
    private function row(array $input, array $server): array
    {
        if (static::FILLABLE === [] && $input !== []) {
            throw new LogicException(static::class . ' has no FILLABLE columns');
        }
        $row = array_merge(array_intersect_key($input, array_flip(static::FILLABLE)), $server);
        if ($row === []) {
            throw HttpException::badRequest('Request body has no writable field', ['allowed' => static::FILLABLE]);
        }
        return $row;
    }
}

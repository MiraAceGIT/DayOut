<?php
declare(strict_types=1);

namespace App\Orm;

use App\Orm\Query\QueryBuilder;

/**
 * Repository de base : une sous-classe par agregat, une methode nommee par
 * besoin metier. query() reste protected pour que les controllers ne
 * construisent pas de SQL eux-memes.
 *
 * Utilisation (dans un controller) :
 *   $users = new UserRepository(Database::orm());
 *   $users->find(3);
 *   $users->create(['email' => 'a@isep.fr', ...]);
 */
abstract class Repository
{
    private Connection $connection;
    private TableMapper $mapper;

    public function __construct(Connection $connection, TableMapper $mapper)
    {
        $this->connection = $connection;
        $this->mapper     = $mapper;
    }

    public function find(int|string $id): ?array
    {
        return $this->query()->where($this->mapper->primaryKey(), $id)->first();
    }

    /**
     * Chargement batch (anti N+1) : une seule requete WHERE pk IN (...).
     * @return array<int|string, array<string, mixed>> indexe par cle primaire
     */
    public function findMany(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }
        $pk = $this->mapper->primaryKey();
        return Rows::keyBy($this->query()->whereIn($pk, $ids)->get(), $pk);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->query()->get();
    }

    /**
     * Insere une ligne. Retourne l'id cree.
     * $input (saisie client) est filtre par FILLABLE ; $server (creator_id,
     * status, password_hash...) est fixe par le code et n'est pas filtre.
     * Les erreurs SQL (contraintes, triggers SIGNAL 45000) remontent en PDOException.
     */
    public function create(array $input, array $server = []): int
    {
        return $this->table()->insert($this->row($input, $server));
    }

    /** Meme separation $input / $server que create(). Retourne le nombre de lignes modifiees. */
    public function updateById(int|string $id, array $input, array $server = []): int
    {
        return $this->table()
            ->where($this->mapper->primaryKey(), $id)
            ->update($this->row($input, $server));
    }

    /** Retourne le nombre de lignes supprimees. */
    public function deleteById(int|string $id): int
    {
        return $this->table()->where($this->mapper->primaryKey(), $id)->delete();
    }

    /** Fusionne saisie client filtree + champs serveur ; $input peut etre vide si $server suffit. */
    private function row(array $input, array $server): array
    {
        if ($input === [] && $server !== []) {
            return $server;
        }
        return array_merge($this->mapper->extract($input), $server);
    }

    /** Lecture : builder pre-configure (table, COLUMNS, hydratation par le mapper). */
    protected function query(): QueryBuilder
    {
        return $this->table()
            ->select(...$this->mapper->columns())
            ->hydrateWith(fn (array $row): array => $this->mapper->hydrate($row));
    }

    /** Builder brut (sans colonnes ni casts) : ecritures, jointures, agregats. */
    protected function table(): QueryBuilder
    {
        return $this->connection->table($this->mapper->table());
    }
}

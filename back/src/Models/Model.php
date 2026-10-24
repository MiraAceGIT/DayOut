<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Orm\Caster;
use App\Core\Orm\Connection;
use App\Core\Orm\Query\QueryBuilder;
use App\Core\Orm\Rows;
use LogicException;

/**
 * Model de base (le M de MVC) : une sous-classe par table, qui decrit la table
 * via ses constantes et expose une methode nommee par besoin metier.
 * Les lignes sont renvoyees en tableaux associatifs deja castes (prets pour
 * Response::json). query() reste protected : les controllers ne construisent
 * pas de SQL eux-memes.
 *
 * Utilisation (dans un controller) :
 *   $users = new User();
 *   $users->find(3);
 *   $users->create($this->body(), ['password_hash' => $hash]);
 */
abstract class Model
{
    /** Nom de la table (obligatoire). */
    protected const TABLE = '';

    protected const PRIMARY_KEY = 'id';

    /** Colonnes lues par defaut (contrat API). Eviter '*' sur les tables sensibles. */
    protected const COLUMNS = ['*'];

    /** Saisie client autorisee en ecriture via create()/updateById() (anti mass-assignment). */
    protected const FILLABLE = [];

    /** colonne => int | float | bool | datetime | json */
    protected const CASTS = [];

    private Connection $connection;

    /** Sans argument : connexion partagee de l'app. Le parametre sert aux tests. */
    public function __construct(?Connection $connection = null)
    {
        if (static::TABLE === '') {
            throw new LogicException(static::class . ' must define TABLE');
        }
        $this->connection = $connection ?? Database::orm();
    }

    public function find(int|string $id): ?array
    {
        return $this->query()->where(static::PRIMARY_KEY, $id)->first();
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
        return Rows::keyBy($this->query()->whereIn(static::PRIMARY_KEY, $ids)->get(), static::PRIMARY_KEY);
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
            ->where(static::PRIMARY_KEY, $id)
            ->update($this->row($input, $server));
    }

    /** Retourne le nombre de lignes supprimees. */
    public function deleteById(int|string $id): int
    {
        return $this->table()->where(static::PRIMARY_KEY, $id)->delete();
    }

    /** Lecture : builder pre-configure (table, COLUMNS, casts). */
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

    /** Saisie client filtree par FILLABLE + champs serveur ; $input peut etre vide si $server suffit. */
    private function row(array $input, array $server): array
    {
        if ($input === [] && $server !== []) {
            return $server;
        }
        if (static::FILLABLE === []) {
            throw new LogicException(static::class . ' has no FILLABLE columns');
        }
        $data = array_intersect_key($input, array_flip(static::FILLABLE));
        if ($data === []) {
            throw new LogicException('No fillable column in data for ' . static::class);
        }
        return array_merge($data, $server);
    }
}

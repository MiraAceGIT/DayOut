<?php
declare(strict_types=1);

namespace App\Orm;

use LogicException;

/**
 * Data Mapper de base : decrit une table et convertit ligne SQL <-> entite.
 * Les entites sont des tableaux associatifs deja castes (prets pour
 * Response::json), pas d'objets : simple et previsible.
 *
 * Declaration :
 *   final class UserMapper extends TableMapper
 *   {
 *       protected const TABLE    = 'users';
 *       protected const COLUMNS  = ['id', 'email', 'first_name'];  // jamais password_hash
 *       protected const FILLABLE = ['email', 'first_name'];
 *       protected const CASTS    = ['email_verified' => 'bool', 'created_at' => 'datetime'];
 *   }
 */
abstract class TableMapper
{
    /** Nom de la table (obligatoire). */
    protected const TABLE = '';

    protected const PRIMARY_KEY = 'id';

    /** Colonnes lues par defaut (contrat API). Eviter '*' sur les tables sensibles. */
    protected const COLUMNS = ['*'];

    /** Colonnes autorisees en ecriture via create()/updateById() (anti mass-assignment). */
    protected const FILLABLE = [];

    /** colonne => int | float | bool | datetime | json */
    protected const CASTS = [];

    public function table(): string
    {
        if (static::TABLE === '') {
            throw new LogicException(static::class . ' must define TABLE');
        }
        return static::TABLE;
    }

    public function primaryKey(): string
    {
        return static::PRIMARY_KEY;
    }

    /** @return string[] */
    public function columns(): array
    {
        return static::COLUMNS;
    }

    /** Ligne SQL -> entite (casts du contrat JSON). */
    public function hydrate(array $row): array
    {
        return Caster::apply($row, static::CASTS, static::class);
    }

    /** Entite -> colonnes a ecrire : ne garde que les colonnes FILLABLE. */
    public function extract(array $data): array
    {
        if (static::FILLABLE === []) {
            throw new LogicException(static::class . ' has no FILLABLE columns');
        }
        $data = array_intersect_key($data, array_flip(static::FILLABLE));
        if ($data === []) {
            throw new LogicException('No fillable column in data for ' . static::class);
        }
        return $data;
    }
}

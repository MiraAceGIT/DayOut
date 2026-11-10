<?php
declare(strict_types=1);

namespace App\Model\Dao\School;

use App\Model\Dao\Model;

final class School extends Model
{
    protected const TABLE = 'schools';

    protected const COLUMNS = ['id', 'name', 'short_name', 'city', 'latitude', 'longitude', 'active'];

    protected const CASTS = [
        'id'        => 'int',
        'latitude'  => 'float',
        'longitude' => 'float',
        'active'    => 'bool',
    ];

    public function findActiveById(int $id): ?array
    {
        return $this->query()->where('id', $id)->where('active', true)->first();
    }

    /** Ecole d'un utilisateur, pour pre-remplir le depart d'un itineraire (nullable : pas d'ecole liee). */
    public function findForUser(int $userId): ?array
    {
        return $this->query()
            ->select(...self::qualifiedColumns())
            ->join('users', 'users.school_id', '=', 'schools.id')
            ->where('users.id', $userId)
            ->where('schools.active', true)
            ->first();
    }

    /** @return list<string> Les COLUMNS prefixees par la table, pour les requetes avec JOIN. */
    private static function qualifiedColumns(): array
    {
        return array_map(static fn (string $column): string => self::TABLE . '.' . $column, self::COLUMNS);
    }
}

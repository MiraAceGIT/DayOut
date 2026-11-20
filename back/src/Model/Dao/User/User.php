<?php
declare(strict_types=1);

namespace App\Model\Dao\User;

use App\Core\Orm\Query\QueryBuilder;
use App\Model\Dao\Model;
use App\Model\Enums\CreatorStatus;
use App\Model\Enums\Role;

/** Droits des comptes (table `users`) ; les profils publics des createurs sont dans Dao\Creator. */
final class User extends Model
{
    protected const TABLE = 'users';

    protected const COLUMNS = ['id'];

    public function isAdmin(int $id): bool
    {
        return $this->active($id)->where('role', Role::Admin->value)->exists();
    }

    /** Admin, ou createur dont la demande est approuvee ; jamais un compte banni. */
    public function canPublish(int $id): bool
    {
        return $this->active($id)
            ->where(static function (QueryBuilder $q): void {
                $q->where('role', Role::Admin->value)
                    ->orWhere(static function (QueryBuilder $creator): void {
                        $creator->where('role', Role::Creator->value)->where('creator_status', CreatorStatus::Approved->value);
                    });
            })
            ->exists();
    }

    private function active(int $id): QueryBuilder
    {
        return $this->table()->where('id', $id)->where('banned', false);
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Dao\User;

use App\Core\Orm\Query\QueryBuilder;
use App\Model\Dao\Dao;
use App\Model\Enums\CreatorStatus;
use App\Model\Enums\Role;
use DateTimeImmutable;

/** Droits des comptes (table `users`) ; les profils publics des createurs sont dans Dao\Creator. */
final class User extends Dao
{
    protected const TABLE = 'users';

    protected const COLUMNS = ['id'];

    /** Saisie de l'inscription ; mot de passe, role et statuts sont fixes par le serveur. */
    protected const FILLABLE = ['email', 'first_name', 'last_name', 'school_id', 'search_radius_km'];

    /** $email deja normalise (minuscules) : les adresses sont stockees ainsi. */
    public function emailExists(string $email): bool
    {
        return $this->table()->where('email', $email)->exists();
    }

    public function markEmailVerified(int $id, DateTimeImmutable $at): void
    {
        $this->table()->where('id', $id)->update(['email_verified' => true, 'email_verified_at' => $at]);
    }

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

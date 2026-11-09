<?php
declare(strict_types=1);

namespace App\Model\Dao;

/** Profil public des createurs (table `users`) : aucune colonne sensible (email, mot de passe, score). */
final class Creator extends Model
{
    protected const TABLE = 'users';

    protected const COLUMNS = ['id', 'first_name', 'last_name', 'avatar_url', 'bio'];

    /**
     * Une seule requete pour toute une page d'events (anti N+1). Alias `detail_*` = format lu par CreatorMapper.
     *
     * @param array<int, int> $userIds
     * @return array<int, array<string, mixed>> user_id => ligne
     */
    public function listPublicByIds(array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));
        if ($userIds === []) {
            return [];
        }

        $rows = $this->table()
            ->selectRaw('users.id AS detail_creator_id, users.first_name AS detail_first_name, users.last_name AS detail_last_name, users.avatar_url AS detail_avatar_url, users.bio AS detail_bio')
            ->selectRaw('cp.display_name AS detail_display_name, cp.description AS detail_creator_description, s.name AS detail_school_name, s.short_name AS detail_school_short_name')
            ->leftJoin('creator_profiles AS cp', 'cp.user_id', '=', 'users.id')
            ->leftJoin('schools AS s', 's.id', '=', 'users.school_id')
            ->whereIn('users.id', $userIds)
            ->get();

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['detail_creator_id']] = $row;
        }
        return $byId;
    }
}

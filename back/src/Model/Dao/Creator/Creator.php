<?php
declare(strict_types=1);

namespace App\Model\Dao\Creator;

use App\Core\Orm\Query\QueryBuilder;
use App\Model\Dao\Model;
use App\Model\Enums\CreatorStatus;
use App\Model\Enums\EventStatus;
use App\Model\Enums\Role;

final class Creator extends Model
{
    protected const TABLE = 'users';

    protected const COLUMNS = ['id', 'first_name', 'last_name', 'avatar_url', 'bio'];

    /** Cle publique (sort_by) => colonne ; la premiere est le tri par defaut. */
    public const SORT_MAP = [
        'display_name' => 'cp.display_name',
        'created_at'   => 'users.created_at',
    ];

    /**
     * @param array<int, int> $userIds
     * @return array<int, array<string, mixed>>
     */
    public function listPublicByIds(array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));
        if ($userIds === []) {
            return [];
        }

        $byId = [];
        foreach ($this->publicProfiles()->whereIn('users.id', $userIds)->get() as $row) {
            $byId[(int) $row['detail_creator_id']] = $row;
        }
        return $byId;
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: int} */
    public function findManyPublic(?CreatorSearchParams $params = null): array
    {
        $param = $params ?? new CreatorSearchParams();
        $query = $this->publicProfiles()
            ->where('users.role', Role::Creator->value)
            ->where('users.creator_status', CreatorStatus::Approved->value)
            ->where('users.banned', false);

        if ($param->text !== null) {
            $like = self::containsPattern($param->text);
            $query->whereRaw(
                "cp.display_name LIKE ? ESCAPE '!' OR users.first_name LIKE ? ESCAPE '!' OR users.last_name LIKE ? ESCAPE '!'",
                [$like, $like, $like],
            );
        }
        if ($param->schoolId !== null) {
            $query->where('users.school_id', $param->schoolId);
        }
        if ($param->ids) {
            $query->whereIn('users.id', $param->ids);
        }
        if ($param->withEventsEndingAfter !== null) {
            $query->whereRaw(
                'EXISTS (SELECT 1 FROM events AS e WHERE e.creator_id = users.id AND e.status = ? AND e.end_at > ?)',
                [EventStatus::Published->value, $param->withEventsEndingAfter],
            );
        }

        return $this->paginate($query, $param->page, self::SORT_MAP);
    }

    private function publicProfiles(): QueryBuilder
    {
        return $this->table()
            ->selectRaw('users.id AS detail_creator_id, users.first_name AS detail_first_name, users.last_name AS detail_last_name, users.avatar_url AS detail_avatar_url, users.bio AS detail_bio')
            ->selectRaw('cp.display_name AS detail_display_name, cp.description AS detail_creator_description, s.name AS detail_school_name, s.short_name AS detail_school_short_name')
            ->leftJoin('creator_profiles AS cp', 'cp.user_id', '=', 'users.id')
            ->leftJoin('schools AS s', 's.id', '=', 'users.school_id');
    }
}

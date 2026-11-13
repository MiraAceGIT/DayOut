<?php
declare(strict_types=1);

namespace App\Model\Dao\Event;

use App\Core\Orm\Query\QueryBuilder;
use App\Model\Dao\Model;

final class Event extends Model
{
    protected const TABLE = 'events';

    protected const COLUMNS = [
        'id', 'creator_id', 'title', 'slug', 'description', 'cover_image_url',
        'start_at', 'end_at', 'venue_name', 'address_line', 'city', 'postal_code',
        'latitude', 'longitude', 'capacity', 'registered_count', 'price_cents', 'currency', 'is_paid',
        'external_ticket_url', 'school_restricted', 'restricted_school_id',
        'status', 'published_at', 'created_at',
    ];

    protected const FILLABLE = [
        'title', 'slug', 'description', 'cover_image_url',
        'start_at', 'end_at', 'venue_name', 'address_line', 'city', 'postal_code',
        'latitude', 'longitude', 'capacity', 'price_cents', 'currency', 'is_paid',
        'external_ticket_url', 'school_restricted', 'restricted_school_id',
    ];

    protected const CASTS = [
        'id'                   => 'int',
        'creator_id'           => 'int',
        'restricted_school_id' => 'int',
        'capacity'             => 'int',
        'registered_count'     => 'int',
        'price_cents'          => 'int',
        'latitude'             => 'float',
        'longitude'            => 'float',
        'is_paid'              => 'bool',
        'school_restricted'    => 'bool',
        'start_at'             => 'datetime',
        'end_at'               => 'datetime',
        'published_at'         => 'datetime',
        'created_at'           => 'datetime',
    ];

    private const SORT_MAP = [
        'start_at'    => 'start_at',
        'created_at'  => 'created_at',
        'title'       => 'title',
        'price_cents' => 'price_cents',
    ];

    /** @return array{0: array<int, array<string, mixed>>, 1: int} */
    public function findManyPublished(?EventSearchParams $params = null): array
    {
        $param = $params ?? new EventSearchParams();
        $query = $this->published();

        if ($param->endingAfter !== null) {
            $query->where('end_at', '>', $param->endingAfter);
        }
        if ($param->startFrom !== null) {
            $query->where('start_at', '>=', $param->startFrom);
        }
        if ($param->startTo !== null) {
            $query->where('start_at', '<', $param->startTo);
        }
        if ($param->text !== null) {
            $like = self::containsPattern($param->text);
            // Casse et accents : collation utf8mb4_unicode_ci des colonnes. Le motif reste un parametre lie.
            $query->whereRaw(
                "title LIKE ? ESCAPE '!' OR description LIKE ? ESCAPE '!' OR venue_name LIKE ? ESCAPE '!' OR city LIKE ? ESCAPE '!'"
                . ' OR EXISTS (SELECT 1 FROM event_categories AS kc JOIN categories AS kcat ON kcat.id = kc.category_id'
                . " WHERE kc.event_id = events.id AND kcat.active = 1 AND kcat.label LIKE ? ESCAPE '!')",
                [$like, $like, $like, $like, $like],
            );
        }
        if ($param->city !== null) {
            $query->where('city', $param->city);
        }
        if ($param->ids) {
            $query->whereIn('id', $param->ids);
        }
        if ($param->categories) {
            $placeholders = implode(', ', array_fill(0, count($param->categories), '?'));
            $query->whereRaw(
                'EXISTS (SELECT 1 FROM event_categories AS ec JOIN categories AS c ON c.id = ec.category_id'
                . " WHERE ec.event_id = events.id AND c.active = 1 AND c.slug IN ($placeholders))",
                $param->categories,
            );
        }
        if ($param->creatorId !== null) {
            $query->where('creator_id', $param->creatorId);
        }
        if ($param->isFree !== null) {
            $query->where('is_paid', !$param->isFree);
        }
        if ($param->onlyAvailable) {
            $query->whereRaw('capacity IS NULL OR registered_count < capacity');
        }

        $total = $query->count();

        $orderColumn = self::SORT_MAP[$param->sortBy] ?? self::SORT_MAP['start_at'];
        $orderDir    = $param->sortDir === 'desc' ? 'DESC' : 'ASC';

        $items = $query
            ->orderBy($orderColumn, $orderDir)
            ->orderBy('id', $orderDir)
            ->limit($param->limit)
            ->offset($param->offset)
            ->get();

        return [$items, $total];
    }

    public function findPublishedById(int $id): ?array
    {
        return $this->published()->where('id', $id)->first();
    }

    public function findForWriteById(int $id): ?array
    {
        return $this->query()->where('id', $id)->first();
    }

    /** Changement de statut atomique : 0 si l'event n'est plus dans le statut `$from`. */
    public function changeStatus(int $id, string $from, string $to, array $server = []): int
    {
        return $this->table()->where('id', $id)->where('status', $from)->update(['status' => $to] + $server);
    }

    public function deleteDraft(int $id): int
    {
        return $this->table()->where('id', $id)->where('status', 'draft')->delete();
    }

    private function published(): QueryBuilder
    {
        return $this->query()
            ->where('status', 'published')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM users AS banned_creator WHERE banned_creator.id = events.creator_id AND banned_creator.banned = 1)');
    }
}

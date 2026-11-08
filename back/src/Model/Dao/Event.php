<?php
declare(strict_types=1);

namespace App\Model\Dao;

use App\Core\Orm\Query\QueryBuilder;
use App\Core\Orm\Caster;
use DateTimeInterface;

/** Seuls les events `published` sont exposes. */
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

    /** Saisie client uniquement : creator_id, status, published_at sont fixes par le serveur. */
    protected const FILLABLE = [
        'title', 'slug', 'description', 'cover_image_url',
        'start_at', 'end_at', 'venue_name', 'address_line', 'city', 'postal_code',
        'latitude', 'longitude', 'capacity', 'price_cents', 'currency', 'is_paid',
        'external_ticket_url', 'school_restricted', 'restricted_school_id',
    ];

    /** Tous les entiers exposes sont castes : ne pas dependre du comportement de mysqlnd. */
    protected const CASTS = [
        'id'                   => 'int',
        'creator_id'           => 'int',
        'restricted_school_id' => 'int',
        'capacity'             => 'int',
        'registered_count'     => 'int',
        'price_cents'          => 'int',
        'latitude'             => 'float', // DECIMAL : PDO renvoie une chaine
        'longitude'            => 'float',
        'is_paid'              => 'bool',
        'school_restricted'    => 'bool',
        'start_at'             => 'datetime',
        'end_at'               => 'datetime',
        'published_at'         => 'datetime',
        'created_at'           => 'datetime',
    ];

    /**
     * Events pas encore termines a `$from` (ceux en cours compris), du plus proche au plus lointain.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPublishedEndingAfter(DateTimeInterface $from, int $limit = 20, int $offset = 0): array
    {
        return $this->published()
            ->where('end_at', '>', $from)
            ->orderBy('start_at')
            ->orderBy('id') // departage les egalites : pas de doublon/trou entre pages
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public function findPublishedById(int $id): ?array
    {
        return $this->published()->where('id', $id)->first();
    }

    /** Detail public en une requete ; le COUNT est independant des categories (pas de multiplication). */
    public function findPublishedDetailById(int $id): ?array
    {
        $columns = array_map(
            static fn (string $column): string => 'events.' . $column,
            array_values(array_diff(self::COLUMNS, ['registered_count'])),
        );
        $rows = $this->table()
            ->select(...$columns)
            ->selectRaw("(SELECT COUNT(*) FROM registrations AS r WHERE r.event_id = events.id AND r.status <> 'cancelled') AS registered_count")
            ->selectRaw('u.id AS detail_creator_id, u.first_name AS detail_first_name, u.last_name AS detail_last_name, u.avatar_url AS detail_avatar_url, u.bio AS detail_bio')
            ->selectRaw('cp.display_name AS detail_display_name, cp.description AS detail_creator_description, s.name AS detail_school_name, s.short_name AS detail_school_short_name')
            ->selectRaw('c.id AS detail_category_id, c.slug AS detail_category_slug, c.label AS detail_category_label, c.active AS detail_category_active')
            ->join('users AS u', 'u.id', '=', 'events.creator_id')
            ->leftJoin('creator_profiles AS cp', 'cp.user_id', '=', 'u.id')
            ->leftJoin('schools AS s', 's.id', '=', 'u.school_id')
            ->leftJoin('event_categories AS ec', 'ec.event_id', '=', 'events.id')
            ->leftJoin('categories AS c', 'c.id', '=', 'ec.category_id')
            ->where('events.id', $id)
            ->where('events.status', 'published')
            ->orderBy('c.label')
            ->orderBy('c.id')
            ->get();

        if ($rows === []) {
            return null;
        }
        $event = Caster::apply($rows[0], self::CASTS, self::class);
        // Filtrer apres la LEFT JOIN garde les evenements sans categorie active.
        $event['categories'] = [];
        foreach ($rows as $row) {
            if ((int) $row['detail_category_active'] === 1) {
                $event['categories'][] = ['slug' => $row['detail_category_slug'], 'label' => $row['detail_category_label']];
            }
        }
        return $event;
    }

    private function published(): QueryBuilder
    {
        return $this->query()->where('status', 'published');
    }
}

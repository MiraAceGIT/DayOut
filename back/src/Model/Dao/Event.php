<?php
declare(strict_types=1);

namespace App\Model\Dao;

use App\Core\Orm\Query\QueryBuilder;

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

    /** @return array<int, array<string, mixed>> */
    public function listPublished(int $limit = 20, int $offset = 0): array
    {
        return $this->published()
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

    private function published(): QueryBuilder
    {
        return $this->query()->where('status', 'published');
    }
}

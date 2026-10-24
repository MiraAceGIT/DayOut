<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Orm\Query\QueryBuilder;

/**
 * Model Event : table `events`. Seuls les events `published` sont exposes
 * publiquement. Pas de logique metier ici, uniquement l'acces aux donnees.
 */
final class Event extends Model
{
    protected const TABLE = 'events';

    /** Colonnes exposees par l'API (ne jamais utiliser SELECT *). */
    protected const COLUMNS = [
        'id', 'creator_id', 'title', 'slug', 'description', 'cover_image_url',
        'start_at', 'end_at', 'venue_name', 'address_line', 'city', 'postal_code',
        'capacity', 'registered_count', 'price_cents', 'currency', 'is_paid',
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

    protected const CASTS = [
        'is_paid'           => 'bool',
        'school_restricted' => 'bool',
        'price_cents'       => 'int',
        'registered_count'  => 'int',
        'capacity'          => 'int',
        'start_at'          => 'datetime',
        'end_at'            => 'datetime',
        'published_at'      => 'datetime',
        'created_at'        => 'datetime',
    ];

    /** Liste des events publies, pagination par limit/offset. */
    public function listPublished(int $limit = 20, int $offset = 0): array
    {
        return $this->published()
            ->orderBy('start_at')
            ->orderBy('id') // departage les egalites : pas de doublon/trou entre pages
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /** Un event par id, uniquement s'il est publie. */
    public function findPublishedById(int $id): ?array
    {
        return $this->published()->where('id', $id)->first();
    }

    /** Scope : events visibles publiquement. */
    private function published(): QueryBuilder
    {
        return $this->query()->where('status', 'published');
    }
}

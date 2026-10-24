<?php
declare(strict_types=1);

namespace App\Mappers;

use App\Orm\TableMapper;

/**
 * Mapper de la table `events` : colonnes exposees, ecriture autorisee et
 * casts necessaires pour un contrat JSON stable. Pas de logique metier ici.
 */
final class EventMapper extends TableMapper
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
}

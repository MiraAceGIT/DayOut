<?php
declare(strict_types=1);

namespace App\Model\Dao\Event;

use App\Model\Enums\EventStatus;

/**
 * Ligne de `events` typee, pour les regles metier (la reponse API passe par EventDto).
 * Seulement les colonnes dont dependent les regles : statut, proprietaire, dates, prix, inscrits.
 */
final readonly class EventRecord
{
    public function __construct(
        public int $id,
        public int $creatorId,
        public EventStatus $status,
        public string $startAt, // ISO 8601 UTC (Caster datetime)
        public string $endAt,
        public bool $isPaid,
        public int $priceCents,
        public int $registeredCount,
    ) {
    }

    /** @param array<string, mixed> $row ligne castee par le DAO Event */
    public static function fromRow(array $row): self
    {
        return new self(
            id:              $row['id'],
            creatorId:       $row['creator_id'],
            status:          EventStatus::from($row['status']),
            startAt:         $row['start_at'],
            endAt:           $row['end_at'],
            isPaid:          $row['is_paid'],
            priceCents:      $row['price_cents'],
            registeredCount: $row['registered_count'],
        );
    }
}

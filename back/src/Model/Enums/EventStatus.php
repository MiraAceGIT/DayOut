<?php
declare(strict_types=1);

namespace App\Model\Enums;

/** Valeurs de `events.status` (ENUM MariaDB). */
enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    /** Champs modifiables : avant et pendant la publication, jamais apres annulation ou archivage. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Published;
    }

    /** Seul un brouillon se supprime : publie, l'event a un historique (inscriptions, clics, signalements). */
    public function isDeletable(): bool
    {
        return $this === self::Draft;
    }

    /** Statut de depart exige pour passer a `$this` (publier un brouillon, annuler un event publie). */
    public function requiredFrom(): ?self
    {
        return match ($this) {
            self::Published => self::Draft,
            self::Cancelled => self::Published,
            default         => null,
        };
    }
}

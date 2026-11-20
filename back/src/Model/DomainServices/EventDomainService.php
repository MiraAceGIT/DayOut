<?php
declare(strict_types=1);

namespace App\Model\DomainServices;

use App\Core\ValidationException;
use App\Model\Dao\Event\EventRecord;
use App\Model\Dto\Event\EventUpdateDto;
use App\Model\Enums\EventStatus;
use App\Model\Exceptions\EventException;
use DateTimeImmutable;

/** Regles metier des events, sans base ni etat : appelees par les DTO (422 a la lecture) et par EventService. */
final class EventDomainService
{
    /** Figees des qu'il y a des inscrits : changer la date ou le prix tromperait les participants. */
    private const LOCKED_WITH_REGISTRATIONS = ['start_at', 'end_at', 'is_paid', 'price_cents', 'currency'];

    public static function checkDates(string $startAt, string $endAt): void
    {
        if ((new DateTimeImmutable($endAt))->getTimestamp() <= (new DateTimeImmutable($startAt))->getTimestamp()) {
            throw ValidationException::field('end_at', 'Must be after start_at');
        }
    }

    public static function checkPrice(bool $isPaid, int $priceCents): void
    {
        if ($isPaid !== ($priceCents > 0)) {
            throw ValidationException::field('price_cents', $isPaid ? 'Must be greater than 0 for a paid event' : 'Must be 0 for a free event');
        }
    }

    /** Modification de champs (hors statut) : statut, champs verrouilles, puis coherence avec l'existant. */
    public static function checkUpdate(EventRecord $event, EventUpdateDto $dto): void
    {
        if (!$event->status->isEditable()) {
            throw EventException::invalidStatus($event->id, $event->status->value, 'update');
        }
        self::checkUnlockedFields($event, $dto);

        if ($dto->has('start_at') || $dto->has('end_at')) {
            self::checkDates($dto->startAt ?? $event->startAt, $dto->endAt ?? $event->endAt);
        }
        if ($dto->has('is_paid') || $dto->has('price_cents')) {
            self::checkPrice($dto->isPaid ?? $event->isPaid, $dto->priceCents ?? $event->priceCents);
        }
        if ($dto->capacity !== null && $dto->capacity < $event->registeredCount) {
            throw ValidationException::field('capacity', "Must be at least {$event->registeredCount} (current registrations)");
        }
    }

    public static function checkPublishable(EventRecord $event, DateTimeImmutable $now): void
    {
        if (new DateTimeImmutable($event->endAt) <= $now) {
            throw ValidationException::field('end_at', 'Must be in the future to publish');
        }
    }

    private static function checkUnlockedFields(EventRecord $event, EventUpdateDto $dto): void
    {
        if ($event->status !== EventStatus::Draft && $dto->has('restricted_school_id')) {
            throw EventException::fieldsLocked($event->id, ['restricted_school_id'], 'the event is published');
        }
        if ($event->registeredCount > 0) {
            $locked = array_values(array_intersect(self::LOCKED_WITH_REGISTRATIONS, $dto->provided));
            if ($locked !== []) {
                throw EventException::fieldsLocked($event->id, $locked, 'the event has registrations');
            }
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Mappers;

use App\Model\Dto\Event\EventCreateDto;
use App\Model\Dto\Event\EventDto;
use App\Model\Dto\Event\EventUpdateDto;
use DateTimeImmutable;
use DateTimeZone;

final class EventMapper
{
    private const DB_DATETIME = 'Y-m-d H:i:s';

    public static function toDto(array $row): EventDto
    {
        return new EventDto(
            id: $row['id'],
            title: $row['title'],
            slug: $row['slug'],
            description: $row['description'],
            coverImageUrl: $row['cover_image_url'],
            startAt: $row['start_at'],
            endAt: $row['end_at'],
            venueName: $row['venue_name'],
            addressLine: $row['address_line'],
            city: $row['city'],
            postalCode: $row['postal_code'],
            latitude: $row['latitude'],
            longitude: $row['longitude'],
            capacity: $row['capacity'],
            registeredCount: $row['registered_count'],
            priceCents: $row['price_cents'],
            currency: $row['currency'],
            isPaid: $row['is_paid'],
            externalTicketUrl: $row['external_ticket_url'],
            schoolRestricted: $row['school_restricted'],
            status: $row['status'],
            categories: CategoryMapper::toDtos($row['categories'] ?? []),
            creator: CreatorMapper::toDto($row),
        );
    }

    /** @return list<EventDto> */
    public static function toDtos(array $rows): array
    {
        return array_map(self::toDto(...), array_values($rows));
    }

    /**
     * Colonnes de `events` pour Event::create() ; creator_id et status vont dans `$server`.
     *
     * @return array<string, mixed>
     */
    public static function toRow(EventCreateDto $dto): array
    {
        return [
            'title'                => $dto->title,
            'slug'                 => $dto->slug,
            'description'          => $dto->description,
            'cover_image_url'      => $dto->coverImageUrl,
            'start_at'             => self::toDbDatetime($dto->startAt),
            'end_at'               => self::toDbDatetime($dto->endAt),
            'venue_name'           => $dto->venueName,
            'address_line'         => $dto->addressLine,
            'city'                 => $dto->city,
            'postal_code'          => $dto->postalCode,
            'latitude'             => $dto->latitude,
            'longitude'            => $dto->longitude,
            'capacity'             => $dto->capacity,
            'price_cents'          => $dto->priceCents,
            'currency'             => $dto->currency,
            'is_paid'              => $dto->isPaid,
            'external_ticket_url'  => $dto->externalTicketUrl,
            // Les deux ensemble : trg_events_bi/bu refuse l'un sans l'autre.
            'school_restricted'    => $dto->restrictedSchoolId !== null,
            'restricted_school_id' => $dto->restrictedSchoolId,
        ];
    }

    /** @return array<string, mixed> */
    public static function toUpdateRow(EventUpdateDto $dto): array
    {
        $columns = [
            'title'                => $dto->title,
            'slug'                 => $dto->slug,
            'description'          => $dto->description,
            'cover_image_url'      => $dto->coverImageUrl,
            'start_at'             => $dto->startAt === null ? null : self::toDbDatetime($dto->startAt),
            'end_at'               => $dto->endAt === null ? null : self::toDbDatetime($dto->endAt),
            'venue_name'           => $dto->venueName,
            'address_line'         => $dto->addressLine,
            'city'                 => $dto->city,
            'postal_code'          => $dto->postalCode,
            'latitude'             => $dto->latitude,
            'longitude'            => $dto->longitude,
            'capacity'             => $dto->capacity,
            'price_cents'          => $dto->priceCents,
            'currency'             => $dto->currency,
            'is_paid'              => $dto->isPaid,
            'external_ticket_url'  => $dto->externalTicketUrl,
            'restricted_school_id' => $dto->restrictedSchoolId,
        ];
        $row = array_intersect_key($columns, array_flip($dto->provided));
        if ($dto->has('restricted_school_id')) {
            $row['school_restricted'] = $dto->restrictedSchoolId !== null;
        }
        return $row;
    }

    private static function toDbDatetime(string $iso): string
    {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('UTC'))->format(self::DB_DATETIME);
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Mappers;

use App\Core\HttpException;
use App\Core\Validator;
use App\Model\Dto\EventDto;
use App\Model\Dto\EventInputDto;
use DateTimeImmutable;
use DateTimeZone;

final class EventMapper
{
    private const DB_DATETIME = 'Y-m-d H:i:s';

    public static function toDto(array $row, array $categoryRows = []): EventDto
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
            categories: CategoryMapper::toDtos($categoryRows),
        );
    }

    public static function toDtos(array $rows, array $categoriesByEvent = []): array
    {
        return array_map(
            static fn (array $row): EventDto => self::toDto($row, $categoriesByEvent[$row['id']] ?? []),
            array_values($rows)
        );
    }

    /** JSON recu -> EventInputDto ; 422 VALIDATION_ERROR (details par champ) si invalide. */
    public static function fromInput(array $json): EventInputDto
    {
        $v = Validator::check($json, EventInputDto::RULES);
        if (new DateTimeImmutable($v['end_at']) <= new DateTimeImmutable($v['start_at'])) {
            throw HttpException::validation(['end_at' => 'Must be after start_at']);
        }

        return new EventInputDto(
            title: $v['title'],
            slug: $v['slug'],
            description: $v['description'],
            startAt: $v['start_at'],
            endAt: $v['end_at'],
            coverImageUrl: $v['cover_image_url'] ?? null,
            venueName: $v['venue_name'] ?? null,
            addressLine: $v['address_line'] ?? null,
            city: $v['city'] ?? null,
            postalCode: $v['postal_code'] ?? null,
            latitude: $v['latitude'] ?? null,
            longitude: $v['longitude'] ?? null,
            capacity: $v['capacity'] ?? null,
            priceCents: $v['price_cents'] ?? 0,
            currency: $v['currency'] ?? 'EUR',
            isPaid: $v['is_paid'] ?? false,
            externalTicketUrl: $v['external_ticket_url'] ?? null,
            restrictedSchoolId: $v['restricted_school_id'] ?? null,
        );
    }

    /**
     * Colonnes de `events` pour Event::create() / updateById() ; creator_id et status vont dans `$server`.
     *
     * @return array<string, mixed>
     */
    public static function toRow(EventInputDto $dto): array
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

    private static function toDbDatetime(string $iso): string
    {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('UTC'))->format(self::DB_DATETIME);
    }
}

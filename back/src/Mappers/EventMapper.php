<?php
declare(strict_types=1);

namespace App\Mappers;

use App\Dto\EventDto;
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

    /**
     * @return array<string, mixed>
     */
    public static function toRow(EventDto $dto): array
    {
        return [
            'title'               => $dto->title,
            'slug'                => $dto->slug,
            'description'         => $dto->description,
            'cover_image_url'     => $dto->coverImageUrl,
            'start_at'            => self::toDbDatetime($dto->startAt),
            'end_at'              => self::toDbDatetime($dto->endAt),
            'venue_name'          => $dto->venueName,
            'address_line'        => $dto->addressLine,
            'city'                => $dto->city,
            'postal_code'         => $dto->postalCode,
            'latitude'            => $dto->latitude,
            'longitude'           => $dto->longitude,
            'capacity'            => $dto->capacity,
            'price_cents'         => $dto->priceCents,
            'currency'            => $dto->currency,
            'is_paid'             => $dto->isPaid,
            'external_ticket_url' => $dto->externalTicketUrl,
        ];
    }

    private static function toDbDatetime(string $iso): string
    {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('UTC'))->format(self::DB_DATETIME);
    }
}

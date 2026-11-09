<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Mappers;

use App\Core\ValidationException;
use App\Model\Dto\Event\EventCreateDto;
use App\Model\Dto\Event\EventUpdateDto;
use App\Model\Mappers\EventMapper;
use Tests\TestCase;

final class EventMapperTest extends TestCase
{
    /** Ligne telle que la renvoie Event (DAO) apres le Caster, colonnes internes comprises. Reprise par DocsTest. */
    public static function eventRow(array $overrides = []): array
    {
        return $overrides + [
            'id' => 1, 'creator_id' => 2, 'title' => 'Soiree', 'slug' => 'soiree', 'description' => 'Desc',
            'cover_image_url' => null, 'start_at' => '2026-11-07T20:00:00+00:00', 'end_at' => '2026-11-08T02:00:00+00:00',
            'venue_name' => 'Le Rex', 'address_line' => '5 bd Poissonniere', 'city' => 'Paris', 'postal_code' => '75002',
            'latitude' => 48.8689, 'longitude' => 2.341, 'capacity' => 250, 'registered_count' => 12,
            'price_cents' => 1500, 'currency' => 'EUR', 'is_paid' => true, 'external_ticket_url' => null,
            'school_restricted' => true, 'restricted_school_id' => 1, 'status' => 'published',
            'published_at' => '2026-10-01T09:00:00+00:00', 'created_at' => '2026-10-01T09:00:00+00:00',
            'detail_creator_id' => 2, 'detail_first_name' => 'BDE', 'detail_last_name' => 'ISEP', 'detail_avatar_url' => null,
            'detail_bio' => null, 'detail_display_name' => 'BDE ISEP', 'detail_creator_description' => null,
            'detail_school_name' => 'Institut superieur', 'detail_school_short_name' => 'ISEP',
        ];
    }

    public function testEventDtoExposesOnlyPublicFieldsInSnakeCase(): void
    {
        $json = EventMapper::toDto(self::eventRow(['categories' => [['slug' => 'bde', 'label' => 'BDE / Assoc']]]))->jsonSerialize();

        $this->assertSame([
            'id', 'title', 'slug', 'description', 'cover_image_url', 'start_at', 'end_at', 'venue_name',
            'address_line', 'city', 'postal_code', 'latitude', 'longitude', 'capacity', 'registered_count',
            'price_cents', 'currency', 'is_paid', 'external_ticket_url', 'school_restricted', 'categories', 'creator',
        ], array_keys($json));
        $this->assertSame('BDE ISEP', $json['creator']->displayName);
        $this->assertSame(12, $json['registered_count']);
        $this->assertSame(48.8689, $json['latitude']);
        $this->assertSame('{"slug":"bde","label":"BDE / Assoc"}', json_encode($json['categories'][0], JSON_UNESCAPED_SLASHES));
    }

    public function testEventWithoutCategoriesNorCoordinates(): void
    {
        $dto = EventMapper::toDto(self::eventRow(['latitude' => null, 'longitude' => null, 'capacity' => null]));

        $this->assertSame([], $dto->categories);
        $this->assertSame(null, $dto->latitude);
        $this->assertSame(null, $dto->capacity);
    }

    public function testToDtosAttachesEachEventItsCategories(): void
    {
        $dtos = EventMapper::toDtos([
            5 => self::eventRow(['id' => 1]),
            9 => self::eventRow(['id' => 2, 'categories' => [['slug' => 'sport', 'label' => 'Sport']]]),
        ]);

        $this->assertSame([1, 2], array_map(static fn ($dto): int => $dto->id, $dtos), 'list, row order kept');
        $this->assertSame([], $dtos[0]->categories, 'event without categories');
        $this->assertSame('sport', $dtos[1]->categories[0]->slug);
        $this->assertSame([], EventMapper::toDtos([]));
    }

    private static function input(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Soiree', 'slug' => 'soiree', 'description' => 'Desc',
            'start_at' => '2026-11-07T21:00:00+01:00', 'end_at' => '2026-11-08T02:00:00Z',
        ];
    }

    public function testCreateDtoAppliesColumnDefaultsAndIgnoresServerFields(): void
    {
        $dto = EventCreateDto::fromArray(self::input([
            'latitude' => '48.8689', 'id' => 99, 'creator_id' => 1, 'status' => 'published', 'registered_count' => 500,
        ]));

        $this->assertSame(48.8689, $dto->latitude, 'numeric string cast to float');
        $this->assertSame(0, $dto->priceCents);
        $this->assertSame('EUR', $dto->currency);
        $this->assertSame(false, $dto->isPaid);
        $this->assertSame(null, $dto->restrictedSchoolId);
    }

    public function testCreateDtoRejectsInvalidBodiesWith422PerField(): void
    {
        $cases = [
            'title'        => [self::input(['title' => '']), 'This field is required'],
            'end_at'       => [self::input(['end_at' => '2026-11-07T19:00:00Z']), 'Must be after start_at'],
            'latitude'     => [self::input(['latitude' => 91]), 'Must be at most 90'],
            'currency'     => [self::input(['currency' => 'EURO']), 'Must be one of: EUR'],
            'slug'         => [self::input(['slug' => 'Soiree Rentree']), 'Must be lowercase letters, digits and single dashes'],
            'description'  => [self::input(['description' => str_repeat('a', 16001)]), 'Must be at most 16000 characters'],
            'restricted_school_id' => [self::input(['restricted_school_id' => 4294967296]), 'Must be at most 4294967295'],
            'capacity'     => [self::input(['capacity' => 0]), 'Must be at least 1'],
            'cover_image_url' => [self::input(['cover_image_url' => 'javascript:alert(1)']), 'Must be a valid http(s) URL'],
        ];
        foreach ($cases as $field => [$body, $message]) {
            $e = $this->assertThrows(ValidationException::class, fn () => EventCreateDto::fromArray($body), $field);
            $this->assertSame($message, $e->errors[$field] ?? null, $field);
        }
    }

    /** chk_events_price verifie en PHP : le client sait quel champ corriger (le CHECK SQL ne le dirait pas). */
    public function testPaidFlagAndPriceMustAgree(): void
    {
        $cases = [
            'Must be greater than 0 for a paid event' => ['is_paid' => true],
            'Must be 0 for a free event'              => ['price_cents' => 500],
        ];
        foreach ($cases as $message => $overrides) {
            $e = $this->assertThrows(ValidationException::class, fn () => EventCreateDto::fromArray(self::input($overrides)), $message);
            $this->assertSame(['price_cents' => $message], $e->errors);
        }

        $paid = EventCreateDto::fromArray(self::input(['is_paid' => true, 'price_cents' => 1500]));
        $this->assertSame(true, $paid->isPaid);
        $this->assertSame(1500, $paid->priceCents);
    }

    public function testToRowWritesDbDatetimesAndDerivesSchoolRestriction(): void
    {
        $row = EventMapper::toRow(EventCreateDto::fromArray(self::input()));

        $this->assertSame('2026-11-07 20:00:00', $row['start_at'], 'converted to UTC');
        $this->assertSame('2026-11-08 02:00:00', $row['end_at'], 'Z suffix');
        $this->assertSame(false, $row['school_restricted']);
        $this->assertSame(null, $row['restricted_school_id']);
        foreach (['id', 'registered_count', 'creator_id', 'status', 'categories'] as $key) {
            $this->assertFalse(array_key_exists($key, $row), "toRow writes $key");
        }

        $restricted = EventMapper::toRow(EventCreateDto::fromArray(self::input(['restricted_school_id' => 3])));
        $this->assertSame(true, $restricted['school_restricted']);
        $this->assertSame(3, $restricted['restricted_school_id']);
    }

    public function testUpdateDtoKeepsOnlySentFieldsAndDistinguishesNullFromAbsent(): void
    {
        $dto = EventUpdateDto::fromArray(['title' => 'Nouveau titre', 'cover_image_url' => null, 'id' => 99, 'status' => 'published']);

        $this->assertSame(['title', 'cover_image_url'], $dto->provided, 'server fields ignored');
        $this->assertSame('Nouveau titre', $dto->title);
        $this->assertTrue($dto->has('cover_image_url'));
        $this->assertFalse($dto->has('city'));
        $this->assertSame(['title' => 'Nouveau titre', 'cover_image_url' => null], EventMapper::toUpdateRow($dto));
    }

    public function testUpdateDtoRejectsInvalidOrEmptyBodies(): void
    {
        $cases = [
            'body'        => [[], 'No writable field'],
            'title'       => [['title' => null], 'This field cannot be null'],
            'latitude'    => [['latitude' => 91], 'Must be at most 90'],
            'end_at'      => [['start_at' => '2026-11-07T21:00:00Z', 'end_at' => '2026-11-07T20:00:00Z'], 'Must be after start_at'],
            'price_cents' => [['is_paid' => true, 'price_cents' => 0], 'Must be greater than 0 for a paid event'],
        ];
        foreach ($cases as $field => [$body, $message]) {
            $e = $this->assertThrows(ValidationException::class, fn () => EventUpdateDto::fromArray($body), $field);
            $this->assertStringContains($message, $e->errors[$field] ?? '', $field);
        }
        $this->assertSame(['end_at'], EventUpdateDto::fromArray(['end_at' => '2026-11-07T20:00:00Z'])->provided);
    }

    public function testUpdateRowConvertsDatesAndKeepsSchoolRestrictionConsistent(): void
    {
        $row = EventMapper::toUpdateRow(EventUpdateDto::fromArray(['start_at' => '2026-11-07T21:00:00+01:00', 'restricted_school_id' => 3]));
        $this->assertSame(['start_at' => '2026-11-07 20:00:00', 'restricted_school_id' => 3, 'school_restricted' => true], $row);

        $open = EventMapper::toUpdateRow(EventUpdateDto::fromArray(['restricted_school_id' => null]));
        $this->assertSame(['restricted_school_id' => null, 'school_restricted' => false], $open);
    }
}

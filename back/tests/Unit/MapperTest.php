<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Model\Mappers\CategoryMapper;
use App\Model\Mappers\EventMapper;
use Tests\TestCase;

final class MapperTest extends TestCase
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
        ];
    }

    public function testEventDtoExposesOnlyPublicFieldsInSnakeCase(): void
    {
        $json = EventMapper::toDto(self::eventRow(), [['slug' => 'bde', 'label' => 'BDE / Assoc']])->jsonSerialize();

        $this->assertSame([
            'id', 'title', 'slug', 'description', 'cover_image_url', 'start_at', 'end_at', 'venue_name',
            'address_line', 'city', 'postal_code', 'latitude', 'longitude', 'capacity', 'registered_count',
            'price_cents', 'currency', 'is_paid', 'external_ticket_url', 'school_restricted', 'categories',
        ], array_keys($json));
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
        $dtos = EventMapper::toDtos(
            [5 => self::eventRow(['id' => 1]), 9 => self::eventRow(['id' => 2])],
            [2 => [['slug' => 'sport', 'label' => 'Sport']], 99 => [['slug' => 'x', 'label' => 'X']]]
        );

        $this->assertSame([1, 2], array_map(static fn ($dto): int => $dto->id, $dtos), 'list, row order kept');
        $this->assertSame([], $dtos[0]->categories, 'event without categories');
        $this->assertSame('sport', $dtos[1]->categories[0]->slug);
        $this->assertSame([], EventMapper::toDtos([]));
    }

    /** Corps JSON minimal valide d'une creation d'event. */
    private static function input(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Soiree', 'slug' => 'soiree', 'description' => 'Desc',
            'start_at' => '2026-11-07T21:00:00+01:00', 'end_at' => '2026-11-08T02:00:00Z',
        ];
    }

    public function testFromInputAppliesColumnDefaultsAndIgnoresServerFields(): void
    {
        $dto = EventMapper::fromInput(self::input([
            'latitude' => '48.8689', 'id' => 99, 'creator_id' => 1, 'status' => 'published', 'registered_count' => 500,
        ]));

        $this->assertSame(48.8689, $dto->latitude, 'numeric string cast to float');
        $this->assertSame(0, $dto->priceCents);
        $this->assertSame('EUR', $dto->currency);
        $this->assertSame(false, $dto->isPaid);
        $this->assertSame(null, $dto->restrictedSchoolId);
    }

    public function testFromInputRejectsInvalidBodiesWith422PerField(): void
    {
        $cases = [
            'title'        => [self::input(['title' => '']), 'This field is required'],
            'end_at'       => [self::input(['end_at' => '2026-11-07T19:00:00Z']), 'Must be after start_at'],
            'latitude'     => [self::input(['latitude' => 91]), 'Must be at most 90'],
            'currency'     => [self::input(['currency' => 'EURO']), 'Must be at most 3 characters'],
            'capacity'     => [self::input(['capacity' => 0]), 'Must be at least 1'],
            'cover_image_url' => [self::input(['cover_image_url' => 'javascript:alert(1)']), 'Must be a valid http(s) URL'],
        ];
        foreach ($cases as $field => [$body, $message]) {
            $e = $this->assertThrows(HttpException::class, fn () => EventMapper::fromInput($body), $field);
            $this->assertSame(422, $e->status, $field);
            $this->assertSame($message, $e->details[$field] ?? null, $field);
        }
    }

    public function testToRowWritesDbDatetimesAndDerivesSchoolRestriction(): void
    {
        $row = EventMapper::toRow(EventMapper::fromInput(self::input()));

        $this->assertSame('2026-11-07 20:00:00', $row['start_at'], 'converted to UTC');
        $this->assertSame('2026-11-08 02:00:00', $row['end_at'], 'Z suffix');
        $this->assertSame(false, $row['school_restricted']);
        $this->assertSame(null, $row['restricted_school_id']);
        foreach (['id', 'registered_count', 'creator_id', 'status', 'categories'] as $key) {
            $this->assertFalse(array_key_exists($key, $row), "toRow writes $key");
        }

        $restricted = EventMapper::toRow(EventMapper::fromInput(self::input(['restricted_school_id' => 3])));
        $this->assertSame(true, $restricted['school_restricted']);
        $this->assertSame(3, $restricted['restricted_school_id']);
    }

    public function testCategoryRoundTrip(): void
    {
        $dto = CategoryMapper::toDto(['slug' => 'sport', 'label' => 'Sport', 'event_id' => 7]);

        $this->assertSame(['slug' => 'sport', 'label' => 'Sport'], $dto->jsonSerialize());
        $this->assertSame(['slug' => 'sport', 'label' => 'Sport'], CategoryMapper::toRow($dto));
        $this->assertSame([0], array_keys(CategoryMapper::toDtos([3 => ['slug' => 'a', 'label' => 'A']])), 're-indexed as a list');
    }
}

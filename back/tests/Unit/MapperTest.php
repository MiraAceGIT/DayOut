<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Dto\CategoryDto;
use App\Mappers\CategoryMapper;
use App\Mappers\EventMapper;
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

    public function testEventToRowKeepsOnlyWritableColumnsWithDbDatetimes(): void
    {
        $row = EventMapper::toRow(EventMapper::toDto(self::eventRow(['start_at' => '2026-11-07T21:00:00+01:00'])));

        $this->assertSame('2026-11-07 20:00:00', $row['start_at'], 'converted to UTC');
        $this->assertSame('2026-11-08 02:00:00', $row['end_at']);
        $this->assertSame('Soiree', $row['title']);
        foreach (['id', 'registered_count', 'school_restricted', 'restricted_school_id', 'creator_id', 'status', 'categories'] as $key) {
            $this->assertFalse(array_key_exists($key, $row), "toRow writes $key");
        }
    }

    public function testCategoryRoundTrip(): void
    {
        $dto = CategoryMapper::toDto(['slug' => 'sport', 'label' => 'Sport', 'event_id' => 7]);

        $this->assertSame(['slug' => 'sport', 'label' => 'Sport'], $dto->jsonSerialize());
        $this->assertSame(['slug' => 'sport', 'label' => 'Sport'], CategoryMapper::toRow($dto));
        $this->assertTrue(CategoryMapper::toDtos([3 => ['slug' => 'a', 'label' => 'A']])[0] instanceof CategoryDto, 'list');
    }
}

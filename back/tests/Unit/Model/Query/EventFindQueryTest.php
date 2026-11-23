<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use App\Core\ValidationException;
use App\Model\Query\EventFindQuery;
use DateTimeImmutable;
use Tests\TestCase;

final class EventFindQueryTest extends TestCase
{
    public function testDefaults(): void
    {
        $now = new DateTimeImmutable('2026-11-01T00:00:00Z');
        $params = EventFindQuery::fromQuery([])->toParams($now);

        $this->assertSame($now, $params->endingAfter);
        $this->assertSame(null, $params->text);
        $this->assertSame(null, $params->ids);
        $this->assertSame(null, $params->categories);
        $this->assertSame(null, $params->isFree);
        $this->assertSame(false, $params->onlyAvailable);
        $this->assertSame('start_at', $params->page->sortBy);
        $this->assertSame('asc', $params->page->sortDir);
        $this->assertSame(20, $params->page->limit);
        $this->assertSame(0, $params->page->offset);
    }

    public function testParsesCsvListsAndCastsScalars(): void
    {
        $query = EventFindQuery::fromQuery([
            'ids' => '3, 1,,3', 'categories' => ['soiree', ' sport '], 'q' => '  jazz ', 'is_free' => 'true',
            'available' => '1', 'creator_id' => '7', 'sort_by' => 'title', 'sort_dir' => 'desc', 'limit' => '5',
            'start_from' => '2026-11-07T00:00:00Z',
        ]);

        $this->assertSame([3, 1], $query->ids);
        $this->assertSame(['soiree', 'sport'], $query->categories);
        $this->assertSame('jazz', $query->q);
        $this->assertSame(true, $query->isFree);
        $this->assertSame(true, $query->available);
        $this->assertSame(7, $query->creatorId);
        $this->assertSame('title', $query->page->sortBy);
        $this->assertSame('desc', $query->page->sortDir);
        $this->assertSame(5, $query->page->limit);
        $this->assertSame('2026-11-07', $query->startFrom?->format('Y-m-d'));
    }

    public function testBlankValuesAreInactive(): void
    {
        $query = EventFindQuery::fromQuery(['q' => '   ', 'ids' => ' , ', 'categories' => '']);
        $this->assertSame(null, $query->q);
        $this->assertSame(null, $query->ids);
        $this->assertSame(null, $query->categories);
    }

    public function testInvalidValuesAre422(): void
    {
        foreach ([
            [['ids' => '1,abc'], 'ids'],
            [['ids' => '0'], 'ids'],
            [['categories' => 'Soiree'], 'categories'],
            [['categories' => [['x']]], 'categories'],
            [['sort_by' => 'password'], 'sort_by'],
            [['sort_dir' => 'up'], 'sort_dir'],
            [['is_free' => 'yes'], 'is_free'],
            [['start_to' => '2026-11-07'], 'start_to'],
            [['start_from' => '2026-11-09T00:00:00Z', 'start_to' => '2026-11-07T00:00:00Z'], 'start_to'],
            [['ids' => implode(',', range(1, 51))], 'ids'],
            [['lat' => '91', 'lng' => '2', 'radius' => '3'], 'lat'],
            [['lat' => '48', 'lng' => '181', 'radius' => '3'], 'lng'],
            [['lat' => '48', 'lng' => '2', 'radius' => '0'], 'radius'],
            [['lat' => '48', 'lng' => '2', 'radius' => '21'], 'radius'],
            [['lat' => 'abc', 'lng' => '2', 'radius' => '3'], 'lat'],
        ] as [$input, $field]) {
            $e = $this->assertThrows(ValidationException::class, fn () => EventFindQuery::fromQuery($input), $field);
            $this->assertSame([$field], array_keys($e->errors));
        }
    }

    public function testGeoParamsGoTogether(): void
    {
        foreach ([
            [['lat' => '48.8689'], 'lng'],
            [['lng' => '2.341'], 'lat'],
            [['radius' => '3'], 'lat'],
            [['lat' => '48.8689', 'lng' => '2.341'], 'radius'],
            [['lat' => '48.8689', 'radius' => '3'], 'lng'],
        ] as [$input, $missingField]) {
            $e = $this->assertThrows(ValidationException::class, fn () => EventFindQuery::fromQuery($input), $missingField);
            $this->assertSame([$missingField], array_keys($e->errors));
            $this->assertStringContains('lat, lng and radius', $e->errors[$missingField]);
        }
    }

    public function testGeoParamsPropagateToSearchParams(): void
    {
        $params = EventFindQuery::fromQuery(['lat' => '48.8689', 'lng' => '2.341', 'radius' => '3.5'])->toParams();

        $this->assertSame(48.8689, $params->latitude);
        $this->assertSame(2.341, $params->longitude);
        $this->assertSame(3.5, $params->radiusKm);
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use App\Core\ValidationException;
use App\Model\Query\CreatorFindQuery;
use DateTimeImmutable;
use Tests\TestCase;

final class CreatorFindQueryTest extends TestCase
{
    public function testDefaults(): void
    {
        $params = CreatorFindQuery::fromQuery([])->toParams();

        $this->assertSame(null, $params->text);
        $this->assertSame(null, $params->schoolId);
        $this->assertSame(null, $params->ids);
        $this->assertSame(null, $params->withEventsEndingAfter, 'no event filter unless has_upcoming_events');
        $this->assertSame('display_name', $params->sortBy);
        $this->assertSame('asc', $params->sortDir);
        $this->assertSame(20, $params->limit);
        $this->assertSame(0, $params->offset);
    }

    public function testParsesAndCastsValues(): void
    {
        $query = CreatorFindQuery::fromQuery([
            'q' => '  isep ', 'school_id' => '1', 'ids' => '3, 2,,3', 'has_upcoming_events' => 'true',
            'sort_by' => 'created_at', 'sort_dir' => 'desc', 'limit' => '5', 'offset' => '10',
        ]);

        $this->assertSame('isep', $query->q);
        $this->assertSame(1, $query->schoolId);
        $this->assertSame([3, 2], $query->ids);
        $this->assertSame(true, $query->hasUpcomingEvents);
        $this->assertSame('created_at', $query->sortBy);
        $this->assertSame('desc', $query->sortDir);
        $this->assertSame(5, $query->limit);
        $this->assertSame(10, $query->offset);
    }

    public function testUpcomingEventsUseFromOrNow(): void
    {
        $now = new DateTimeImmutable('2026-11-01T00:00:00Z');
        $this->assertSame($now, CreatorFindQuery::fromQuery(['has_upcoming_events' => '1'])->toParams($now)->withEventsEndingAfter);

        $from = CreatorFindQuery::fromQuery(['has_upcoming_events' => '1', 'from' => '2026-10-19T00:00:00Z'])->toParams($now);
        $this->assertSame('2026-10-19', $from->withEventsEndingAfter?->format('Y-m-d'));
    }

    public function testInvalidValuesAre422(): void
    {
        foreach ([
            [['ids' => '2,abc'], 'ids'],
            [['school_id' => '0'], 'school_id'],
            [['sort_by' => 'email'], 'sort_by'],
            [['sort_dir' => 'up'], 'sort_dir'],
            [['has_upcoming_events' => 'yes'], 'has_upcoming_events'],
            [['from' => '2026-11-07'], 'from'],
            [['limit' => '101'], 'limit'],
        ] as [$input, $field]) {
            $e = $this->assertThrows(ValidationException::class, fn () => CreatorFindQuery::fromQuery($input), $field);
            $this->assertSame([$field], array_keys($e->errors));
        }
    }
}

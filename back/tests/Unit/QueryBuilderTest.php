<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Orm\Query\QueryBuilder;
use InvalidArgumentException;
use Tests\TestCase;

/** SQL genere sans connexion : toSql() + getBindings(). */
final class QueryBuilderTest extends TestCase
{
    private function events(): QueryBuilder
    {
        return new QueryBuilder('events');
    }

    public function testBasicSelectWithBindings(): void
    {
        $q = $this->events()->select('id', 'title')->where('status', 'published')->where('capacity', '>=', 10)
            ->orderBy('start_at')->limit(20)->offset(40);

        $this->assertSame(
            'SELECT `id`, `title` FROM `events` WHERE `status` = ? AND `capacity` >= ? ORDER BY `start_at` ASC LIMIT 20 OFFSET 40',
            $q->toSql()
        );
        $this->assertSame(['published', 10], $q->getBindings());
    }

    public function testWhereRawIsAlwaysParenthesized(): void
    {
        $q = $this->events()->where('status', 'published')->whereRaw('city = ? OR city = ?', ['Paris', 'Lyon']);

        $this->assertSame('SELECT * FROM `events` WHERE `status` = ? AND (city = ? OR city = ?)', $q->toSql());
        $this->assertSame(['published', 'Paris', 'Lyon'], $q->getBindings());
    }

    public function testNestedGroup(): void
    {
        $q = $this->events()->where('status', 'published')
            ->where(fn (QueryBuilder $g) => $g->where('city', 'Paris')->orWhere('is_paid', false));

        $this->assertSame('SELECT * FROM `events` WHERE `status` = ? AND (`city` = ? OR `is_paid` = ?)', $q->toSql());
        $this->assertSame(['published', 'Paris', false], $q->getBindings());
    }

    public function testNullComparisons(): void
    {
        $this->assertSame('SELECT * FROM `events` WHERE `cover_image_url` IS NULL', $this->events()->where('cover_image_url', null)->toSql());
        $this->assertSame(
            'SELECT * FROM `events` WHERE `a` = ? OR `b` IS NULL',
            $this->events()->where('a', 1)->orWhere('b', null)->toSql()
        );
        $this->assertSame('SELECT * FROM `events` WHERE `a` IS NOT NULL', $this->events()->where('a', '<>', null)->toSql());
        $this->assertThrows(InvalidArgumentException::class, fn () => $this->events()->where('capacity', '>', null));
    }

    public function testEmptyWhereInMatchesNothingAndEmptyNotInMatchesEverything(): void
    {
        $this->assertSame('SELECT * FROM `events` WHERE 0 = 1', $this->events()->whereIn('id', [])->toSql());
        $this->assertSame('SELECT * FROM `events`', $this->events()->whereNotIn('id', [])->toSql());
        $this->assertSame(
            'SELECT * FROM `events` WHERE `id` IN (?, ?)',
            $this->events()->whereIn('id', [3 => 1, 7 => 2])->toSql()
        );
    }

    public function testAliasesJoinsAndInvalidIdentifiers(): void
    {
        $q = $this->events()->select('events.id', 'schools.name AS school')
            ->leftJoin('schools', 'schools.id', '=', 'events.restricted_school_id');
        $this->assertSame(
            'SELECT `events`.`id`, `schools`.`name` AS `school` FROM `events` LEFT JOIN `schools` ON `schools`.`id` = `events`.`restricted_school_id`',
            $q->toSql()
        );

        $this->assertThrows(InvalidArgumentException::class, fn () => $this->events()->where('id; DROP TABLE users', 1));
        $this->assertThrows(InvalidArgumentException::class, fn () => $this->events()->orderBy('id', 'SIDEWAYS'));
        $this->assertThrows(InvalidArgumentException::class, fn () => $this->events()->where('id', 'IS', 1));
    }

    public function testCloneDoesNotLeakConditions(): void
    {
        $base = $this->events()->where('status', 'published');
        $copy = (clone $base)->where('city', 'Paris');

        $this->assertSame(['published'], $base->getBindings());
        $this->assertSame(['published', 'Paris'], $copy->getBindings());
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Orm\Connection;
use App\Model\Dao\Category;
use PDO;
use PDOStatement;
use Tests\TestCase;

/** Compte les requetes preparees : verifie l'absence de N+1. */
final class CountingPdo extends PDO
{
    public int $queries = 0;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries++;
        return parent::prepare($query, $options);
    }
}

final class CategoryTest extends TestCase
{
    private CountingPdo $pdo;
    private Category $categories;

    public function setUp(): void
    {
        $this->pdo = new CountingPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, slug TEXT, label TEXT, icon TEXT, active INTEGER DEFAULT 1)');
        $this->pdo->exec('CREATE TABLE event_categories (event_id INTEGER, category_id INTEGER, PRIMARY KEY (event_id, category_id))');
        $this->pdo->exec("INSERT INTO categories (id, slug, label, active) VALUES
            (1, 'soiree', 'Soiree', 1), (2, 'bde', 'BDE / Assoc', 1), (3, 'tech', 'Tech', 1), (4, 'old', 'Ancienne', 0)");
        $this->pdo->exec('INSERT INTO event_categories (event_id, category_id) VALUES (1, 1), (1, 2), (2, 3), (3, 4), (99, 1)');
        $this->categories = new Category(new Connection($this->pdo));
    }

    public function testGroupsActiveCategoriesByEventInOneQuery(): void
    {
        $byEvent = $this->categories->listActiveByEvent([1, 2, 3, 4, 1]);

        $this->assertSame(1, $this->pdo->queries);
        $this->assertSame([
            1 => [['slug' => 'bde', 'label' => 'BDE / Assoc'], ['slug' => 'soiree', 'label' => 'Soiree']],
            2 => [['slug' => 'tech', 'label' => 'Tech']],
        ], $byEvent);
    }

    public function testInactiveCategoriesAndUnrequestedEventsAreExcluded(): void
    {
        $byEvent = $this->categories->listActiveByEvent([3]);

        $this->assertSame([], $byEvent);
    }

    public function testNoEventIdsRunsNoQuery(): void
    {
        $this->assertSame([], $this->categories->listActiveByEvent([]));
        $this->assertSame(0, $this->pdo->queries);
    }
}

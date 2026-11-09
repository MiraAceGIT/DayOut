<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Services;

use App\Core\Orm\Connection;
use App\Model\Dao\Creator\Creator;
use App\Model\Dto\Creator\CreatorDto;
use App\Model\Query\CreatorFindQuery;
use App\Model\Services\CreatorService;
use PDO;
use Tests\TestCase;

final class CreatorServiceTest extends TestCase
{
    private CreatorService $service;

    public function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, password_hash TEXT, first_name TEXT, last_name TEXT,
            school_id INTEGER, role TEXT, creator_status TEXT, avatar_url TEXT, bio TEXT, reliability_score INTEGER,
            banned INTEGER DEFAULT 0, created_at TEXT)');
        $pdo->exec('CREATE TABLE creator_profiles (user_id INTEGER PRIMARY KEY, display_name TEXT, description TEXT, review_notes TEXT)');
        $pdo->exec('CREATE TABLE schools (id INTEGER PRIMARY KEY, name TEXT, short_name TEXT)');
        $pdo->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, creator_id INTEGER, status TEXT, end_at TEXT)');
        $pdo->exec("INSERT INTO schools VALUES (1, 'Institut superieur', 'ISEP'), (2, 'Epita', NULL)");
        $pdo->exec("INSERT INTO users (id, email, password_hash, first_name, last_name, school_id, role, creator_status, bio, reliability_score, banned, created_at) VALUES
            (2, 'bde@isep.fr', 'secret-hash', 'BDE', 'ISEP', 1, 'creator', 'approved', NULL, 100, 0, '2026-01-02 00:00:00'),
            (3, 'orga@epita.fr', 'secret-hash', 'Clement', 'Epita', 2, 'creator', 'approved', 'Bio', 100, 0, '2026-01-03 00:00:00'),
            (4, 'solo@ex.fr', 'secret-hash', 'Diane', 'Solo', NULL, 'creator', 'approved', NULL, 100, 0, '2026-01-01 00:00:00'),
            (5, 'pending@ex.fr', 'secret-hash', 'Enzo', 'Pending', 1, 'participant', 'pending', NULL, 100, 0, '2026-01-04 00:00:00'),
            (6, 'banned@ex.fr', 'secret-hash', 'Bob', 'Banned', 1, 'creator', 'approved', NULL, 100, 1, '2026-01-05 00:00:00'),
            (7, 'user@isep.fr', 'secret-hash', 'Yo', 'User', 1, 'participant', 'none', NULL, 100, 0, '2026-01-06 00:00:00')");
        $pdo->exec("INSERT INTO creator_profiles VALUES (2, 'BDE ISEP', 'Le bureau des eleves', 'secret-review'),
            (3, 'Clement Events', NULL, NULL), (4, 'Atelier 100% Diane', NULL, NULL), (6, 'Banni', NULL, NULL)");
        $pdo->exec("INSERT INTO events VALUES
            (1, 2, 'published', '2026-11-08 02:00:00'),
            (2, 3, 'published', '2026-10-01 12:00:00'),
            (3, 4, 'draft', '2026-12-01 12:00:00')");

        $this->service = new CreatorService(new Creator(new Connection($pdo)));
    }

    /**
     * @param array<string, mixed> $query
     * @return list<int>
     */
    private function search(array $query): array
    {
        $page = $this->service->searchPage(CreatorFindQuery::fromQuery($query)->toParams());
        return array_map(static fn (CreatorDto $c): int => $c->id, $page->items);
    }

    public function testListsOnlyApprovedNotBannedCreatorsSortedByDisplayName(): void
    {
        $this->assertSame([4, 2, 3], $this->search([]), 'Atelier, BDE, Clement ; pending, banned and participants hidden');
    }

    public function testFilters(): void
    {
        $this->assertSame([2], $this->search(['q' => 'isep']), 'display name');
        $this->assertSame([3], $this->search(['q' => 'clem']), 'first name');
        $this->assertSame([4], $this->search(['q' => '100%']), '% is literal');
        $this->assertSame([], $this->search(['q' => '1_0']), '_ is literal');
        $this->assertSame([2], $this->search(['school_id' => '1']));
        $this->assertSame([2, 3], $this->search(['ids' => '3,2,5,6']), 'pending and banned ids are dropped');
        $this->assertSame([2], $this->search(['has_upcoming_events' => 'true', 'from' => '2026-10-19T00:00:00Z']),
            'ended (3) and draft (4) events do not count');
        $this->assertSame([3, 2, 4], $this->search(['sort_by' => 'created_at', 'sort_dir' => 'desc']));
    }

    public function testPageReportsTotalAndHasMore(): void
    {
        $first = $this->service->searchPage(CreatorFindQuery::fromQuery(['limit' => '2'])->toParams());
        $last  = $this->service->searchPage(CreatorFindQuery::fromQuery(['limit' => '2', 'offset' => '2'])->toParams());

        $this->assertCount(2, $first->items);
        $this->assertSame(3, $first->total);
        $this->assertSame(true, $first->hasMore);
        $this->assertCount(1, $last->items);
        $this->assertSame(false, $last->hasMore);
    }

    public function testExposesOnlyThePublicProfile(): void
    {
        $page = $this->service->searchPage(CreatorFindQuery::fromQuery(['ids' => '2,3'])->toParams());
        [$bde, $clement] = $page->items;

        $this->assertSame('BDE ISEP', $bde->displayName);
        $this->assertSame('Le bureau des eleves', $bde->description);
        $this->assertSame('ISEP', $bde->schoolName);
        $this->assertSame('Bio', $clement->description, 'profile without description: user bio');
        $this->assertSame('Epita', $clement->schoolName, 'no short name: school name');

        $json = json_encode($page, JSON_THROW_ON_ERROR);
        foreach (['@', 'secret', 'reliability', 'role', 'creator_status'] as $private) {
            $this->assertStringNotContains($private, $json);
        }
    }
}

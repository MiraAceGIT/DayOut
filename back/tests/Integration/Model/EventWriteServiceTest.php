<?php
declare(strict_types=1);

namespace Tests\Integration\Model;

use App\Core\Http\DbErrorMapper;
use App\Core\Orm\Connection;
use App\Core\ValidationException;
use App\Model\Dao\Category\Category;
use App\Model\Dao\Creator\Creator;
use App\Model\Dao\Event\Event;
use App\Model\Dto\Event\EventCreateDto;
use App\Model\Dto\Event\EventUpdateDto;
use App\Model\Exceptions\EventException;
use App\Model\Services\EventService;
use PDO;
use PDOException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

/** Ecritures contre MariaDB (triggers, CHECK, cles etrangeres) ; chaque test est annule par rollback. */
final class EventWriteServiceTest extends TestCase
{
    private const CREATOR = 2;

    private PDO $pdo;
    private EventService $service;

    public function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        $this->pdo->beginTransaction();
        $connection = new Connection($this->pdo);
        $this->service = new EventService(new Event($connection), new Category($connection), new Creator($connection));
    }

    public function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    /** @param array<string, mixed> $overrides */
    private static function body(array $overrides = []): EventCreateDto
    {
        return EventCreateDto::fromArray($overrides + [
            'title' => 'Test integration', 'slug' => 'test-integration', 'description' => 'd',
            'start_at' => '2030-05-01T18:00:00Z', 'end_at' => '2030-05-01T23:00:00Z',
        ]);
    }

    private function dbError(callable $write): int
    {
        $e = $this->assertThrows(PDOException::class, $write);
        return (DbErrorMapper::toHttpException($e) ?? throw $e)->status;
    }

    private function scalar(string $sql): mixed
    {
        return ($this->pdo->query($sql) ?: throw new \LogicException($sql))->fetchColumn();
    }

    public function testSessionIsStrictAndTheFeedIndexExists(): void
    {
        $this->assertStringContains('STRICT_ALL_TABLES', (string) $this->scalar('SELECT @@SESSION.sql_mode'));
        $this->assertSame('1', (string) $this->scalar(
            "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'events' AND index_name = 'idx_events_status_end'"
        ));
    }

    public function testDatabaseConstraintsMapToClientErrors(): void
    {
        $taken = (string) $this->scalar('SELECT slug FROM events WHERE id = 1');
        $this->assertSame(409, $this->dbError(fn () => $this->service->create(self::body(['slug' => $taken]), self::CREATOR)));
        $this->assertSame(422, $this->dbError(fn () => $this->service->create(self::body(['restricted_school_id' => 999999]), self::CREATOR)));
    }

    public function testDraftLifecycle(): void
    {
        $draft = $this->service->create(self::body(['restricted_school_id' => 1]), self::CREATOR);
        $this->assertSame('draft', $draft->status);
        $this->assertSame(true, $draft->schoolRestricted, 'trg_events_bi keeps school_restricted consistent');

        $this->assertSame(false, $this->service->update($draft->id, EventUpdateDto::fromArray(['restricted_school_id' => null]), self::CREATOR)->schoolRestricted);
        $this->assertSame('published', $this->service->update($draft->id, EventUpdateDto::fromArray(['status' => 'published']), self::CREATOR)->status);
        $this->assertSame($draft->id, $this->service->findOne($draft->id)->id);

        $this->assertSame('EVENT_INVALID_STATUS', $this->assertThrows(EventException::class, fn () => $this->service->delete($draft->id, self::CREATOR))->errorCode);
        $this->assertSame('cancelled', $this->service->update($draft->id, EventUpdateDto::fromArray(['status' => 'cancelled']), self::CREATOR)->status);

        $other = $this->service->create(self::body(['slug' => 'a-supprimer']), self::CREATOR);
        $this->service->delete($other->id, self::CREATOR);
        $this->assertSame('0', (string) $this->scalar("SELECT COUNT(*) FROM events WHERE id = {$other->id}"));
    }

    public function testRegisteredEventKeepsItsRegistrationsSafe(): void
    {
        $event = (int) $this->scalar('SELECT id FROM events WHERE creator_id = ' . self::CREATOR . " AND status = 'published' AND registered_count > 0 ORDER BY id LIMIT 1");
        $count = (int) $this->scalar("SELECT registered_count FROM events WHERE id = $event");

        $capacity = $this->assertThrows(ValidationException::class,
            fn () => $this->service->update($event, EventUpdateDto::fromArray(['capacity' => $count - 1]), self::CREATOR));
        $this->assertSame(['capacity'], array_keys($capacity->errors));

        $locked = $this->assertThrows(EventException::class,
            fn () => $this->service->update($event, EventUpdateDto::fromArray(['end_at' => '2030-01-01T00:00:00Z']), self::CREATOR));
        $this->assertSame('EVENT_FIELDS_LOCKED', $locked->errorCode);

        $registrations = (int) $this->scalar("SELECT COUNT(*) FROM registrations WHERE event_id = $event");
        $this->assertThrows(EventException::class, fn () => $this->service->delete($event, self::CREATOR));
        $this->assertSame($registrations, (int) $this->scalar("SELECT COUNT(*) FROM registrations WHERE event_id = $event"));
    }
}

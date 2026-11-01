<?php
declare(strict_types=1);

namespace Tests\Unit\Core\Orm;

use App\Core\Orm\Connection;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class ConnectionTest extends TestCase
{
    private Connection $db;

    public function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)');
        $this->db = new Connection($pdo);
    }

    /** @return array<int, mixed> */
    private function titles(): array
    {
        return $this->db->table('notes')->orderBy('id')->pluck('title');
    }

    private static function insert(Connection $db, string $title): int
    {
        return $db->table('notes')->insert(['title' => $title]);
    }

    public function testTransactionCommitsAndReturnsTheCallbackValue(): void
    {
        $id = $this->db->transaction(fn (Connection $db): int => self::insert($db, 'a'));

        $this->assertSame(1, $id);
        $this->assertSame(['a'], $this->titles());
    }

    public function testFailureRollsEverythingBack(): void
    {
        $this->assertThrows(RuntimeException::class, fn () => $this->db->transaction(function (Connection $db): void {
            self::insert($db, 'a');
            throw new RuntimeException('boom');
        }));
        $this->assertSame([], $this->titles());
    }

    public function testCaughtNestedFailureOnlyUndoesItsOwnWork(): void
    {
        $this->db->transaction(function (Connection $db): void {
            self::insert($db, 'outer');
            try {
                $db->transaction(function (Connection $db): void {
                    self::insert($db, 'inner');
                    throw new RuntimeException('inner failed');
                });
            } catch (RuntimeException) {
                // l'appelant decide de continuer sans le travail interne
            }
            $db->transaction(fn (Connection $db): int => self::insert($db, 'after'));
        });

        $this->assertSame(['outer', 'after'], $this->titles());
    }

    public function testUncaughtNestedFailureRollsBackTheOuterTransaction(): void
    {
        $this->assertThrows(RuntimeException::class, fn () => $this->db->transaction(function (Connection $db): void {
            self::insert($db, 'outer');
            $db->transaction(static function (): void {
                throw new RuntimeException('inner failed');
            });
        }));
        $this->assertSame([], $this->titles());

        $this->db->transaction(fn (Connection $db): int => self::insert($db, 'next'));
        $this->assertSame(['next'], $this->titles(), 'depth reset after a failure');
    }
}

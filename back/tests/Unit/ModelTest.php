<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Core\Orm\Connection;
use App\Model\Dao\Model;
use PDO;
use Tests\TestCase;

/** Model de test sur SQLite en memoire : ecritures reelles sans MariaDB. */
final class Note extends Model
{
    protected const TABLE    = 'notes';
    protected const COLUMNS  = ['id', 'title', 'pinned', 'author_id'];
    protected const FILLABLE = ['title', 'pinned'];
    protected const CASTS    = ['id' => 'int', 'pinned' => 'bool', 'author_id' => 'int'];

    public function byId(int $id): ?array
    {
        return $this->find($id);
    }
}

final class ModelTest extends TestCase
{
    private Note $notes;

    public function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, pinned INTEGER DEFAULT 0, author_id INTEGER)');
        $this->notes = new Note(new Connection($pdo));
    }

    public function testCreateKeepsOnlyFillableInputPlusServerFields(): void
    {
        $id = $this->notes->create(['title' => 'Hello', 'pinned' => 1, 'author_id' => 666, 'id' => 99], ['author_id' => 7]);

        $this->assertSame(['id' => $id, 'title' => 'Hello', 'pinned' => true, 'author_id' => 7], $this->notes->byId($id));
        $this->assertNotSame(99, $id);
    }

    public function testServerFieldsAloneAreEnough(): void
    {
        $id = $this->notes->create([], ['title' => 'From server']);
        $this->assertSame('From server', $this->notes->byId($id)['title']);
    }

    public function testNothingWritableIsAClientError(): void
    {
        $id = $this->notes->create(['title' => 'x']);

        foreach ([[], ['unknown' => 1], ['author_id' => 3]] as $input) {
            $e = $this->assertThrows(HttpException::class, fn () => $this->notes->updateById($id, $input), json_encode($input));
            $this->assertSame(400, $e->status);
        }
    }

    public function testUpdateAndDeleteById(): void
    {
        $id = $this->notes->create(['title' => 'Old']);

        $this->assertSame(1, $this->notes->updateById($id, ['title' => 'New']));
        $this->assertSame('New', $this->notes->byId($id)['title']);
        $this->assertSame(1, $this->notes->deleteById($id));
        $this->assertSame(null, $this->notes->byId($id));
    }

    public function testGenericFindersAreNotPublic(): void
    {
        foreach (['find', 'findMany', 'all'] as $method) {
            $this->assertFalse((new \ReflectionMethod(Model::class, $method))->isPublic(), $method);
        }
    }
}

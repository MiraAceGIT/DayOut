<?php
declare(strict_types=1);

namespace Tests\Integration\Model;

use App\Core\Orm\Connection;
use App\Model\Dao\Faq\Faq;
use App\Model\Dao\Faq\FaqAdmin;
use App\Model\Dao\Faq\FaqSearchParams;
use App\Model\Dto\Faq\FaqCreateDto;
use App\Model\Dto\Faq\FaqUpdateDto;
use App\Model\Exceptions\FaqException;
use App\Model\Services\FaqService;
use PDO;
use Tests\Support\TestDatabase;
use Tests\TestCase;

final class FaqWriteServiceTest extends TestCase
{
    private PDO $pdo;
    private FaqService $service;

    public function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        $this->pdo->beginTransaction();
        $connection = new Connection($this->pdo);
        $this->service = new FaqService(new Faq($connection), new FaqAdmin($connection));
    }

    public function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    public function testAdminLifecycleAgainstMariaDb(): void
    {
        $entry = $this->service->create(FaqCreateDto::fromArray([
            'question' => 'FAQ integration 100%_!', 'answer' => str_repeat('😀', 16000), 'sort_order' => '4294967295',
        ]), 1);
        $this->assertSame(16000, mb_strlen($entry->answer));
        $this->assertSame(4294967295, $entry->sortOrder);
        $this->assertSame($entry->id, $this->service->findOne($entry->id)->id);
        $this->assertSame([$entry->id], array_column($this->service->findMany(new FaqSearchParams(text: '100%_!'))->items, 'id'));

        $hidden = $this->service->update($entry->id, FaqUpdateDto::fromArray(['active' => false, 'sort_order' => 0]), 1);
        $this->assertFalse($hidden->active);
        $this->assertSame(0, $hidden->sortOrder);
        $this->assertThrows(FaqException::class, fn () => $this->service->findOne($entry->id));
        $this->assertSame(0, $this->service->findMany(new FaqSearchParams(ids: [$entry->id]))->total);
        $this->assertTrue($this->service->update($entry->id, FaqUpdateDto::fromArray(['active' => true]), 1)->active);
        $this->assertTrue($this->service->update($entry->id, FaqUpdateDto::fromArray(['active' => true]), 1)->active);

        $this->service->delete($entry->id, 1);
        $error = $this->assertThrows(FaqException::class, fn () => $this->service->delete($entry->id, 1));
        $this->assertSame('FAQ_NOT_FOUND', $error->errorCode);
    }

    public function testCreatorCannotManageFaq(): void
    {
        $error = $this->assertThrows(FaqException::class,
            fn () => $this->service->create(FaqCreateDto::fromArray(['question' => 'Q', 'answer' => 'R']), 2));
        $this->assertSame('FAQ_ADMIN_REQUIRED', $error->errorCode);
    }
}

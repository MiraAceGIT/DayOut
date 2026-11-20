<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Services;

use App\Controller\FaqController;
use App\Core\Http\RequireAuth;
use App\Core\Orm\Connection;
use App\Core\Request;
use App\Model\Dao\Faq\Faq;
use App\Model\Dao\Faq\FaqAdmin;
use App\Model\Dao\Faq\FaqSearchParams;
use App\Model\Dao\PageRequest;
use App\Model\Dto\Faq\FaqCreateDto;
use App\Model\Dto\Faq\FaqUpdateDto;
use App\Model\Exceptions\FaqException;
use App\Model\Services\FaqService;
use PDO;
use Tests\TestCase;

final class FaqServiceTest extends TestCase
{
    private PDO $pdo;
    private FaqService $service;

    public function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE faq_entries (
            id INTEGER PRIMARY KEY, question TEXT, answer TEXT, sort_order INTEGER, active INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT, banned INTEGER)');
        $this->pdo->exec("INSERT INTO users VALUES (1, 'admin', 0), (2, 'creator', 0), (3, 'participant', 0), (4, 'admin', 1)");
        $connection = new Connection($this->pdo);
        $this->service = new FaqService(new Faq($connection), new FaqAdmin($connection));
    }

    public function testFiltersHiddenQuestionsAndOrdersByPositionThenId(): void
    {
        $this->pdo->exec("INSERT INTO faq_entries (id, question, answer, sort_order, active) VALUES
            (1, 'Derniere', 'C', 30, 1),
            (2, 'Cachee', 'Secret', 0, 0),
            (3, 'Premiere', 'A', 10, 1),
            (4, 'Deuxieme', 'B', 10, 1)");

        $page = $this->service->findMany(new FaqSearchParams());
        $entries = $page->items;
        $this->assertSame([3, 4, 1], array_column($entries, 'id'));
        $this->assertSame(['id', 'question', 'answer', 'sort_order', 'active'], array_keys($entries[0]->jsonSerialize()));
        $this->assertSame(3, $page->total);
        $this->assertFalse($page->hasMore);
    }

    public function testReturnsAnEmptyListWhenNoneAreVisible(): void
    {
        $this->assertSame([], $this->service->findMany(new FaqSearchParams())->items);
        $this->pdo->exec("INSERT INTO faq_entries (id, question, answer, sort_order, active) VALUES (1, 'Cachee', 'Secret', 0, 0)");
        $this->assertSame(0, $this->service->findMany(new FaqSearchParams())->total);
    }

    public function testControllerPreservesTextForSafeRenderingByTheClient(): void
    {
        $text = '<script>alert("test")</script> & deja <b>inscrit</b> ?';
        $this->pdo->prepare('INSERT INTO faq_entries (id, question, answer, sort_order, active) VALUES (1, ?, ?, 1, 1)')->execute([$text, $text]);
        $response = (new FaqController($this->service))->findMany(new Request('GET', '/faq'));
        $json = json_decode(json_encode(self::payload($response), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([['id' => 1, 'question' => $text, 'answer' => $text, 'sort_order' => 1, 'active' => true]], $json['data']['items']);
    }

    public function testSearchCombinesTextIdsAndVisibilityBeforePagination(): void
    {
        $this->pdo->exec("INSERT INTO faq_entries (id, question, answer, sort_order, active) VALUES
            (1, 'Compte', 'Inscription ecole', 10, 1),
            (2, 'Inscription', 'Secret', 0, 0),
            (3, 'Inscription', 'Publique', 10, 1),
            (4, 'Autre', 'Sans rapport', 20, 1)");

        $page = $this->service->findMany(new FaqSearchParams(text: 'inscription', ids: [1, 2, 3], page: new PageRequest(limit: 1)));
        $this->assertSame([1], array_column($page->items, 'id'));
        $this->assertSame(2, $page->total);
        $this->assertTrue($page->hasMore);
        $last = $this->service->findMany(new FaqSearchParams(text: 'inscription', page: new PageRequest(limit: 1, offset: 1)));
        $this->assertSame([3], array_column($last->items, 'id'));
        $this->assertFalse($last->hasMore);
        $past = $this->service->findMany(new FaqSearchParams(text: 'inscription', page: new PageRequest(offset: 5)));
        $this->assertSame([], $past->items);
        $this->assertSame(2, $past->total);
        $this->assertFalse($past->hasMore);
        $this->assertSame([3, 1], array_column($this->service->findMany(new FaqSearchParams(text: 'inscription', page: new PageRequest(sortDir: 'desc')))->items, 'id'));
        $this->assertSame([1], array_column($this->service->findMany(new FaqSearchParams(text: 'inscription', ids: [1, 2]))->items, 'id'));
    }

    public function testSearchTreatsSqlAndLikeMetacharactersAsLiteralText(): void
    {
        $this->pdo->exec("INSERT INTO faq_entries (id, question, answer, sort_order, active) VALUES
            (1, '100%_!', 'Oui', 0, 1), (2, '100abc', 'Non', 0, 1), (3, 'Cachee', '100%_!', 0, 0)");
        foreach (['%', '_', '!'] as $text) {
            $this->assertSame([1], array_column($this->service->findMany(new FaqSearchParams(text: $text))->items, 'id'));
        }
        $this->assertSame(0, $this->service->findMany(new FaqSearchParams(text: "' OR 1=1 --"))->total);
    }

    public function testAdminCanCreateEditHideReactivateAndDeleteAnEntry(): void
    {
        $entry = $this->service->create(FaqCreateDto::fromArray(['question' => 'Question', 'answer' => 'Reponse', 'id' => 999]), 1);
        $this->assertNotSame(999, $entry->id);
        $this->assertSame(0, $entry->sortOrder);
        $this->assertTrue($entry->active);
        $this->assertSame($entry->jsonSerialize(), $this->service->findOne($entry->id)->jsonSerialize());

        $hidden = $this->service->update($entry->id, FaqUpdateDto::fromArray(['active' => false, 'sort_order' => 5]), 1);
        $this->assertFalse($hidden->active);
        $this->assertSame('Reponse', $hidden->answer);
        $this->assertSame(5, $hidden->sortOrder);
        $this->assertThrows(FaqException::class, fn () => $this->service->findOne($entry->id));
        $this->assertSame(0, $this->service->findMany(new FaqSearchParams())->total);

        $updated = $this->service->update($entry->id, FaqUpdateDto::fromArray(['active' => true, 'sort_order' => 0, 'answer' => 'Nouvelle reponse']), 1);
        $this->assertSame('Question', $updated->question);
        $this->assertSame('Nouvelle reponse', $updated->answer);
        $this->assertSame(0, $updated->sortOrder);
        $this->assertTrue($updated->active);
        $this->assertSame($updated->jsonSerialize(), $this->service->update($entry->id, FaqUpdateDto::fromArray(['active' => true]), 1)->jsonSerialize());

        $this->service->delete($entry->id, 1);
        foreach ([
            fn () => $this->service->findOne($entry->id),
            fn () => $this->service->update($entry->id, FaqUpdateDto::fromArray(['answer' => 'x']), 1),
            fn () => $this->service->delete($entry->id, 1),
        ] as $action) {
            $error = $this->assertThrows(FaqException::class, $action);
            $this->assertSame('FAQ_NOT_FOUND', $error->errorCode);
            $this->assertSame(['id' => $entry->id], $error->details);
        }
    }

    public function testOnlyAnActiveAdminCanWrite(): void
    {
        $dto = FaqCreateDto::fromArray(['question' => 'Q', 'answer' => 'R', 'active' => false]);
        $entry = $this->service->create($dto, 1);
        foreach ([2, 3, 4, 999] as $userId) {
            foreach ([
                fn () => $this->service->create($dto, $userId),
                fn () => $this->service->update($entry->id, FaqUpdateDto::fromArray(['active' => true]), $userId),
                fn () => $this->service->delete($entry->id, $userId),
            ] as $action) {
                $error = $this->assertThrows(FaqException::class, $action);
                $this->assertSame('FAQ_ADMIN_REQUIRED', $error->errorCode);
            }
        }
        $this->assertFalse($this->service->update($entry->id, FaqUpdateDto::fromArray(['answer' => 'Toujours cachee']), 1)->active);
        $this->service->delete($entry->id, 1);
        $this->assertSame(0, $this->service->findMany(new FaqSearchParams())->total);
    }

    public function testControllerReturnsRestStatusesAndCreatedEntry(): void
    {
        $controller = new FaqController($this->service);
        $create = $controller->create(new Request('POST', '/faq', headers: ['content-type' => 'application/json'],
            rawBody: '{"question":"Q","answer":"R"}', attributes: [RequireAuth::USER_ID => 1]));
        $this->assertSame(201, $create->status);
        $id = self::payload($create)['data']->id;
        $this->assertSame('Q', self::payload($create)['data']->question);

        $update = $controller->update(new Request('PATCH', '/faq/' . $id, headers: ['content-type' => 'application/json'],
            rawBody: '{"answer":"Modifiee"}', params: ['id' => (string) $id], attributes: [RequireAuth::USER_ID => 1]));
        $this->assertSame(200, $update->status);
        $this->assertSame('Modifiee', self::payload($update)['data']->answer);
        $read = $controller->findOne(new Request('GET', '/faq/' . $id, params: ['id' => (string) $id]));
        $this->assertSame(200, $read->status);
        $this->assertSame('Modifiee', self::payload($read)['data']->answer);

        $delete = $controller->delete(new Request('DELETE', '/faq/' . $id, params: ['id' => (string) $id], attributes: [RequireAuth::USER_ID => 1]));
        $this->assertSame(204, $delete->status);
        $this->assertSame(null, $delete->jsonBody());
    }
}

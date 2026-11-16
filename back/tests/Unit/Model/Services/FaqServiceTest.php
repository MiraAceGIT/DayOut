<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Services;

use App\Controller\FaqController;
use App\Core\Orm\Connection;
use App\Core\Request;
use App\Model\Dao\Faq\Faq;
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
            id INTEGER PRIMARY KEY, question TEXT, answer TEXT, sort_order INTEGER, active INTEGER)');
        $this->service = new FaqService(new Faq(new Connection($this->pdo)));
    }

    public function testFiltersHiddenQuestionsAndOrdersByPositionThenId(): void
    {
        $this->pdo->exec("INSERT INTO faq_entries VALUES
            (1, 'Derniere', 'C', 30, 1),
            (2, 'Cachee', 'Secret', 0, 0),
            (3, 'Premiere', 'A', 10, 1),
            (4, 'Deuxieme', 'B', 10, 1)");

        $entries = $this->service->listVisible();
        $this->assertSame([3, 4, 1], array_column($entries, 'id'));
        $this->assertSame(['id', 'question', 'answer'], array_keys($entries[0]->jsonSerialize()));
    }

    public function testReturnsAnEmptyListWhenNoneAreVisible(): void
    {
        $this->assertSame([], $this->service->listVisible());
        $this->pdo->exec("INSERT INTO faq_entries VALUES (1, 'Cachee', 'Secret', 0, 0)");
        $this->assertSame([], $this->service->listVisible());
    }

    public function testControllerPreservesTextForSafeRenderingByTheClient(): void
    {
        $text = '<script>alert("test")</script> & deja <b>inscrit</b> ?';
        $this->pdo->prepare('INSERT INTO faq_entries VALUES (1, ?, ?, 1, 1)')->execute([$text, $text]);
        $response = (new FaqController($this->service))->index(new Request('GET', '/faq'));
        $json = json_decode(json_encode(self::payload($response), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([['id' => 1, 'question' => $text, 'answer' => $text]], $json['data']);
    }
}

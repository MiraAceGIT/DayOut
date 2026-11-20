<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use App\Core\ValidationException;
use App\Model\Query\FaqFindQuery;
use Tests\TestCase;

final class FaqFindQueryTest extends TestCase
{
    public function testDefaultsAndBlankFilters(): void
    {
        $params = FaqFindQuery::fromQuery(['q' => '   ', 'ids' => ', '])->toParams();
        $this->assertSame(null, $params->text);
        $this->assertSame(null, $params->ids);
        $this->assertSame('sort_order', $params->page->sortBy);
        $this->assertSame('asc', $params->page->sortDir);
        $this->assertSame(20, $params->page->limit);
        $this->assertSame(0, $params->page->offset);
    }

    public function testParsesFiltersAndPagination(): void
    {
        $params = FaqFindQuery::fromQuery([
            'q' => ' inscription ', 'ids' => '3,1,,3', 'sort_by' => 'question', 'sort_dir' => 'desc', 'limit' => '5', 'offset' => '10',
        ])->toParams();
        $this->assertSame('inscription', $params->text);
        $this->assertSame([3, 1], $params->ids);
        $this->assertSame('question', $params->page->sortBy);
        $this->assertSame('desc', $params->page->sortDir);
        $this->assertSame(5, $params->page->limit);
        $this->assertSame(10, $params->page->offset);
        $this->assertSame([3, 1], FaqFindQuery::fromQuery(['ids' => ['3', '1']])->toParams()->ids);
    }

    public function testRejectsInvalidFilters(): void
    {
        foreach ([
            ['q' => ['x']], ['q' => str_repeat('x', 101)], ['ids' => '0'], ['ids' => '1,no'], ['ids' => [['1']]],
            ['ids' => implode(',', range(1, 51))], ['ids' => '9223372036854775808'],
            ['sort_by' => 'password'], ['sort_dir' => 'up'], ['limit' => '0'], ['limit' => '101'], ['limit' => '1.5'],
            ['offset' => '-1'], ['offset' => '10001'],
        ] as $input) {
            $error = $this->assertThrows(ValidationException::class, fn () => FaqFindQuery::fromQuery($input));
            $this->assertSame(array_keys($input), array_keys($error->errors));
        }
    }
}

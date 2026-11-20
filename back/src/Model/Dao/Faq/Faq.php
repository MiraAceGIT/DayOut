<?php
declare(strict_types=1);

namespace App\Model\Dao\Faq;

use App\Core\Orm\Query\QueryBuilder;
use App\Model\Dao\Model;

final class Faq extends Model
{
    protected const TABLE = 'faq_entries';
    protected const COLUMNS = ['id', 'question', 'answer', 'sort_order', 'active'];
    protected const FILLABLE = ['question', 'answer', 'sort_order', 'active'];
    protected const CASTS = ['id' => 'int', 'sort_order' => 'int', 'active' => 'bool'];

    private const SORT_MAP = [
        'sort_order' => 'sort_order',
        'question'   => 'question',
        'created_at' => 'created_at',
    ];

    /** @return array{0: array<int, array<string, mixed>>, 1: int} */
    public function findManyVisible(?FaqSearchParams $params = null): array
    {
        $params ??= new FaqSearchParams();
        $query = $this->visible();

        if ($params->text !== null) {
            $like = self::containsPattern($params->text);
            $query->whereRaw("question LIKE ? ESCAPE '!' OR answer LIKE ? ESCAPE '!'", [$like, $like]);
        }
        if ($params->ids) {
            $query->whereIn('id', $params->ids);
        }

        $total = $query->count();
        $orderColumn = self::SORT_MAP[$params->sortBy] ?? self::SORT_MAP['sort_order'];
        $orderDir = $params->sortDir === 'desc' ? 'DESC' : 'ASC';
        $items = $query
            ->orderBy($orderColumn, $orderDir)
            ->orderBy('id', $orderDir)
            ->limit($params->limit)
            ->offset($params->offset)
            ->get();

        return [$items, $total];
    }

    public function findVisibleById(int $id): ?array
    {
        return $this->visible()->where('id', $id)->first();
    }

    public function findForWriteById(int $id): ?array
    {
        return $this->find($id);
    }

    private function visible(): QueryBuilder
    {
        return $this->query()->where('active', true);
    }
}

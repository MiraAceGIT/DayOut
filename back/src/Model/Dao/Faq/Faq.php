<?php
declare(strict_types=1);

namespace App\Model\Dao\Faq;

use App\Core\Orm\Query\QueryBuilder;
use App\Model\Dao\Dao;

final class Faq extends Dao
{
    protected const TABLE = 'faq_entries';
    protected const COLUMNS = ['id', 'question', 'answer', 'sort_order', 'active'];
    protected const FILLABLE = ['question', 'answer', 'sort_order', 'active'];
    protected const CASTS = ['id' => 'int', 'sort_order' => 'int', 'active' => 'bool'];

    /** Cle publique (sort_by) => colonne ; la premiere est le tri par defaut. */
    public const SORT_MAP = [
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

        return $this->paginate($query, $params->page, self::SORT_MAP);
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

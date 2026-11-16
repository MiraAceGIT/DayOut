<?php
declare(strict_types=1);

namespace App\Model\Dao\Faq;

use App\Model\Dao\Model;

final class Faq extends Model
{
    protected const TABLE = 'faq_entries';
    protected const COLUMNS = ['id', 'question', 'answer'];
    protected const CASTS = ['id' => 'int'];

    /** @return array<int, array<string, mixed>> */
    public function findVisible(): array
    {
        return $this->query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}

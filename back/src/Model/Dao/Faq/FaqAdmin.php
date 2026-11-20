<?php
declare(strict_types=1);

namespace App\Model\Dao\Faq;

use App\Model\Dao\Model;

final class FaqAdmin extends Model
{
    protected const TABLE = 'users';
    protected const COLUMNS = ['id'];

    public function existsById(int $id): bool
    {
        return $this->table()->where('id', $id)->where('role', 'admin')->where('banned', false)->exists();
    }
}

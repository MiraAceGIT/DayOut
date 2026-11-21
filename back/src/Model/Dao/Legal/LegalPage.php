<?php declare(strict_types=1);

namespace App\Model\Dao\Legal;

use App\Model\Dao\Model;

final class LegalPage extends Model
{
    protected const TABLE = 'legal_page';
    protected const PRIMARY_KEY = 'slug';
    protected const COLUMNS = ['slug', 'title', 'body_md', 'updated_at'];
    protected const FILLABE = ['slug', 'title', 'body_md'];
    protected const CASTS = ['updated_at' => 'datetime'];

    public function findBySlug(string $slug): ?array
    {
        return $this->query()->where('slug', $slug)->first();
    }
}

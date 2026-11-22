<?php
declare(strict_types=1);

namespace App\Model\Dao\School;

use App\Core\Orm\Query\QueryBuilder;
use App\Model\Dao\Dao;

/** Ecoles dont le domaine e-mail ouvre l'inscription ; une ecole inactive n'est jamais proposee. */
final class School extends Dao
{
    protected const TABLE = 'schools';

    protected const COLUMNS = ['id', 'name', 'short_name', 'email_domain', 'city'];

    protected const CASTS = ['id' => 'int'];

    /** @return list<array<string, mixed>> ecoles actives, par nom (petit referentiel : sans pagination) */
    public function listActive(): array
    {
        return array_values($this->active()->orderBy('name')->orderBy('id')->get());
    }

    public function findActiveById(int $id): ?array
    {
        return $this->active()->where('id', $id)->first();
    }

    /** $domain deja en minuscules, comme `schools.email_domain`. */
    public function activeDomainExists(string $domain): bool
    {
        return $this->active()->where('email_domain', $domain)->exists();
    }

    private function active(): QueryBuilder
    {
        return $this->query()->where('active', true);
    }
}

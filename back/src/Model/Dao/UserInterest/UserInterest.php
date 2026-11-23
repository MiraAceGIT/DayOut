<?php
declare(strict_types=1);

namespace App\Model\Dao\UserInterest;

use App\Model\Dao\Dao;

/** Centres d'interet d'un compte (table `user_interests`, une ligne par categorie). */
final class UserInterest extends Dao
{
    protected const TABLE = 'user_interests';

    /** @param list<int> $categoryIds une ligne par categorie : au plus le nombre de categories actives */
    public function addAll(int $userId, array $categoryIds): void
    {
        foreach (array_values(array_unique($categoryIds)) as $categoryId) {
            $this->table()->insert(['user_id' => $userId, 'category_id' => $categoryId]);
        }
    }
}

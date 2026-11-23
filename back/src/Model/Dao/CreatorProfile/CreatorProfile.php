<?php
declare(strict_types=1);

namespace App\Model\Dao\CreatorProfile;

use App\Model\Dao\Dao;

/** Demande de compte createur (table `creator_profiles`) ; la lecture publique est dans Dao\Creator. */
final class CreatorProfile extends Dao
{
    protected const TABLE = 'creator_profiles';

    protected const PRIMARY_KEY = 'user_id';

    protected const FILLABLE = ['display_name'];

    public function createFor(int $userId, string $displayName): void
    {
        $this->create(['display_name' => $displayName], ['user_id' => $userId]);
    }
}

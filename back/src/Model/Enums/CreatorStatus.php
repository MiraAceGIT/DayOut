<?php
declare(strict_types=1);

namespace App\Model\Enums;

/** Valeurs de `users.creator_status` (ENUM MariaDB) : demande de compte createur. */
enum CreatorStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

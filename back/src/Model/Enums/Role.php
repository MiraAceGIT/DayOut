<?php
declare(strict_types=1);

namespace App\Model\Enums;

/** Valeurs de `users.role` (ENUM MariaDB). */
enum Role: string
{
    case Participant = 'participant';
    case Creator = 'creator';
    case Admin = 'admin';
}

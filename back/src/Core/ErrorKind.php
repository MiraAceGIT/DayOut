<?php
declare(strict_types=1);

namespace App\Core;

enum ErrorKind
{
    case NotFound;
    case Forbidden;
    case Conflict;
    case Invalid;
}

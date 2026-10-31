<?php
declare(strict_types=1);

namespace App\Model\Exceptions;

enum ErrorKind
{
    case NotFound;
    case Forbidden;
    case Conflict;
    case Invalid;
}

<?php
declare(strict_types=1);

namespace App\Model\Exceptions;

use App\Core\BusinessException;
use App\Core\ErrorKind;

final class RegistrationException extends BusinessException
{
    public static function emailTaken(): self
    {
        return new self(ErrorKind::Conflict, 'EMAIL_TAKEN', 'An account already exists with this email', ['field' => 'email']);
    }

    public static function tokenInvalid(): self
    {
        return new self(ErrorKind::Invalid, 'VERIFICATION_TOKEN_INVALID', 'Invalid confirmation link');
    }

    public static function tokenExpired(): self
    {
        return new self(ErrorKind::Invalid, 'VERIFICATION_TOKEN_EXPIRED', 'This confirmation link has expired');
    }

    public static function tokenUsed(): self
    {
        return new self(ErrorKind::Conflict, 'VERIFICATION_TOKEN_USED', 'This confirmation link has already been used');
    }
}

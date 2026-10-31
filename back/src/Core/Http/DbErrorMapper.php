<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\HttpException;
use PDOException;

/**
 * MESSAGE_TEXT des triggers (anglais, sans donnee sensible) renvoye tel quel ; contraintes
 * natives : message generique, car celui de MariaDB cite des noms de tables et de contraintes.
 * L'errno est dans errorInfo[1] : getCode() vaut '45000' pour tous les triggers.
 */
final class DbErrorMapper
{
    private const TRIGGER_ERRORS = [
        50101 => [422, 'EMAIL_DOMAIN_NOT_ALLOWED'],
        50201 => [422, 'INVALID_SCHOOL_RESTRICTION'],
        50202 => [422, 'INVALID_SCHOOL_RESTRICTION'],
        50301 => [403, 'USER_BANNED'],
        50302 => [403, 'RELIABILITY_TOO_LOW'],
        50303 => [409, 'EVENT_NOT_OPEN'],
        50304 => [409, 'EVENT_FULL'],
        50401 => [403, 'ADMIN_REQUIRED'],
        50402 => [403, 'ADMIN_REQUIRED'],
        50403 => [403, 'ADMIN_REQUIRED'],
        50404 => [403, 'ADMIN_REQUIRED'],
        50501 => [422, 'INVALID_PLANNING_ITEM'],
        50502 => [422, 'INVALID_REPORT_TARGET'],
    ];

    private const NATIVE_ERRORS = [
        1062 => [409, 'CONFLICT', 'Resource already exists'],         // cle unique (ex. deja inscrit)
        1451 => [409, 'CONFLICT', 'Resource is still referenced'],    // suppression d'une ligne referencee
        1452 => [422, 'VALIDATION_ERROR', 'Referenced resource does not exist'],
        4025 => [422, 'VALIDATION_ERROR', 'Invalid data'],            // CHECK viole
    ];

    /** Plage reservee aux triggers : un errno inconnu de cette plage reste une regle metier. */
    private const TRIGGER_RANGE = [50000, 50999];

    public static function toHttpException(PDOException $e): ?HttpException
    {
        $errno   = (int) ($e->errorInfo[1] ?? 0);
        $message = (string) ($e->errorInfo[2] ?? '');

        if ($errno >= self::TRIGGER_RANGE[0] && $errno <= self::TRIGGER_RANGE[1] && $message !== '') {
            [$status, $code] = self::TRIGGER_ERRORS[$errno] ?? [409, 'BUSINESS_RULE_VIOLATION'];
            return new HttpException($status, $code, $message, [], $e);
        }
        if (isset(self::NATIVE_ERRORS[$errno])) {
            [$status, $code, $generic] = self::NATIVE_ERRORS[$errno];
            return new HttpException($status, $code, $generic, [], $e);
        }
        return null;
    }
}

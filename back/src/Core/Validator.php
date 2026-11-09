<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use LogicException;

/** min / max dependent du type deja caste : placer la regle de type avant (`int|min:1`, pas `min:1|int`). */
final class Validator
{
    private const DATETIME_FORMATS = ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.vP'];

    /** Regle => argument attendu : null (aucun), 'int' (min:3) ou 'list' (in:a,b). */
    private const RULES = [
        'required' => null, 'nullable' => null, 'filled' => null, 'string' => null, 'int' => null, 'number' => null, 'bool' => null,
        'array'    => null, 'email'    => null, 'url'    => null, 'date' => null, 'datetime' => null, 'slug' => null,
        'min'      => 'int', 'max'     => 'int', 'in'    => 'list',
    ];

    /**
     * @param array<string, string|array<int, string>> $rules
     * @return array<string, mixed>
     */
    public static function check(array $data, array $rules): array
    {
        // Regles verifiees d'abord : une faute de frappe leve une LogicException meme si le champ est absent.
        $parsed = array_map(self::parseRules(...), $rules, array_keys($rules));
        $parsed = array_combine(array_keys($rules), $parsed);

        $clean  = [];
        $errors = [];

        foreach ($parsed as $field => $fieldRules) {
            $names   = array_column($fieldRules, 0);
            $present = array_key_exists($field, $data);
            $value   = $data[$field] ?? null;

            if (in_array('required', $names, true) && self::isBlank($value)) {
                $errors[$field] = 'This field is required';
                continue;
            }
            if (!$present) {
                continue;
            }
            if ($value === null) {
                if (in_array('nullable', $names, true)) {
                    $clean[$field] = null;
                } else {
                    $errors[$field] = 'This field cannot be null';
                }
                continue;
            }

            foreach ($fieldRules as [$name, $arg]) {
                $error = self::apply($name, $arg, $value);
                if ($error !== null) {
                    $errors[$field] = $error;
                    continue 2;
                }
            }
            $clean[$field] = $value;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $clean;
    }

    /**
     * @param string|array<int, string> $rules
     * @return array<int, array{0: string, 1: ?string}>
     */
    private static function parseRules(string|array $rules, string $field): array
    {
        $parsed = [];
        foreach (is_string($rules) ? explode('|', $rules) : $rules as $rule) {
            [$name, $arg] = array_map('trim', array_pad(explode(':', $rule, 2), 2, ''));
            if (!array_key_exists($name, self::RULES)) {
                throw new LogicException("Unknown validation rule '$name' on '$field'");
            }
            $valid = match (self::RULES[$name]) {
                null   => $arg === '',
                'int'  => preg_match('/^-?\d+$/', $arg) === 1,
                'list' => $arg !== '',
            };
            if (!$valid) {
                throw new LogicException("Invalid argument for rule '$name' on '$field': '$arg'");
            }
            $parsed[] = [$name, $arg === '' ? null : $arg];
        }
        return $parsed;
    }

    /** Message d'erreur ou null ; peut caster $value (par reference). */
    private static function apply(string $rule, ?string $arg, mixed &$value): ?string
    {
        return match ($rule) {
            'required', 'nullable' => null,
            'filled'   => self::isBlank($value) ? 'This field cannot be empty' : null,
            'string'   => is_string($value) ? null : 'Must be a string',
            'int'      => self::toInt($value) ? null : 'Must be an integer',
            'number'   => self::toNumber($value) ? null : 'Must be a number',
            'bool'     => self::toBool($value) ? null : 'Must be a boolean',
            'array'    => is_array($value) ? null : 'Must be an array',
            'email'    => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null : 'Must be a valid email address',
            'url'      => is_string($value) && self::isPublicHttpsUrl($value) ? null : 'Must be a public https URL',
            'slug'     => is_string($value) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1
                ? null : 'Must be lowercase letters, digits and single dashes',
            'date'     => self::isDate($value, ['Y-m-d']) ? null : 'Must be a date (YYYY-MM-DD)',
            'datetime' => self::isDate($value, self::DATETIME_FORMATS)
                ? null : 'Must be an ISO 8601 datetime with timezone',
            'min'      => self::size($value) >= (int) $arg ? null : self::sizeMessage($value, 'at least', $arg),
            'max'      => self::size($value) <= (int) $arg ? null : self::sizeMessage($value, 'at most', $arg),
            'in'       => is_scalar($value) && in_array((string) $value, explode(',', (string) $arg), true)
                ? null : 'Must be one of: ' . str_replace(',', ', ', (string) $arg),
            default    => throw new LogicException("Rule '$rule' is declared in RULES but not implemented"),
        };
    }

    /** https, sans identifiants, caracteres RFC 3986 uniquement, hote public (ni localhost ni IP privee). */
    private static function isPublicHttpsUrl(string $value): bool
    {
        if (preg_match('#^https://[A-Za-z0-9\-._~:/?\#\[\]@!$&\'()*+,;=%]+$#', $value) !== 1
            || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($value);
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || isset($parts['user']) || isset($parts['pass'])
            || $host === 'localhost' || str_ends_with($host, '.localhost') || !str_contains($host, '.') && filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        return filter_var($host, FILTER_VALIDATE_IP) === false
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || $value === [];
    }

    /** Accepte 12 et "12" (query string) ; refuse 1.5, "1e3", true. */
    private static function toInt(mixed &$value): bool
    {
        if (is_int($value)) {
            return true;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $int = filter_var($value, FILTER_VALIDATE_INT);
            if ($int !== false) {
                $value = $int;
                return true;
            }
        }
        return false;
    }

    /** Accepte 48.85, 2 et "48.85" (caste en float) ; refuse "1e3", "", true. */
    private static function toNumber(mixed &$value): bool
    {
        if (is_int($value) || is_float($value)) {
            $value = (float) $value;
            return is_finite($value);
        }
        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            $value = (float) $value;
            return true;
        }
        return false;
    }

    private static function toBool(mixed &$value): bool
    {
        $bool = match (true) {
            is_bool($value)                              => $value,
            $value === 1, $value === '1', $value === 'true'  => true,
            $value === 0, $value === '0', $value === 'false' => false,
            default                                      => null,
        };
        if ($bool === null) {
            return false;
        }
        $value = $bool;
        return true;
    }

    /** @param array<int, string> $formats */
    private static function isDate(mixed $value, array $formats): bool
    {
        if (!is_string($value)) {
            return false;
        }
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            // Le reformatage rejette les dates debordantes que PHP corrige en silence (2026-02-31).
            if ($date !== false && $date->format($format) === $value) {
                return true;
            }
        }
        // 'P' reformate "Z" en "+00:00" : on accepte aussi le suffixe Z.
        return str_ends_with($value, 'Z') && self::isDate(substr($value, 0, -1) . '+00:00', $formats);
    }

    private static function size(mixed $value): int|float
    {
        return match (true) {
            is_string($value)                 => mb_strlen($value),
            is_array($value)                  => count($value),
            is_int($value), is_float($value)  => $value,
            default                           => 0,
        };
    }

    private static function sizeMessage(mixed $value, string $bound, ?string $arg): string
    {
        return match (true) {
            is_string($value) => "Must be $bound $arg characters",
            is_array($value)  => "Must contain $bound $arg items",
            default           => "Must be $bound $arg",
        };
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use LogicException;

/**
 * Valide un tableau d'entree contre des regles, leve HttpException 422 sinon.
 *
 *   $data = Validator::check($input, [
 *       'title'    => 'required|string|max:150',
 *       'capacity' => 'nullable|int|min:1',
 *       'currency' => 'in:EUR,USD',
 *   ]);
 *
 * Renvoie uniquement les champs declares (les autres sont ignores), avec
 * `int` et `bool` castes. Champ absent et non `required` : absent du resultat.
 * Une seule erreur par champ : la premiere regle qui echoue.
 *
 * Regles : required, nullable, string, int, bool, array, email, url,
 * date (AAAA-MM-JJ), datetime (ISO 8601 avec fuseau), min:n, max:n, in:a,b.
 * min / max portent sur la longueur d'une chaine, la valeur d'un int ou la taille d'un tableau :
 * placer la regle de type avant (`int|min:1`, pas `min:1|int`).
 */
final class Validator
{
    private const DATETIME_FORMATS = ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.vP'];

    /**
     * @param array<string, string|array<int, string>> $rules
     * @return array<string, mixed>
     */
    public static function check(array $data, array $rules): array
    {
        $clean  = [];
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $present    = array_key_exists($field, $data);
            $value      = $data[$field] ?? null;

            if (in_array('required', $fieldRules, true) && self::isBlank($value)) {
                $errors[$field] = 'This field is required';
                continue;
            }
            if (!$present) {
                continue;
            }
            if ($value === null) {
                if (in_array('nullable', $fieldRules, true)) {
                    $clean[$field] = null;
                } else {
                    $errors[$field] = 'This field cannot be null';
                }
                continue;
            }

            foreach ($fieldRules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = self::apply($name, $arg, $value);
                if ($error !== null) {
                    $errors[$field] = $error;
                    continue 2;
                }
            }
            $clean[$field] = $value;
        }

        if ($errors !== []) {
            throw HttpException::validation($errors);
        }
        return $clean;
    }

    /** Renvoie le message d'erreur, ou null si la regle passe ($value peut etre caste). */
    private static function apply(string $rule, ?string $arg, mixed &$value): ?string
    {
        return match ($rule) {
            'required', 'nullable' => null,
            'string'   => is_string($value) ? null : 'Must be a string',
            'int'      => self::toInt($value) ? null : 'Must be an integer',
            'bool'     => self::toBool($value) ? null : 'Must be a boolean',
            'array'    => is_array($value) ? null : 'Must be an array',
            'email'    => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null : 'Must be a valid email address',
            'url'      => is_string($value) && preg_match('#^https?://#i', $value) === 1
                && filter_var($value, FILTER_VALIDATE_URL) !== false
                ? null : 'Must be a valid http(s) URL',
            'date'     => self::isDate($value, ['Y-m-d']) ? null : 'Must be a date (YYYY-MM-DD)',
            'datetime' => self::isDate($value, self::DATETIME_FORMATS)
                ? null : 'Must be an ISO 8601 datetime with timezone',
            'min'      => self::size($value) >= self::number($rule, $arg) ? null : self::sizeMessage($value, 'at least', $arg),
            'max'      => self::size($value) <= self::number($rule, $arg) ? null : self::sizeMessage($value, 'at most', $arg),
            'in'       => is_scalar($value) && in_array((string) $value, explode(',', (string) $arg), true)
                ? null : 'Must be one of: ' . str_replace(',', ', ', (string) $arg),
            default    => throw new LogicException("Unknown validation rule '$rule'"),
        };
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

    /** Accepte true/false, 1/0, "true"/"false", "1"/"0". */
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

    private static function number(string $rule, ?string $arg): int
    {
        if ($arg === null || preg_match('/^-?\d+$/', $arg) !== 1) {
            throw new LogicException("Rule '$rule' expects an integer argument");
        }
        return (int) $arg;
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Query;

use App\Core\ValidationException;
use App\Model\Dao\PageRequest;
use DateTimeImmutable;

trait ParsesQueryString
{
    private const MAX_LIST_ITEMS = 50;

    /**
     * Regles sort_by / sort_dir / limit / offset, communes aux listes paginees.
     *
     * @param array<string, string> $sortMap SORT_MAP du DAO : ses cles sont les tris acceptes
     * @return array<string, string>
     */
    private static function pageRules(array $sortMap): array
    {
        return [
            'sort_by'  => 'in:' . implode(',', array_keys($sortMap)),
            'sort_dir' => 'in:asc,desc',
            'limit'    => 'int|min:1|max:' . PageRequest::MAX_LIMIT,
            'offset'   => 'int|min:0|max:' . PageRequest::MAX_OFFSET,
        ];
    }

    /**
     * @param array<string, mixed>  $clean   query string deja validee avec pageRules()
     * @param array<string, string> $sortMap tri par defaut = sa premiere cle
     */
    private static function page(array $clean, array $sortMap): PageRequest
    {
        return new PageRequest(
            sortBy:  $clean['sort_by'] ?? array_key_first($sortMap),
            sortDir: $clean['sort_dir'] ?? 'asc',
            limit:   $clean['limit'] ?? PageRequest::DEFAULT_LIMIT,
            offset:  $clean['offset'] ?? 0,
        );
    }

    /** @return list<int>|null */
    private static function parseIds(string $field, mixed $value): ?array
    {
        $parts = self::csv($field, $value);
        if ($parts === null) {
            return null;
        }
        $ids = [];
        foreach ($parts as $part) {
            $id = preg_match('/^[1-9][0-9]*$/D', $part) === 1 ? filter_var($part, FILTER_VALIDATE_INT) : false;
            if ($id === false) {
                throw ValidationException::field($field, 'Must be a comma-separated list of positive integers');
            }
            $ids[] = $id;
        }
        return array_values(array_unique($ids));
    }

    /** @return list<string>|null */
    private static function parseSlugs(string $field, mixed $value): ?array
    {
        $parts = self::csv($field, $value);
        if ($parts === null) {
            return null;
        }
        foreach ($parts as $part) {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $part) !== 1 || strlen($part) > 60) {
                throw ValidationException::field($field, 'Must be a comma-separated list of slugs');
            }
        }
        return array_values(array_unique($parts));
    }

    /** @return list<string>|null */
    private static function csv(string $field, mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value) || array_filter($value, static fn (mixed $v): bool => !is_string($v)) !== []) {
            throw ValidationException::field($field, 'Must be a comma-separated list');
        }
        $parts = array_values(array_filter(array_map('trim', $value), static fn (string $v): bool => $v !== ''));
        if (count($parts) > self::MAX_LIST_ITEMS) {
            throw ValidationException::field($field, 'Must contain at most ' . self::MAX_LIST_ITEMS . ' items');
        }
        return $parts === [] ? null : $parts;
    }

    private static function date(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable($value);
    }

    private static function text(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);
        return $value === '' ? null : $value;
    }
}

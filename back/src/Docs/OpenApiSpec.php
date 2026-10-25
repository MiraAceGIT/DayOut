<?php
declare(strict_types=1);

namespace App\Docs;

use App\Core\Yaml;
use LogicException;

final class OpenApiSpec
{
    private const BASE = 'openapi.yaml';

    private const PART_KEYS = ['tags', 'paths', 'components'];

    /** @return array<string, mixed> */
    public static function load(string $dir): array
    {
        $spec = Yaml::parseFile("$dir/" . self::BASE);
        $spec += ['tags' => [], 'paths' => [], 'components' => []];

        $parts = glob("$dir/*.yaml") ?: [];
        sort($parts);
        foreach ($parts as $file) {
            if (basename($file) !== self::BASE) {
                $spec = self::merge($spec, Yaml::parseFile($file) ?? [], basename($file));
            }
        }
        return $spec;
    }

    private static function merge(array $spec, array $part, string $file): array
    {
        if (($extra = array_diff(array_keys($part), self::PART_KEYS)) !== []) {
            throw new LogicException("$file: only " . implode(', ', self::PART_KEYS) . ' allowed, got ' . implode(', ', $extra));
        }

        $spec['tags'] = [...$spec['tags'], ...($part['tags'] ?? [])];
        $spec['paths'] = self::union($spec['paths'], $part['paths'] ?? [], "$file: path");
        foreach ($part['components'] ?? [] as $section => $items) {
            $spec['components'][$section] = self::union($spec['components'][$section] ?? [], $items, "$file: components.$section");
        }
        return $spec;
    }

    private static function union(array $into, array $items, string $context): array
    {
        foreach ($items as $name => $value) {
            if (array_key_exists($name, $into)) {
                throw new LogicException("$context '$name' already defined");
            }
            $into[$name] = $value;
        }
        return $into;
    }
}

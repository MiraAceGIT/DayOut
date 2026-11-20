<?php
declare(strict_types=1);

namespace App\Core;

use LogicException;

/** Chemin de route (`/events/{id:\d+}`) -> regex a groupes nommes, sans ancres, echappee pour le delimiteur `#`. */
final class RouteCompiler
{
    /**
     * {name} -> [^/]+ ; {name:regex} -> regex fournie, accolades equilibrees comprises (`{code:[a-z]{2}}`).
     * Lu caractere par caractere : une regex ne peut pas trouver la fin d'un parametre qui contient lui-meme `}`.
     */
    public static function compile(string $path): string
    {
        $regex = '';
        $names = [];
        $length = strlen($path);
        for ($i = 0; $i < $length; $i++) {
            if ($path[$i] !== '{') {
                $regex .= preg_quote($path[$i], '#');
                continue;
            }
            $depth = 1;
            $end = $i + 1;
            for (; $end < $length && $depth > 0; $end++) {
                $depth += match ($path[$end]) { '{' => 1, '}' => -1, default => 0 };
            }
            if ($depth !== 0) {
                throw new LogicException("Unclosed route parameter in $path");
            }
            $inner = substr($path, $i + 1, $end - $i - 2);
            [$name, $pattern] = array_pad(explode(':', $inner, 2), 2, null);
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $name) !== 1 || $pattern === '') {
                throw new LogicException("Invalid route parameter '{{$inner}}' in $path");
            }
            if (in_array($name, $names, true)) {
                throw new LogicException("Duplicate route parameter '$name' in $path");
            }
            $names[] = $name;
            $regex .= '(?P<' . $name . '>' . str_replace('#', '\#', $pattern ?? '[^/]+') . ')';
            $i = $end - 1;
        }
        return $regex;
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use stdClass;

/**
 * Lecteur YAML minimal (ni Composer ni extension yaml) pour les fichiers ecrits a la main du projet (docs/openapi).
 * Sous-ensemble : mappings et listes en blocs, collections en flux [a, b] / {k: v}, scalaires simples ou entre
 * guillemets, blocs | et > (avec - ou +), commentaires #. Tout le reste est refuse avec le fichier et la ligne,
 * pour qu'un fichier lu ici reste du YAML standard : ancres & / alias *, tags !, cles complexes ?, tabulations,
 * scalaires simples sur plusieurs lignes, multi-documents.
 * `{}` donne un stdClass (sinon json_encode ecrirait [] au lieu de {}).
 */
final class Yaml
{
    /** @var list<string> */
    private array $raw;

    private int $pos = 0;

    /** Contenu d'un "- cle: valeur", relu comme une ligne a part entiere a sa vraie colonne. */
    private ?array $pending = null;

    private function __construct(string $yaml, private readonly string $source)
    {
        $this->raw = preg_split('/\R/', $yaml) ?: [];
    }

    public static function parseFile(string $path): mixed
    {
        $yaml = @file_get_contents($path);
        if ($yaml === false) {
            throw new RuntimeException("YAML file not readable: $path");
        }
        return self::parse($yaml, $path);
    }

    public static function parse(string $yaml, string $source = 'yaml'): mixed
    {
        $parser = new self($yaml, $source);
        if (($parser->peek()['text'] ?? null) === '---') {
            $parser->advance();
        }
        $first = $parser->peek();
        if ($first === null) {
            return null;
        }
        $value = $parser->parseNode($first['indent']);
        if (($extra = $parser->peek()) !== null) {
            throw $parser->error($extra['line'], 'unexpected content (indentation?)');
        }
        return $value;
    }

    /** Prochaine ligne utile (ni vide ni commentaire), sans la consommer. */
    private function peek(): ?array
    {
        if ($this->pending !== null) {
            return $this->pending;
        }
        for (; $this->pos < count($this->raw); $this->pos++) {
            $line = rtrim($this->raw[$this->pos]);
            $text = ltrim($line, ' ');
            if ($text === '' || $text[0] === '#') {
                continue;
            }
            if ($text[0] === "\t") {
                throw $this->error($this->pos + 1, 'tabs are not allowed for indentation');
            }
            return ['indent' => strlen($line) - strlen($text), 'text' => $text, 'line' => $this->pos + 1];
        }
        return null;
    }

    private function advance(): void
    {
        if ($this->pending !== null) {
            $this->pending = null;
            return;
        }
        $this->pos++;
    }

    private function parseNode(int $indent): array
    {
        return self::isSequenceItem($this->peek()['text']) ? $this->parseSequence($indent) : $this->parseMapping($indent);
    }

    private function parseMapping(int $indent): array
    {
        $map = [];
        while (($line = $this->peek()) !== null && $line['indent'] === $indent && !self::isSequenceItem($line['text'])) {
            [$key, $rest] = $this->splitKey($line['text']) ?? throw $this->error($line['line'], 'expected "key: value"');
            if (array_key_exists($key, $map)) {
                throw $this->error($line['line'], "duplicate key '$key'");
            }
            $this->advance();
            $map[$key] = $this->parseValue($rest, $indent, $line['line']);
        }
        if ($line !== null && $line['indent'] > $indent) {
            throw $this->error($line['line'], 'unexpected indentation');
        }
        return $map;
    }

    private function parseSequence(int $indent): array
    {
        $list = [];
        while (($line = $this->peek()) !== null && $line['indent'] === $indent && self::isSequenceItem($line['text'])) {
            $after = substr($line['text'], 1);
            $content = ltrim($after, ' ');
            $this->advance();

            if ($content === '' || $content[0] === '#') {
                $next = $this->peek();
                $list[] = $next !== null && $next['indent'] > $indent ? $this->parseNode($next['indent']) : null;
            } elseif ($this->splitKey($content) !== null) {
                $column = $indent + 1 + strlen($after) - strlen($content);
                $this->pending = ['indent' => $column, 'text' => $content, 'line' => $line['line']];
                $list[] = $this->parseMapping($column);
            } else {
                $list[] = $this->parseValue($content, $indent, $line['line']);
            }
        }
        return $list;
    }

    /** Ce qui suit "cle:" : bloc imbrique (plus indente, ou liste a la meme colonne), bloc | >, ou scalaire. */
    private function parseValue(string $rest, int $parentIndent, int $lineNo): mixed
    {
        $rest = trim($rest);
        if ($rest === '' || $rest[0] === '#') {
            $next = $this->peek();
            $nested = $next !== null
                && ($next['indent'] > $parentIndent || ($next['indent'] === $parentIndent && self::isSequenceItem($next['text'])));
            return $nested ? $this->parseNode($next['indent']) : null;
        }
        if ($rest[0] === '|' || $rest[0] === '>') {
            return $this->blockScalar($rest, $parentIndent, $lineNo);
        }
        return $this->scalar($rest, $lineNo);
    }

    /** @return array{0: string, 1: string}|null [cle, reste de la ligne] */
    private function splitKey(string $text): ?array
    {
        if ($text[0] === '"' || $text[0] === "'") {
            $end = self::quotedEnd($text, 0);
            if ($end === null || preg_match('/^\s*:(?:\s|$)/', substr($text, $end + 1), $m) !== 1) {
                return null;
            }
            return [(string) $this->unquote(substr($text, 0, $end + 1), 0), substr($text, $end + 1 + strlen($m[0]))];
        }
        if ($text[0] === '[' || $text[0] === '{' || $text[0] === '?') {
            return null;
        }
        if (preg_match('/^(.*?):(?:\s|$)/', $text, $m) !== 1 || trim($m[1]) === '') {
            return null;
        }
        return [trim($m[1]), substr($text, strlen($m[0]))];
    }

    private function scalar(string $rest, int $lineNo): mixed
    {
        if ($rest[0] === '"' || $rest[0] === "'") {
            $end = self::quotedEnd($rest, 0) ?? throw $this->error($lineNo, 'unterminated quoted string');
            $this->assertOnlyComment(substr($rest, $end + 1), $lineNo);
            return $this->unquote(substr($rest, 0, $end + 1), $lineNo);
        }
        if ($rest[0] === '[' || $rest[0] === '{') {
            $i = 0;
            $value = $this->flow($rest, $i, $lineNo);
            $this->assertOnlyComment(substr($rest, $i), $lineNo);
            return $value;
        }
        if (str_contains('&*!%@`', $rest[0])) {
            throw $this->error($lineNo, "unsupported or reserved indicator '{$rest[0]}' (quote the value)");
        }
        return self::plain(trim((string) preg_replace('/\s+#.*$/', '', $rest)));
    }

    private function assertOnlyComment(string $tail, int $lineNo): void
    {
        $tail = trim($tail);
        if ($tail !== '' && $tail[0] !== '#') {
            throw $this->error($lineNo, "unexpected '$tail' after value");
        }
    }

    /** Collection en flux : [a, b], {k: v}, imbricables ; sur une seule ligne. */
    private function flow(string $s, int &$i, int $lineNo): mixed
    {
        self::skipSpaces($s, $i);
        $open = $s[$i] ?? '';
        if ($open !== '[' && $open !== '{') {
            return $this->flowScalar($s, $i, $lineNo, false);
        }
        $close = $open === '[' ? ']' : '}';
        $items = [];
        $i++;
        self::skipSpaces($s, $i);
        if (($s[$i] ?? '') === $close) {
            $i++;
            return $open === '[' ? [] : new stdClass();
        }
        while (true) {
            if ($open === '[') {
                $items[] = $this->flow($s, $i, $lineNo);
            } else {
                $key = (string) $this->flowScalar($s, $i, $lineNo, true);
                self::skipSpaces($s, $i);
                if (($s[$i++] ?? '') !== ':') {
                    throw $this->error($lineNo, "expected ':' after '$key' in flow mapping");
                }
                $items[$key] = $this->flow($s, $i, $lineNo);
            }
            self::skipSpaces($s, $i);
            $c = $s[$i++] ?? '';
            if ($c === $close) {
                return $items;
            }
            if ($c !== ',') {
                throw $this->error($lineNo, "expected ',' or '$close' in flow collection");
            }
        }
    }

    private function flowScalar(string $s, int &$i, int $lineNo, bool $isKey): mixed
    {
        self::skipSpaces($s, $i);
        $c = $s[$i] ?? '';
        if ($c === '"' || $c === "'") {
            $end = self::quotedEnd($s, $i) ?? throw $this->error($lineNo, 'unterminated quoted string');
            $value = $this->unquote(substr($s, $i, $end - $i + 1), $lineNo);
            $i = $end + 1;
            return $value;
        }
        preg_match($isKey ? '/\G[^,\[\]{}:]*/' : '/\G[^,\[\]{}]*/', $s, $m, 0, $i);
        $i += strlen($m[0]);
        $text = trim($m[0]);
        if ($text === '') {
            throw $this->error($lineNo, 'empty entry in flow collection');
        }
        return $isKey ? $text : self::plain($text);
    }

    /** Bloc | (litteral) ou > (replie) ; chomping : rien = un \n final, - = aucun, + = tous. */
    private function blockScalar(string $header, int $parentIndent, int $lineNo): string
    {
        if (preg_match('/^([|>])([-+]?)\s*(?:#.*)?$/', $header, $m) !== 1) {
            throw $this->error($lineNo, "unsupported block scalar header '$header'");
        }
        $lines = [];
        $blockIndent = null;
        for (; $this->pos < count($this->raw); $this->pos++) {
            $line = $this->raw[$this->pos];
            if (trim($line) === '') {
                $lines[] = '';
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line, ' '));
            if ($indent <= $parentIndent) {
                break;
            }
            $blockIndent ??= $indent;
            if ($indent < $blockIndent) {
                throw $this->error($this->pos + 1, 'block scalar line less indented than the first one');
            }
            $lines[] = rtrim(substr($line, $blockIndent));
        }

        $trailing = 0;
        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
            $trailing++;
        }
        if ($lines === []) {
            return '';
        }
        $text = $m[1] === '|' ? implode("\n", $lines) : self::fold($lines);
        return match ($m[2]) {
            '-'     => $text,
            '+'     => $text . str_repeat("\n", $trailing + 1),
            default => $text . "\n",
        };
    }

    /** @param list<string> $lines lignes non vides jointes par un espace, ligne vide = saut de ligne */
    private static function fold(array $lines): string
    {
        $text = $lines[0];
        for ($k = 1, $n = count($lines); $k < $n; $k++) {
            $text .= match (true) {
                $lines[$k] === ''     => "\n",
                $lines[$k - 1] === '' => $lines[$k],
                default               => ' ' . $lines[$k],
            };
        }
        return $text;
    }

    /** Schema "core" de YAML 1.2 : null, booleens, entiers, flottants ; le reste est une chaine. */
    private static function plain(string $s): mixed
    {
        $lower = strtolower($s);
        return match (true) {
            $s === '' || $s === '~' || $lower === 'null' => null,
            $lower === 'true'                             => true,
            $lower === 'false'                            => false,
            preg_match('/^[-+]?(0|[1-9][0-9]*)$/', $s) === 1                              => (int) $s,
            preg_match('/^[-+]?(\.[0-9]+|[0-9]+(\.[0-9]*)?)([eE][-+]?[0-9]+)?$/', $s) === 1 => (float) $s,
            default                                                                        => $s,
        };
    }

    /** Index du guillemet fermant de la chaine ouverte en $start ; null si non fermee. */
    private static function quotedEnd(string $s, int $start): ?int
    {
        $quote = $s[$start];
        for ($i = $start + 1, $n = strlen($s); $i < $n; $i++) {
            if ($quote === '"' && $s[$i] === '\\') {
                $i++;
            } elseif ($s[$i] === $quote) {
                if ($quote === "'" && ($s[$i + 1] ?? '') === "'") {
                    $i++;
                    continue;
                }
                return $i;
            }
        }
        return null;
    }

    /** '...' : '' -> ' ; "..." : echappements JSON (\n, \", \\, \uXXXX), sous-ensemble de ceux de YAML. */
    private function unquote(string $token, int $lineNo): string
    {
        if ($token[0] === "'") {
            return str_replace("''", "'", substr($token, 1, -1));
        }
        $value = json_decode($token);
        if (!is_string($value)) {
            throw $this->error($lineNo, "invalid escape in $token");
        }
        return $value;
    }

    private static function isSequenceItem(string $text): bool
    {
        return $text === '-' || str_starts_with($text, '- ');
    }

    private static function skipSpaces(string $s, int &$i): void
    {
        while (($s[$i] ?? '') === ' ') {
            $i++;
        }
    }

    private function error(int $line, string $message): RuntimeException
    {
        return new RuntimeException("{$this->source}:$line: $message");
    }
}

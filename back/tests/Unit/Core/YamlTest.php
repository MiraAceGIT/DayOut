<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Yaml;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class YamlTest extends TestCase
{
    public function testBlockMappingsAndSequences(): void
    {
        $yaml = <<<'YAML'
            # commentaire
            ---
            name: DayOut   # commentaire de fin de ligne
            nested:
              deeper:
                key: value
            list:
              - a
              - b
            same_column:
            - x
            - y
            objects:
              - name: limit
                in: query
              -
                name: offset
            YAML;

        $this->assertSame([
            'name'        => 'DayOut',
            'nested'      => ['deeper' => ['key' => 'value']],
            'list'        => ['a', 'b'],
            'same_column' => ['x', 'y'],
            'objects'     => [['name' => 'limit', 'in' => 'query'], ['name' => 'offset']],
        ], Yaml::parse($yaml));
    }

    public function testScalarTypesFollowTheCoreSchema(): void
    {
        $yaml = <<<'YAML'
            int: 42
            negative: -3
            float: 48.8689
            exp: 1e3
            version: 1.0.0
            quoted_number: "75002"
            yes: true
            no: False
            nothing: null
            tilde: ~
            empty:
            plain: Etat de l'API (`db` up)
            YAML;

        $this->assertSame([
            'int' => 42, 'negative' => -3, 'float' => 48.8689, 'exp' => 1000.0, 'version' => '1.0.0',
            'quoted_number' => '75002', 'yes' => true, 'no' => false, 'nothing' => null, 'tilde' => null,
            'empty' => null, 'plain' => "Etat de l'API (`db` up)",
        ], Yaml::parse($yaml));
    }

    public function testQuotedStringsAndKeys(): void
    {
        $yaml = <<<'YAML'
            "200": ok
            '/api/events/{id}': route
            double: "a: b # pas un commentaire \"x\" \u00e9\n"
            single: 'it''s # aussi'
            ref: "#/components/schemas/Event"
            YAML;

        $this->assertSame([
            200 => 'ok', '/api/events/{id}' => 'route', 'double' => "a: b # pas un commentaire \"x\" é\n",
            'single' => "it's # aussi", 'ref' => '#/components/schemas/Event',
        ], Yaml::parse($yaml));
    }

    public function testFlowCollections(): void
    {
        $value = Yaml::parse('a: { type: string, enum: [up, down], nested: { "$ref": "#/x" } }' . "\nb: []\nc: {}");

        $this->assertSame(['type' => 'string', 'enum' => ['up', 'down'], 'nested' => ['$ref' => '#/x']], $value['a']);
        $this->assertSame([], $value['b']);
        $this->assertTrue($value['c'] instanceof stdClass, '{} stays an object in JSON');
        $this->assertSame('{}', json_encode($value['c']));
    }

    public function testBlockScalars(): void
    {
        $yaml = <<<'YAML'
            literal: |
              ligne 1
                indentee

              ligne 3
            folded: >-
              une phrase
              sur deux lignes

              puis un paragraphe
            kept: |+
              fin

            after: ok
            YAML;

        $this->assertSame([
            'literal' => "ligne 1\n  indentee\n\nligne 3\n",
            'folded'  => "une phrase sur deux lignes\npuis un paragraphe",
            'kept'    => "fin\n\n",
            'after'   => 'ok',
        ], Yaml::parse($yaml));
    }

    public function testUnsupportedOrInvalidYamlFailsWithFileAndLine(): void
    {
        $cases = [
            "a: 1\na: 2"                   => 'src.yaml:2: duplicate key',
            "a:\n  b: 1\n    c: 2"         => 'src.yaml:3: unexpected indentation',
            "a: &anchor 1"                 => "src.yaml:1: unsupported or reserved indicator '&'",
            "a: `code`"                    => "src.yaml:1: unsupported or reserved indicator '`'",
            "a:\n\tb: 1"                   => 'src.yaml:2: tabs',
            "a: \"open"                    => 'src.yaml:1: unterminated',
            "a: [1, 2"                     => "src.yaml:1: expected ',' or ']'",
            "a: 'x' y"                     => "src.yaml:1: unexpected 'y'",
            "a: b\n  continuation"         => 'src.yaml:2: unexpected indentation',
        ];
        foreach ($cases as $yaml => $expected) {
            $e = $this->assertThrows(RuntimeException::class, fn () => Yaml::parse($yaml, 'src.yaml'), $yaml);
            $this->assertStringContains($expected, $e->getMessage());
        }
    }

    public function testEmptyDocumentAndMissingFile(): void
    {
        $this->assertSame(null, Yaml::parse("# rien\n\n"));
        $this->assertThrows(RuntimeException::class, fn () => Yaml::parseFile('/nope.yaml'));
    }
}

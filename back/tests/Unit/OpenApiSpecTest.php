<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Docs\OpenApiSpec;
use LogicException;
use Tests\TestCase;

final class OpenApiSpecTest extends TestCase
{
    /** @param array<string, string> $files nom => contenu YAML ; dans le dossier jetable du runner */
    private function dir(array $files): string
    {
        $dir = getenv('TEST_TMP_DIR') . '/openapi-' . bin2hex(random_bytes(4));
        mkdir($dir);
        foreach ($files as $name => $yaml) {
            file_put_contents("$dir/$name", $yaml);
        }
        return $dir;
    }

    private const BASE = "openapi: \"3.0.3\"\ninfo: { title: T, version: \"1\" }\ncomponents:\n  schemas:\n    Error: { type: object }\n";

    public function testPartsAreMergedIntoTheBaseInFileOrder(): void
    {
        $spec = OpenApiSpec::load($this->dir([
            'openapi.yaml' => self::BASE,
            'b.yaml' => "tags: [{ name: b }]\npaths:\n  /b: { get: { summary: B } }\ncomponents:\n  schemas:\n    B: { type: object }\n",
            'a.yaml' => "tags: [{ name: a }]\npaths:\n  /a: { get: { summary: A } }\ncomponents:\n  responses:\n    R: { description: r }\n",
        ]));

        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame([['name' => 'a'], ['name' => 'b']], $spec['tags']);
        $this->assertSame(['/a', '/b'], array_keys($spec['paths']));
        $this->assertSame(['Error', 'B'], array_keys($spec['components']['schemas']));
        $this->assertSame(['R'], array_keys($spec['components']['responses']));
    }

    public function testDuplicatesAndForeignKeysAreRejected(): void
    {
        $cases = [
            "b.yaml: path '/x' already defined"               => ['a.yaml' => "paths:\n  /x: {}\n", 'b.yaml' => "paths:\n  /x: {}\n"],
            "a.yaml: components.schemas 'Error' already defined" => ['a.yaml' => "components:\n  schemas:\n    Error: {}\n"],
            'a.yaml: only tags, paths, components allowed, got info' => ['a.yaml' => "info: { title: X }\n"],
        ];
        foreach ($cases as $expected => $files) {
            $dir = $this->dir(['openapi.yaml' => self::BASE] + $files);
            $e = $this->assertThrows(LogicException::class, fn () => OpenApiSpec::load($dir), $expected);
            $this->assertStringContains($expected, $e->getMessage());
        }
    }
}

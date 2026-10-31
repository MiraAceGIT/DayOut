<?php
declare(strict_types=1);

namespace Tests\Unit\Docs;

use App\Core\Router;
use App\Docs\OpenApiSpec;
use App\Model\Dto\CategoryDto;
use App\Model\Mappers\EventMapper;
use ReflectionProperty;
use Tests\Support\AppRouter;
use Tests\TestCase;
use Tests\Unit\Model\Mappers\EventMapperTest;

/** La spec OpenAPI doit suivre le code : routes declarees et champs des DTO. */
final class OpenApiContractTest extends TestCase
{
    private const DOC_ROUTES = ['/docs', '/openapi.json'];

    private const SPEC_DIR = APP_ROOT . '/docs/openapi';

    private function spec(): array
    {
        return OpenApiSpec::load(self::SPEC_DIR);
    }

    public function testEachRoutesFileHasItsOwnSpecFile(): void
    {
        foreach (glob(APP_ROOT . '/routes/*.php') as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, ['api', 'docs'], true)) {
                $this->assertTrue(is_file(self::SPEC_DIR . "/$name.yaml"), "routes/$name.php has no docs/openapi/$name.yaml");
            }
        }
    }

    /** @return list<array{method: string, regex: string}> */
    private function routes(): array
    {
        return (new ReflectionProperty(Router::class, 'routes'))->getValue(AppRouter::load());
    }

    /** "/events/{id}" -> "/events/1" : chemin concret a tester contre les regex du Router. */
    private static function sample(string $path): string
    {
        return (string) preg_replace('#\{[^}]+\}#', '1', $path);
    }

    public function testEveryRouteIsDocumentedAndEveryDocumentedRouteExists(): void
    {
        $operations = [];
        foreach ($this->spec()['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $operations[] = [strtoupper($method), self::sample($path), "$method $path"];
            }
        }

        foreach ($this->routes() as $route) {
            $documented = array_filter(
                $operations,
                static fn (array $op): bool => $op[0] === $route['method'] && preg_match($route['regex'], $op[1]) === 1
            );
            $isDocRoute = array_filter(self::DOC_ROUTES, static fn (string $p): bool => preg_match($route['regex'], $p) === 1);
            $this->assertTrue($documented !== [] || $isDocRoute !== [], "{$route['method']} {$route['regex']} missing from openapi.json");
        }

        foreach ($operations as [$method, $sample, $label]) {
            $exists = array_filter(
                $this->routes(),
                static fn (array $r): bool => $r['method'] === $method && preg_match($r['regex'], $sample) === 1
            );
            $this->assertTrue($exists !== [], "$label documented but not routed");
        }
    }

    public function testEventSchemaMatchesEventDto(): void
    {
        $schema = $this->spec()['components']['schemas']['Event'];
        $dtoKeys = array_keys(EventMapper::toDto(EventMapperTest::eventRow())->jsonSerialize());

        $this->assertSame($dtoKeys, array_keys($schema['properties']), 'properties, in DTO order');
        $this->assertSame($dtoKeys, $schema['required'], 'every field is always present (null allowed)');
    }

    public function testCategorySchemaMatchesCategoryDto(): void
    {
        $schema = $this->spec()['components']['schemas']['Category'];

        $this->assertSame(array_keys((new CategoryDto('s', 'l'))->jsonSerialize()), array_keys($schema['properties']));
    }

    public function testBearerTokenIsOfferedButOptional(): void
    {
        $spec = $this->spec();

        $this->assertSame(['type' => 'http', 'scheme' => 'bearer'], array_intersect_key(
            $spec['components']['securitySchemes']['bearerAuth'],
            ['type' => 0, 'scheme' => 0]
        ));
        $this->assertSame('{}', json_encode($spec['security'][0]), 'public routes stay callable without token');
        $this->assertSame(['bearerAuth' => []], $spec['security'][1]);
    }
}

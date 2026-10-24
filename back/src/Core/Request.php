<?php
declare(strict_types=1);

namespace App\Core;

use JsonException;
use LogicException;

/**
 * Requete HTTP immuable. fromGlobals() est le seul endroit qui lit $_SERVER,
 * $_GET et php://input ; les middlewares enrichissent la requete via withAttribute()
 * (ex. l'utilisateur connecte) et le routeur y ajoute les parametres de route.
 */
final class Request
{
    private ?array $json = null;

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers noms en minuscules
     * @param array<string, string> $params  parametres de route ({id})
     * @param array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $headers = [],
        private readonly string $rawBody = '',
        private readonly array $params = [],
        private readonly array $attributes = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $key => $name) {
            if (isset($_SERVER[$key])) {
                $headers[$name] = (string) $_SERVER[$key];
            }
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            self::normalizePath(
                (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'),
                self::basePath((string) ($_SERVER['SCRIPT_NAME'] ?? ''))
            ),
            $_GET,
            $headers,
            (string) file_get_contents('php://input'),
        );
    }

    /** Body vide -> [] ; JSON invalide ou scalaire -> HttpException 400. */
    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }
        if (trim($this->rawBody) === '') {
            return $this->json = [];
        }
        try {
            $data = json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw HttpException::badRequest('Invalid JSON body', [], $e);
        }
        if (!is_array($data)) {
            throw HttpException::badRequest('JSON body must be an object or array');
        }
        return $this->json = $data;
    }

    /** @return array<string, mixed> toute la query string, a valider avec Validator */
    public function query(): array
    {
        return $this->query;
    }

    /** Hors [min, max] ou non entier -> $default. */
    public function queryInt(string $key, int $default, int $min, int $max): int
    {
        $value = filter_var($this->query[$key] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);
        return $value === false ? $default : $value;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Parametre de route : toujours present si la route le declare. */
    public function param(string $name): string
    {
        return $this->params[$name] ?? throw new LogicException("Unknown route parameter '$name'");
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /** @param array<string, string> $params */
    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $this->headers, $this->rawBody, $params, $this->attributes);
    }

    public function withAttribute(string $name, mixed $value): self
    {
        return new self($this->method, $this->path, $this->query, $this->headers, $this->rawBody, $this->params,
            [$name => $value] + $this->attributes);
    }

    /**
     * Prefixe d'installation retire du chemin : sous Apache dans un sous-dossier
     * (http://localhost/dayout/back/public/api/health), SCRIPT_NAME vaut
     * /dayout/back/public/index.php. Sous `php -S`, il vaut /index.php : prefixe vide.
     */
    private static function basePath(string $scriptName): string
    {
        $dir = str_replace('\\', '/', dirname($scriptName));
        return rtrim($dir, '/.');
    }

    private static function normalizePath(string $path, string $basePath): string
    {
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath));
        }
        return '/' . trim($path, '/');
    }
}

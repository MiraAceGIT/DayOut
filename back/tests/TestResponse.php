<?php
declare(strict_types=1);

namespace Tests;

final class TestResponse
{
    /** @param array<string, string> $headers noms en minuscules */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Body decode ; AssertionFailed si ce n'est pas du JSON. */
    public function json(): array
    {
        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            throw new AssertionFailed("Response body is not JSON (HTTP {$this->status}): " . substr($this->body, 0, 300));
        }
        return $data;
    }
}

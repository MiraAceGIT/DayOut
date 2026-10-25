<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Log\Log;
use JsonException;

final class Response
{
    /** @param array<string, string> $headers */
    private function __construct(
        public readonly int $status,
        private readonly ?array $payload,
        private readonly array $headers = [],
        private readonly ?string $body = null,
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, ['data' => $data]);
    }

    /** Body envoye tel quel, hors enveloppe `data` (page HTML, document OpenAPI). */
    public static function raw(string $body, string $contentType, int $status = 200): self
    {
        return new self($status, null, ['Content-Type' => $contentType], $body);
    }

    public static function error(string $code, string $message, int $status, array $details = []): self
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }
        return new self($status, ['error' => $error]);
    }

    public static function noContent(): self
    {
        return new self(204, null);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, $this->payload, [$name => $value] + $this->headers, $this->body);
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    public function payload(): ?array
    {
        return $this->payload;
    }

    public function send(): void
    {
        $body = null;
        $status = $this->status;
        if ($this->payload !== null) {
            try {
                $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                // Sinon le client recevrait un body vide avec le statut d'origine (ex. 200).
                $status = 500;
                $body = '{"error":{"code":"ENCODING_ERROR","message":"Response could not be encoded"}}';
                Log::error('Response encoding failed', ['exception' => $e]);
            }
        }

        http_response_code($status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        if ($body !== null) {
            header('Content-Type: application/json; charset=utf-8');
            echo $body;
        } elseif ($this->body !== null) {
            echo $this->body; // Content-Type pose par raw()
        }
    }
}

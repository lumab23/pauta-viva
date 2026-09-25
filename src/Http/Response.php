<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    /** @param array<string, mixed>|null $body */
    public function __construct(
        public readonly int $status,
        public readonly ?array $body = null,
        public readonly array $headers = []
    ) {
    }

    /** @param array<string, mixed> $body */
    public static function json(array $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, $headers);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    /** @param array<string, list<string>> $fields */
    public static function error(
        string $code,
        string $message,
        int $status,
        array $fields = [],
        array $headers = []
    ): self {
        $error = ['code' => $code, 'message' => $message];

        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return self::json(['error' => $error], $status, $headers);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        if ($this->body === null) {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $this->body,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}


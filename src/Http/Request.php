<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;
use UnexpectedValueException;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = []
    ) {
    }

    /** @throws JsonException|UnexpectedValueException */
    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $body = [];

        if (in_array($method, ['POST', 'PUT'], true)) {
            $rawBody = file_get_contents('php://input');

            if ($rawBody !== false && trim($rawBody) !== '') {
                $decoded = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);

                if (!is_object($decoded)) {
                    throw new UnexpectedValueException('O corpo JSON deve ser um objeto.');
                }

                $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return new self($method, self::normalizePath($path), $_GET, $body);
    }

    private static function normalizePath(string $path): string
    {
        if ($path === '/') {
            return $path;
        }

        return rtrim($path, '/');
    }
}


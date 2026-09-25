<?php

declare(strict_types=1);

namespace App\Http\Router;

use App\Http\Request;
use App\Http\Response;

final class Router
{
    /** @var list<array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $this->compilePattern($path),
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $request->path, $matches)) {
                continue;
            }

            if ($route['method'] !== $request->method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $parameters = array_filter(
                $matches,
                static fn (string|int $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            return ($route['handler'])($request, $parameters);
        }

        if ($allowedMethods !== []) {
            $allowedMethods = array_values(array_unique($allowedMethods));

            return Response::error(
                'method_not_allowed',
                'Método HTTP não permitido para este recurso.',
                405,
                [],
                ['Allow' => implode(', ', $allowedMethods)]
            );
        }

        return Response::error('route_not_found', 'Rota não encontrada.', 404);
    }

    private function compilePattern(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        $compiled = array_map(
            static function (string $segment): string {
                if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $matches)) {
                    return sprintf('(?P<%s>[^/]+)', $matches[1]);
                }

                return preg_quote($segment, '#');
            },
            $segments
        );

        return '#^/' . implode('/', $compiled) . '$#';
    }
}


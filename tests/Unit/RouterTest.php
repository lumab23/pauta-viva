<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Request;
use App\Http\Response;
use App\Http\Router\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testRouteParametersAreForwarded(): void
    {
        $router = new Router();
        $router->add(
            'GET',
            '/api/pautas/{id}',
            static fn (Request $request, array $parameters): Response => Response::json([
                'id' => $parameters['id'],
            ])
        );

        $response = $router->dispatch(new Request('GET', '/api/pautas/15'));

        self::assertSame(200, $response->status);
        self::assertSame(['id' => '15'], $response->body);
    }

    public function testUnknownRouteAndUnsupportedMethodReturnErrors(): void
    {
        $router = new Router();
        $router->add(
            'GET',
            '/api/pautas',
            static fn (Request $request, array $parameters): Response => Response::json(['data' => []])
        );

        $notFound = $router->dispatch(new Request('GET', '/rota-inexistente'));
        $notAllowed = $router->dispatch(new Request('PATCH', '/api/pautas'));

        self::assertSame(404, $notFound->status);
        self::assertSame('route_not_found', $notFound->body['error']['code']);
        self::assertSame(405, $notAllowed->status);
        self::assertSame(['Allow' => 'GET'], $notAllowed->headers);
    }
}

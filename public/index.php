<?php

declare(strict_types=1);

use App\Config\Environment;
use App\Http\Controller\PautaController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router\Router;
use App\Persistence\Database;
use App\Persistence\PautaRepository;
use App\Validation\PautaValidator;

$projectRoot = dirname(__DIR__);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => [
            'code' => 'dependencies_missing',
            'message' => 'As dependências da aplicação não estão instaladas.',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require $autoload;

try {
    $request = Request::fromGlobals();
} catch (JsonException|UnexpectedValueException) {
    Response::error(
        'invalid_json',
        'O corpo da requisição deve ser um objeto JSON válido.',
        400
    )->send();
    exit;
}

try {
    Environment::load($projectRoot . '/.env');
    $databaseConfig = require $projectRoot . '/config/database.php';

    $repository = new PautaRepository(Database::connect($databaseConfig));
    $controller = new PautaController($repository, new PautaValidator());
    $router = new Router();

    $router->add('GET', '/api/pautas', $controller->index(...));
    $router->add('GET', '/api/pautas/{id}', $controller->show(...));
    $router->add('POST', '/api/pautas', $controller->store(...));
    $router->add('PUT', '/api/pautas/{id}', $controller->update(...));
    $router->add('DELETE', '/api/pautas/{id}', $controller->destroy(...));

    $router->dispatch($request)->send();
} catch (Throwable $exception) {
    // Registra o erro no servidor sem mostrar detalhes para o cliente.
    error_log(sprintf('[pauta-viva] %s', $exception->getMessage()));
    Response::error(
        'internal_error',
        'Não foi possível concluir a operação.',
        500
    )->send();
}

<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Persistence\PautaRepository;
use App\Validation\PautaValidator;

final class PautaController
{
    public function __construct(
        private readonly PautaRepository $repository,
        private readonly PautaValidator $validator
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters = []): Response
    {
        $validation = $this->validator->validateFilters($request->query);

        if ($validation['errors'] !== []) {
            return Response::error(
                'invalid_query',
                'Os parâmetros de consulta são inválidos.',
                400,
                $validation['errors']
            );
        }

        $filters = $validation['data'];
        $result = $this->repository->findAll($filters);

        return Response::json([
            'data' => $result['data'],
            'meta' => [
                'pagina' => $filters['pagina'],
                'por_pagina' => $filters['por_pagina'],
                'total' => $result['total'],
            ],
        ]);
    }

    /** @param array<string, string> $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $id = $this->validatedId($parameters);

        if ($id === null) {
            return $this->invalidIdResponse();
        }

        $pauta = $this->repository->find($id);

        return $pauta === null
            ? $this->notFoundResponse()
            : Response::json(['data' => $pauta]);
    }

    /** @param array<string, string> $parameters */
    public function store(Request $request, array $parameters = []): Response
    {
        $validation = $this->validator->validatePayload($request->body);

        if ($validation['errors'] !== []) {
            return $this->validationErrorResponse($validation['errors']);
        }

        return Response::json(
            ['data' => $this->repository->create($validation['data'])],
            201
        );
    }

    /** @param array<string, string> $parameters */
    public function update(Request $request, array $parameters): Response
    {
        $id = $this->validatedId($parameters);

        if ($id === null) {
            return $this->invalidIdResponse();
        }

        $validation = $this->validator->validatePayload($request->body);

        if ($validation['errors'] !== []) {
            return $this->validationErrorResponse($validation['errors']);
        }

        $pauta = $this->repository->update($id, $validation['data']);

        return $pauta === null
            ? $this->notFoundResponse()
            : Response::json(['data' => $pauta]);
    }

    /** @param array<string, string> $parameters */
    public function destroy(Request $request, array $parameters): Response
    {
        $id = $this->validatedId($parameters);

        if ($id === null) {
            return $this->invalidIdResponse();
        }

        return $this->repository->delete($id)
            ? Response::noContent()
            : $this->notFoundResponse();
    }

    /**
     * @param array<string, list<string>> $fields
     */
    private function validationErrorResponse(array $fields): Response
    {
        return Response::error(
            'validation_error',
            'Os dados enviados são inválidos.',
            422,
            $fields
        );
    }

    /** @param array<string, string> $parameters */
    private function validatedId(array $parameters): ?int
    {
        return $this->validator->validateId($parameters['id'] ?? '');
    }

    private function invalidIdResponse(): Response
    {
        return Response::error(
            'invalid_id',
            'O ID deve ser um número inteiro positivo.',
            400
        );
    }

    private function notFoundResponse(): Response
    {
        return Response::error('pauta_not_found', 'Pauta não encontrada.', 404);
    }
}


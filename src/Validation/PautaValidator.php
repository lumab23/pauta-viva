<?php

declare(strict_types=1);

namespace App\Validation;

use DateTimeImmutable;
use DateTimeZone;

final class PautaValidator
{
    public const STATUSES = ['ideia', 'em_apuracao', 'publicada'];
    public const MAX_DESCRIPTION_LENGTH = 10_000;
    public const MAX_PER_PAGE = 100;

    /**
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, errors: array<string, list<string>>}
     */
    public function validatePayload(array $input): array
    {
        $errors = [];
        $data = [];
        $allowedFields = ['titulo', 'descricao', 'editoria', 'status', 'prazo'];

        foreach (array_diff(array_keys($input), $allowedFields) as $field) {
            $errors[(string) $field][] = 'Este campo não é aceito.';
        }

        $this->validateRequiredString($input, 'titulo', 255, $data, $errors);
        $this->validateRequiredString(
            $input,
            'descricao',
            self::MAX_DESCRIPTION_LENGTH,
            $data,
            $errors
        );
        $this->validateRequiredString($input, 'editoria', 100, $data, $errors);

        if (!array_key_exists('status', $input)) {
            $errors['status'][] = 'O campo é obrigatório.';
        } elseif (!is_string($input['status']) || !in_array($input['status'], self::STATUSES, true)) {
            $errors['status'][] = 'Use ideia, em_apuracao ou publicada.';
        } else {
            $data['status'] = $input['status'];
        }

        $data['prazo'] = null;

        if (array_key_exists('prazo', $input) && $input['prazo'] !== null) {
            $deadline = $this->parseDeadline($input['prazo']);

            if ($deadline === null) {
                $errors['prazo'][] = 'Use uma data ISO 8601 com horário e fuso, por exemplo 2026-10-01T18:00:00-03:00.';
            } else {
                // Converte o prazo para UTC antes de salvar no MySQL.
                $data['prazo'] = $deadline
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d H:i:s');
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{data: array<string, mixed>, errors: array<string, list<string>>}
     */
    public function validateFilters(array $query): array
    {
        $errors = [];
        $data = ['pagina' => 1, 'por_pagina' => 20];
        $allowedFields = ['titulo', 'editoria', 'status', 'pagina', 'por_pagina'];

        foreach (array_diff(array_keys($query), $allowedFields) as $field) {
            $errors[(string) $field][] = 'Parâmetro de consulta desconhecido.';
        }

        $this->validateOptionalFilter($query, 'titulo', 255, $data, $errors);
        $this->validateOptionalFilter($query, 'editoria', 100, $data, $errors);

        if (array_key_exists('status', $query)) {
            if (!is_string($query['status']) || !in_array($query['status'], self::STATUSES, true)) {
                $errors['status'][] = 'Use ideia, em_apuracao ou publicada.';
            } else {
                $data['status'] = $query['status'];
            }
        }

        $this->validatePositiveInteger($query, 'pagina', null, $data, $errors);
        $this->validatePositiveInteger($query, 'por_pagina', self::MAX_PER_PAGE, $data, $errors);

        return ['data' => $data, 'errors' => $errors];
    }

    public function validateId(string $id): ?int
    {
        $validated = filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $validated === false ? null : $validated;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $data
     * @param array<string, list<string>> $errors
     */
    private function validateRequiredString(
        array $input,
        string $field,
        int $maxLength,
        array &$data,
        array &$errors
    ): void {
        if (!array_key_exists($field, $input)) {
            $errors[$field][] = 'O campo é obrigatório.';
            return;
        }

        if (!is_string($input[$field])) {
            $errors[$field][] = 'O campo deve ser texto.';
            return;
        }

        $value = trim($input[$field]);

        if ($value === '') {
            $errors[$field][] = 'O campo não pode ficar vazio.';
            return;
        }

        if (mb_strlen($value) > $maxLength) {
            $errors[$field][] = sprintf('Use no máximo %d caracteres.', $maxLength);
            return;
        }

        $data[$field] = $value;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $data
     * @param array<string, list<string>> $errors
     */
    private function validateOptionalFilter(
        array $query,
        string $field,
        int $maxLength,
        array &$data,
        array &$errors
    ): void {
        if (!array_key_exists($field, $query)) {
            return;
        }

        if (!is_string($query[$field])) {
            $errors[$field][] = 'O parâmetro deve ser texto.';
            return;
        }

        $value = trim($query[$field]);

        if ($value === '' || mb_strlen($value) > $maxLength) {
            $errors[$field][] = sprintf('Use entre 1 e %d caracteres.', $maxLength);
            return;
        }

        $data[$field] = $value;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $data
     * @param array<string, list<string>> $errors
     */
    private function validatePositiveInteger(
        array $query,
        string $field,
        ?int $maximum,
        array &$data,
        array &$errors
    ): void {
        if (!array_key_exists($field, $query)) {
            return;
        }

        if (!is_string($query[$field]) && !is_int($query[$field])) {
            $errors[$field][] = 'Use um número inteiro positivo.';
            return;
        }

        $value = filter_var(
            $query[$field],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($value === false || ($maximum !== null && $value > $maximum)) {
            $errors[$field][] = $maximum === null
                ? 'Use um número inteiro positivo.'
                : sprintf('Use um número inteiro entre 1 e %d.', $maximum);
            return;
        }

        $data[$field] = $value;
    }

    private function parseDeadline(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        // Também aceita datas terminadas em Z, que representa UTC.
        $normalized = str_ends_with($value, 'Z')
            ? substr($value, 0, -1) . '+00:00'
            : $value;

        $deadline = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $normalized);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            $deadline === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $deadline->format('Y-m-d\TH:i:sP') !== $normalized
        ) {
            return null;
        }

        return $deadline;
    }
}

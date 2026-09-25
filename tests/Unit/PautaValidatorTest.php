<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validation\PautaValidator;
use PHPUnit\Framework\TestCase;

final class PautaValidatorTest extends TestCase
{
    private PautaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PautaValidator();
    }

    public function testValidPayloadIsNormalized(): void
    {
        $result = $this->validator->validatePayload([
            'titulo' => '  Pauta automatizada  ',
            'descricao' => '  Descrição usada somente no teste.  ',
            'editoria' => '  testes  ',
            'status' => 'em_apuracao',
            'prazo' => '2030-01-10T12:30:00-03:00',
        ]);

        self::assertSame([], $result['errors']);
        self::assertSame('Pauta automatizada', $result['data']['titulo']);
        self::assertSame('Descrição usada somente no teste.', $result['data']['descricao']);
        self::assertSame('testes', $result['data']['editoria']);
        self::assertSame('em_apuracao', $result['data']['status']);
        self::assertSame('2030-01-10 15:30:00', $result['data']['prazo']);
    }

    public function testRequiredFieldsAndAllowedStatusAreValidated(): void
    {
        $result = $this->validator->validatePayload([
            'titulo' => '   ',
            'status' => 'rascunho',
        ]);

        self::assertArrayHasKey('titulo', $result['errors']);
        self::assertArrayHasKey('descricao', $result['errors']);
        self::assertArrayHasKey('editoria', $result['errors']);
        self::assertArrayHasKey('status', $result['errors']);
    }

    public function testFieldSizesUnknownFieldsAndDeadlineAreValidated(): void
    {
        $result = $this->validator->validatePayload([
            'titulo' => str_repeat('a', 256),
            'descricao' => str_repeat('b', PautaValidator::MAX_DESCRIPTION_LENGTH + 1),
            'editoria' => str_repeat('c', 101),
            'status' => 'ideia',
            'prazo' => '2030-02-30T10:00:00-03:00',
            'id' => 10,
        ]);

        self::assertArrayHasKey('titulo', $result['errors']);
        self::assertArrayHasKey('descricao', $result['errors']);
        self::assertArrayHasKey('editoria', $result['errors']);
        self::assertArrayHasKey('prazo', $result['errors']);
        self::assertArrayHasKey('id', $result['errors']);
    }

    public function testNullDeadlineIsAccepted(): void
    {
        $result = $this->validator->validatePayload($this->validPayload());

        self::assertSame([], $result['errors']);
        self::assertNull($result['data']['prazo']);
    }

    public function testFiltersReceiveDefaultsAndAreValidated(): void
    {
        $defaults = $this->validator->validateFilters([]);

        self::assertSame([], $defaults['errors']);
        self::assertSame(['pagina' => 1, 'por_pagina' => 20], $defaults['data']);

        $invalid = $this->validator->validateFilters([
            'status' => 'invalido',
            'pagina' => '0',
            'por_pagina' => '101',
            'outro' => 'valor',
        ]);

        self::assertArrayHasKey('status', $invalid['errors']);
        self::assertArrayHasKey('pagina', $invalid['errors']);
        self::assertArrayHasKey('por_pagina', $invalid['errors']);
        self::assertArrayHasKey('outro', $invalid['errors']);
    }

    public function testIdMustBeAPositiveInteger(): void
    {
        self::assertSame(42, $this->validator->validateId('42'));
        self::assertNull($this->validator->validateId('0'));
        self::assertNull($this->validator->validateId('-1'));
        self::assertNull($this->validator->validateId('abc'));
        self::assertNull($this->validator->validateId('1.5'));
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'titulo' => 'Pauta automatizada',
            'descricao' => 'Descrição usada somente no teste.',
            'editoria' => 'testes',
            'status' => 'ideia',
            'prazo' => null,
        ];
    }
}


<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Persistence\Database;
use App\Persistence\PautaRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PautaRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PautaRepository $repository;

    protected function setUp(): void
    {
        $database = getenv('TEST_DB_DATABASE');

        if ($database === false || trim($database) === '') {
            self::markTestSkipped('Defina TEST_DB_DATABASE para executar os testes de integração.');
        }

        if (!str_ends_with($database, '_test')) {
            self::fail('TEST_DB_DATABASE deve terminar em _test.');
        }

        $developmentDatabase = getenv('DB_DATABASE');

        if ($developmentDatabase !== false && $developmentDatabase === $database) {
            self::fail('O banco de teste deve ser diferente de DB_DATABASE.');
        }

        $this->pdo = Database::connect([
            'host' => $this->environment('TEST_DB_HOST', '127.0.0.1'),
            'port' => $this->environment('TEST_DB_PORT', '3306'),
            'database' => $database,
            'username' => $this->environment('TEST_DB_USERNAME', 'root'),
            'password' => $this->environment('TEST_DB_PASSWORD', ''),
        ]);

        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/001_create_pautas.sql');
        self::assertNotFalse($migration);
        $this->pdo->exec($migration);
        $this->pdo->exec('TRUNCATE TABLE pautas');

        $this->repository = new PautaRepository($this->pdo);
    }

    public function testCreateFindUpdateAndDelete(): void
    {
        $created = $this->repository->create($this->pautaData());

        self::assertGreaterThan(0, $created['id']);
        self::assertSame('Pauta automatizada A', $created['titulo']);
        self::assertNull($created['prazo']);
        self::assertSame($created, $this->repository->find($created['id']));

        $updatedData = $this->pautaData();
        $updatedData['titulo'] = 'Pauta automatizada atualizada';
        $updatedData['status'] = 'publicada';
        $updatedData['prazo'] = '2030-01-01 15:00:00';
        $updated = $this->repository->update($created['id'], $updatedData);

        self::assertNotNull($updated);
        self::assertSame('Pauta automatizada atualizada', $updated['titulo']);
        self::assertSame('publicada', $updated['status']);
        self::assertSame('2030-01-01T15:00:00+00:00', $updated['prazo']);

        self::assertTrue($this->repository->delete($created['id']));
        self::assertNull($this->repository->find($created['id']));
        self::assertFalse($this->repository->delete($created['id']));
    }

    public function testListSupportsSearchFiltersAndPaginationMetadata(): void
    {
        $this->repository->create($this->pautaData());

        $second = $this->pautaData();
        $second['titulo'] = 'Pauta automatizada B';
        $second['editoria'] = 'outra-editoria-teste';
        $second['status'] = 'em_apuracao';
        $this->repository->create($second);

        $result = $this->repository->findAll([
            'titulo' => 'automatizada B',
            'editoria' => 'outra-editoria-teste',
            'status' => 'em_apuracao',
            'pagina' => 1,
            'por_pagina' => 10,
        ]);

        self::assertSame(1, $result['total']);
        self::assertCount(1, $result['data']);
        self::assertSame('Pauta automatizada B', $result['data'][0]['titulo']);
    }

    /** @return array{titulo: string, descricao: string, editoria: string, status: string, prazo: string|null} */
    private function pautaData(): array
    {
        return [
            'titulo' => 'Pauta automatizada A',
            'descricao' => 'Registro criado exclusivamente pelo teste automatizado.',
            'editoria' => 'editoria-teste',
            'status' => 'ideia',
            'prazo' => null,
        ];
    }

    private function environment(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false ? $default : $value;
    }
}

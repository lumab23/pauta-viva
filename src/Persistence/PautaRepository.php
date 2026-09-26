<?php

declare(strict_types=1);

namespace App\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class PautaRepository
{
    private const COLUMNS = 'id, titulo, descricao, editoria, status, prazo, criado_em, atualizado_em';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{titulo?: string, editoria?: string, status?: string, pagina: int, por_pagina: int} $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function findAll(array $filters): array
    {
        [$where, $parameters] = $this->buildFilters($filters);

        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM pautas' . $where);
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();

        $offset = ($filters['pagina'] - 1) * $filters['por_pagina'];
        $sql = sprintf(
            'SELECT %s FROM pautas%s ORDER BY criado_em DESC, id DESC LIMIT :limit OFFSET :offset',
            self::COLUMNS,
            $where
        );
        $statement = $this->pdo->prepare($sql);

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->bindValue(':limit', $filters['por_pagina'], PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();

        return [
            'data' => array_map($this->mapRow(...), $rows),
            'total' => $total,
        ];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            sprintf('SELECT %s FROM pautas WHERE id = :id', self::COLUMNS)
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();

        return $row === false ? null : $this->mapRow($row);
    }

    /**
     * @param array{titulo: string, descricao: string, editoria: string, status: string, prazo: string|null} $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $statement = $this->pdo->prepare(
            <<<'SQL'
                INSERT INTO pautas (titulo, descricao, editoria, status, prazo, criado_em, atualizado_em)
                VALUES (:titulo, :descricao, :editoria, :status, :prazo, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                SQL
        );
        $this->bindPauta($statement, $data);
        $statement->execute();

        $pauta = $this->find((int) $this->pdo->lastInsertId());

        if ($pauta === null) {
            throw new RuntimeException('A pauta criada não pôde ser recuperada.');
        }

        return $pauta;
    }

    /**
     * @param array{titulo: string, descricao: string, editoria: string, status: string, prazo: string|null} $data
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        $statement = $this->pdo->prepare(
            <<<'SQL'
                UPDATE pautas
                SET titulo = :titulo,
                    descricao = :descricao,
                    editoria = :editoria,
                    status = :status,
                    prazo = :prazo,
                    atualizado_em = UTC_TIMESTAMP()
                WHERE id = :id
                SQL
        );
        $this->bindPauta($statement, $data);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM pautas WHERE id = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    /**
     * @param array{titulo: string, descricao: string, editoria: string, status: string, prazo: string|null} $data
     */
    private function bindPauta(\PDOStatement $statement, array $data): void
    {
        $statement->bindValue(':titulo', $data['titulo']);
        $statement->bindValue(':descricao', $data['descricao']);
        $statement->bindValue(':editoria', $data['editoria']);
        $statement->bindValue(':status', $data['status']);
        $statement->bindValue(':prazo', $data['prazo'], $data['prazo'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{string, array<string, string>}
     */
    private function buildFilters(array $filters): array
    {
        $clauses = [];
        $parameters = [];

        if (isset($filters['titulo'])) {
            $clauses[] = "titulo LIKE :titulo ESCAPE '!'";
            // Escapa os curingas do LIKE para pesquisar o texto digitado.
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['titulo']);
            $parameters[':titulo'] = '%' . $escaped . '%';
        }

        if (isset($filters['editoria'])) {
            $clauses[] = 'editoria = :editoria';
            $parameters[':editoria'] = $filters['editoria'];
        }

        if (isset($filters['status'])) {
            $clauses[] = 'status = :status';
            $parameters[':status'] = $filters['status'];
        }

        return [
            $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses),
            $parameters,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        // As datas retornadas pelo banco estão em UTC.
        $utc = new DateTimeZone('UTC');

        return [
            'id' => (int) $row['id'],
            'titulo' => $row['titulo'],
            'descricao' => $row['descricao'],
            'editoria' => $row['editoria'],
            'status' => $row['status'],
            'prazo' => $row['prazo'] === null
                ? null
                : (new DateTimeImmutable($row['prazo'], $utc))->format(DATE_ATOM),
            'criado_em' => (new DateTimeImmutable($row['criado_em'], $utc))->format(DATE_ATOM),
            'atualizado_em' => (new DateTimeImmutable($row['atualizado_em'], $utc))->format(DATE_ATOM),
        ];
    }
}

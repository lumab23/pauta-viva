# Contrato previsto da API e modelo de dados

Este documento define o alvo para a etapa de back-end. Os endpoints abaixo ainda não estão implementados.

## Recurso `pauta`

| Campo | Tipo na API | Regra prevista |
|---|---|---|
| `id` | inteiro | Identificador positivo, gerado pelo banco |
| `titulo` | string | Obrigatório; limite proposto de 255 caracteres |
| `descricao` | string | Obrigatório; texto da pauta |
| `editoria` | string | Obrigatório; limite proposto de 100 caracteres |
| `status` | string | Obrigatório: `ideia`, `em_apuracao` ou `publicada` |
| `prazo` | string ou `null` | Opcional; data e hora em ISO 8601 |
| `criado_em` | string | Data e hora em ISO 8601; definida pelo servidor |
| `atualizado_em` | string | Data e hora em ISO 8601; definida pelo servidor |

Os limites propostos deverão ser confirmados ao implementar a validação. Datas enviadas e retornadas pela API devem declarar o fuso horário; a estratégia de armazenamento será definida de forma consistente na implementação.

## Modelo MySQL planejado

Tabela `pautas`:

| Coluna | Tipo proposto | Restrições propostas |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | chave primária, auto incremento |
| `titulo` | `VARCHAR(255)` | não nulo |
| `descricao` | `TEXT` | não nulo |
| `editoria` | `VARCHAR(100)` | não nulo, indexado |
| `status` | `ENUM('ideia','em_apuracao','publicada')` | não nulo, indexado |
| `prazo` | `DATETIME` | nulo permitido |
| `criado_em` | `DATETIME` | não nulo |
| `atualizado_em` | `DATETIME` | não nulo |

A migration e os índices definitivos serão criados na etapa de back-end. A busca por título deve usar correspondência parcial; qualquer otimização adicional deve ser orientada por necessidade real.

## Endpoints planejados

Base: `/api/pautas`

| Método | Caminho | Finalidade | Sucesso previsto |
|---|---|---|---|
| `GET` | `/api/pautas` | Listar, buscar e filtrar pautas | `200` |
| `GET` | `/api/pautas/{id}` | Obter uma pauta | `200` |
| `POST` | `/api/pautas` | Criar uma pauta | `201` |
| `PUT` | `/api/pautas/{id}` | Substituir os campos editáveis | `200` |
| `DELETE` | `/api/pautas/{id}` | Excluir uma pauta | `204` |

### Consulta da coleção

Parâmetros opcionais de `GET /api/pautas`:

- `titulo`: busca parcial pelo título.
- `editoria`: correspondência exata da editoria.
- `status`: correspondência exata; aceita somente os três status definidos.
- `pagina`: inteiro positivo; padrão proposto `1`.
- `por_pagina`: inteiro positivo; padrão proposto `20`, com limite a definir.

Os filtros podem ser combinados. Ordenação padrão proposta: `criado_em` decrescente.

Exemplo de consulta:

```http
GET /api/pautas?titulo=eleicoes&editoria=politica&status=em_apuracao
```

### Corpo de criação e atualização

Campos aceitos em `POST` e `PUT`:

```json
{
  "titulo": "Cobertura das eleições municipais",
  "descricao": "Apurar propostas dos candidatos para mobilidade urbana.",
  "editoria": "politica",
  "status": "ideia",
  "prazo": "2026-10-01T18:00:00-03:00"
}
```

`prazo` pode ser `null`. `id`, `criado_em` e `atualizado_em` não são campos graváveis pelo cliente. A semântica de atualização parcial não está prevista inicialmente; se necessária, poderá ser especificada depois com `PATCH`.

## Formato previsto das respostas

Objeto individual:

```json
{
  "data": {
    "id": 1,
    "titulo": "Cobertura das eleições municipais",
    "descricao": "Apurar propostas dos candidatos para mobilidade urbana.",
    "editoria": "politica",
    "status": "ideia",
    "prazo": "2026-10-01T18:00:00-03:00",
    "criado_em": "2026-09-25T10:00:00-03:00",
    "atualizado_em": "2026-09-25T10:00:00-03:00"
  }
}
```

Coleção paginada:

```json
{
  "data": [],
  "meta": {
    "pagina": 1,
    "por_pagina": 20,
    "total": 0
  }
}
```

Erro:

```json
{
  "error": {
    "code": "validation_error",
    "message": "Os dados enviados são inválidos.",
    "fields": {
      "status": ["Use ideia, em_apuracao ou publicada."]
    }
  }
}
```

Códigos de erro previstos: `400` para JSON ou parâmetros inválidos, `404` para pauta inexistente, `422` para falha de validação e `500` para erro interno sem exposição de dados sensíveis.

## Separação de responsabilidades prevista

- O roteador identifica método e caminho e encaminha a requisição.
- O controlador transforma entrada e saída HTTP e coordena o caso solicitado.
- A validação rejeita dados ausentes, inválidos ou fora dos limites.
- A persistência executa consultas parametrizadas e converte registros do banco.

Essas responsabilidades serão implementadas na etapa de back-end. Não há garantia de compatibilidade até o contrato ser acompanhado por testes.


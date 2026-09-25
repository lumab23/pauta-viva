# API de pautas

Base local: `http://localhost:8000/api/pautas`

Criação e edição recebem um objeto JSON. Toda resposta com corpo usa `Content-Type: application/json; charset=utf-8`.

## Modelo e validações

| Campo | Tipo | Regra |
|---|---|---|
| `id` | inteiro | Positivo, gerado pelo banco e somente leitura |
| `titulo` | string | Obrigatório, de 1 a 255 caracteres após remover espaços externos |
| `descricao` | string | Obrigatório, de 1 a 10.000 caracteres após remover espaços externos |
| `editoria` | string | Obrigatório, de 1 a 100 caracteres após remover espaços externos |
| `status` | string | Obrigatório: `ideia`, `em_apuracao` ou `publicada` |
| `prazo` | string ou `null` | Opcional, ISO 8601 com data, horário e fuso |
| `criado_em` | string | ISO 8601 em UTC, definido pelo servidor e somente leitura |
| `atualizado_em` | string | ISO 8601 em UTC, definido pelo servidor e somente leitura |

O prazo aceita, por exemplo, `2030-01-10T12:30:00-03:00` ou `2030-01-10T15:30:00Z`. O valor é convertido para UTC antes da gravação. Campos desconhecidos no corpo são rejeitados.

## Criar

```http
POST /api/pautas
Content-Type: application/json

{
  "titulo": "Pauta de demonstração técnica",
  "descricao": "Conteúdo usado apenas para experimentar a API local.",
  "editoria": "teste",
  "status": "ideia",
  "prazo": "2030-01-10T12:30:00-03:00"
}
```

Resposta `201 Created`:

```json
{
  "data": {
    "id": 1,
    "titulo": "Pauta de demonstração técnica",
    "descricao": "Conteúdo usado apenas para experimentar a API local.",
    "editoria": "teste",
    "status": "ideia",
    "prazo": "2030-01-10T15:30:00+00:00",
    "criado_em": "2026-09-25T13:00:00+00:00",
    "atualizado_em": "2026-09-25T13:00:00+00:00"
  }
}
```

## Listar, buscar e filtrar

```http
GET /api/pautas?titulo=demonstracao&editoria=teste&status=ideia&pagina=1&por_pagina=20
```

Todos os parâmetros são opcionais:

- `titulo`: busca parcial, de 1 a 255 caracteres; `%` e `_` são tratados literalmente.
- `editoria`: correspondência exata, de 1 a 100 caracteres.
- `status`: correspondência exata com um status permitido.
- `pagina`: inteiro positivo, padrão `1`.
- `por_pagina`: inteiro entre `1` e `100`, padrão `20`.

Os filtros podem ser combinados. Parâmetros desconhecidos ou inválidos retornam `400`. A ordenação é por criação e ID, do mais recente para o mais antigo.

Resposta `200 OK`:

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

## Consultar por ID

```http
GET /api/pautas/1
```

Retorna `200 OK` com a pauta em `data`. Um ID inexistente retorna `404`; um ID que não seja inteiro positivo retorna `400`.

## Editar

```http
PUT /api/pautas/1
Content-Type: application/json

{
  "titulo": "Pauta de demonstração revisada",
  "descricao": "Conteúdo atualizado somente para experimentar a API local.",
  "editoria": "teste",
  "status": "publicada",
  "prazo": null
}
```

Retorna `200 OK` com a pauta atualizada. `PUT` substitui todos os campos editáveis, portanto todos os campos obrigatórios devem estar presentes. Não há atualização parcial com `PATCH`.

## Excluir

```http
DELETE /api/pautas/1
```

Retorna `204 No Content`, sem corpo. Uma pauta inexistente retorna `404`.

## Respostas de erro

Exemplo de `422 Unprocessable Entity`:

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

| Código HTTP | Situação |
|---|---|
| `200` | Consulta ou edição concluída |
| `201` | Criação concluída |
| `204` | Exclusão concluída |
| `400` | JSON, ID ou parâmetros de consulta inválidos |
| `404` | Pauta ou rota inexistente |
| `405` | Método não permitido para uma rota existente |
| `422` | Campos da pauta não passaram na validação |
| `500` | Dependências, configuração, banco ou outra falha interna |

Erros internos retornam uma mensagem genérica, sem credenciais, SQL ou rastros de execução.

## Persistência

A tabela é criada por `database/migrations/001_create_pautas.sql`. Ela usa InnoDB, `utf8mb4`, índices para editoria e status e um `ENUM` que restringe os três status. Todas as consultas que recebem valores externos são parametrizadas com PDO.

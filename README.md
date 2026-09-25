# Pauta Viva

Back-end de um sistema de gestão de pautas jornalísticas, criado para um teste prático de aprendiz. A aplicação oferece uma API HTTP em PHP para criação, consulta, edição, exclusão, busca e filtragem de pautas.

## Tecnologias

- PHP 8.2 ou superior
- MySQL 8
- PDO MySQL
- Composer 2
- PHPUnit 11
- Git

## Requisitos

- PHP 8.2+ com as extensões JSON, Mbstring, PDO e PDO MySQL
- Composer 2
- MySQL Server 8 e seu cliente de linha de comando

## Instalação

1. Instale as dependências:

   ```sh
   composer install
   ```

2. Crie o banco e um usuário local. Troque a senha do exemplo:

   ```sql
   CREATE DATABASE pauta_viva
       CHARACTER SET utf8mb4
       COLLATE utf8mb4_0900_ai_ci;

   CREATE USER 'pauta_viva'@'localhost' IDENTIFIED BY 'troque_esta_senha';
   GRANT ALL PRIVILEGES ON pauta_viva.* TO 'pauta_viva'@'localhost';
   ```

3. Copie a configuração e informe as credenciais locais:

   ```sh
   cp .env.example .env
   ```

   O arquivo `.env` não deve ser versionado.

4. Aplique a migration:

   ```sh
   mysql -u pauta_viva -p pauta_viva < database/migrations/001_create_pautas.sql
   ```

5. Inicie o servidor de desenvolvimento na raiz do projeto:

   ```sh
   php -S localhost:8000 -t public
   ```

A API estará disponível em `http://localhost:8000/api/pautas`.

## Endpoints

| Método | Caminho | Resultado de sucesso |
|---|---|---|
| `GET` | `/api/pautas` | Lista paginada (`200`) |
| `GET` | `/api/pautas/{id}` | Pauta encontrada (`200`) |
| `POST` | `/api/pautas` | Pauta criada (`201`) |
| `PUT` | `/api/pautas/{id}` | Pauta substituída (`200`) |
| `DELETE` | `/api/pautas/{id}` | Sem conteúdo (`204`) |

A listagem aceita os parâmetros `titulo`, `editoria`, `status`, `pagina` e `por_pagina`. Consulte [a documentação da API](docs/api.md) para todas as validações, respostas e códigos HTTP.

Exemplo de criação com conteúdo explicitamente demonstrativo:

```sh
curl -i -X POST http://localhost:8000/api/pautas \
  -H 'Content-Type: application/json' \
  -d '{
    "titulo": "Pauta de demonstração técnica",
    "descricao": "Conteúdo usado apenas para experimentar a API local.",
    "editoria": "teste",
    "status": "ideia",
    "prazo": null
  }'
```

## Testes

Os testes unitários não acessam o banco:

```sh
composer test
```

Os testes de integração criam a tabela e limpam seu conteúdo antes de cada caso. Para impedir o uso acidental do banco de desenvolvimento, eles só executam quando `TEST_DB_DATABASE` está definido, termina em `_test` e é diferente de `DB_DATABASE`.

Crie um banco exclusivo para testes:

```sql
CREATE DATABASE pauta_viva_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_0900_ai_ci;
```

Execute a suíte com credenciais que tenham acesso somente a esse banco:

```sh
TEST_DB_HOST=127.0.0.1 \
TEST_DB_PORT=3306 \
TEST_DB_DATABASE=pauta_viva_test \
TEST_DB_USERNAME=pauta_viva_test \
TEST_DB_PASSWORD='senha_local_de_teste' \
composer test:integration
```

Para executar as duas suítes, use `composer test:all` com as mesmas variáveis. Sem `TEST_DB_DATABASE`, os testes de integração são marcados como ignorados.

## Estrutura

```text
.
├── config/database.php                 # Configuração do MySQL
├── database/migrations/                # Criação reproduzível da tabela
├── docs/api.md                         # Contrato e exemplos da API
├── public/index.php                    # Composição e ponto de entrada HTTP
├── src/
│   ├── Config/Environment.php          # Carregamento do arquivo .env
│   ├── Http/
│   │   ├── Controller/                 # Coordenação das operações
│   │   ├── Router/                     # Resolução de método e caminho
│   │   ├── Request.php                 # Entrada HTTP
│   │   └── Response.php                # Respostas JSON
│   ├── Persistence/                    # Conexão PDO e consultas parametrizadas
│   └── Validation/                     # Validação e normalização da entrada
└── tests/
    ├── Integration/                    # CRUD em banco exclusivo de teste
    └── Unit/                           # Validação e roteamento
```

As datas são armazenadas em UTC e retornadas com o deslocamento `+00:00`. Falhas internas produzem uma mensagem genérica e não expõem credenciais, SQL ou rastros de execução ao cliente.

## Fora do escopo

A interface, autenticação, upload, integrações externas e recursos de IA não fazem parte desta etapa.

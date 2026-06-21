# Estrutura do Projeto

Backend em **PHP puro** (sem framework), seguindo MVC com separacao em camadas
(Controller -> Service -> Repository), padrao Repository e Service Layer. Toda a
comunicacao com o frontend e feita por **API REST com respostas JSON**.

## Visao geral das pastas

```
backend/
├── config/          Arquivos de configuracao (retornam arrays)
├── core/            Micro-framework proprio (infra: roteamento, request, etc.)
│   └── Exceptions/  Excecoes de aplicacao (HTTP e validacao)
├── controllers/     Recebem a requisicao, chamam services, devolvem JSON
├── services/        Regras de negocio (camada de dominio)
├── repositories/    Acesso a dados (PDO) — unica camada que fala com o banco
├── models/          Estruturas de dados (entidades) e mapeamento de linhas
├── middlewares/     CORS, autenticacao e autorizacao
├── helpers/         Utilitarios (Auth, CSRF, seguranca, rate limit, funcoes)
├── routes/          Definicao das rotas (web e api)
├── public/          Webroot: front controller (index.php) e .htaccess
│   └── uploads/     Imagens de produtos (servidas estaticamente)
├── docs/            Documentacao e o script Banco.sql
├── tests/           Runner de testes proprio (sem dependencias)
├── storage/         Cache (rate limit) e e-mails gerados no driver "log"
├── logs/            Arquivos de log de fallback
├── composer.json    Dependencias externas (Mercado Pago e PHPMailer)
└── .env.example     Modelo de configuracao de ambiente
```

## Camadas e responsabilidades

### Controllers (`controllers/`)
Camada fina. Extraem dados da `Request`, delegam para os Services e formatam a
resposta via `Response` (envelope JSON). Nao contem regra de negocio nem SQL.

### Services (`services/`)
Concentram as **regras de negocio**: autenticacao, checkout e restricao de
entrega, ciclo de vida e criacao transacional de pedidos, pagamentos
(Mercado Pago), geracao de codigo de pedido, e-mail e logs. Orquestram um ou
mais repositories.

### Repositories (`repositories/`)
Unica camada que acessa o banco, sempre via **PDO com prepared statements**.
`BaseRepository` oferece os metodos comuns (`fetch`, `fetchAll`, `execute`,
`insertGetId`). Cada repository recebe opcionalmente uma conexao `PDO` no
construtor (por padrao, a conexao compartilhada), o que facilita testes e
transacoes.

### Models (`models/`)
Entidades simples com `fromRow()` (constroi a entidade a partir de uma linha do
banco) e `toArray()` (serializacao para JSON). Dados sensiveis, como o hash de
senha, nunca sao serializados.

### Middlewares (`middlewares/`)
- `CorsMiddleware` — cabecalhos CORS conforme a URL do frontend.
- `AuthMiddleware` — exige sessao autenticada (401 caso contrario).
- `AdminMiddleware` — exige perfil de administrador (401/403).

## O micro-framework (`core/`)

`core/` e a **infraestrutura** que sustenta a aplicacao — nao substitui as
regras de negocio, que vivem nos services. Principais componentes:

- **Autoload** — autoloader PSR-4 proprio; carrega as classes `App\*` mesmo sem
  o Composer.
- **Router** — roteamento com grupos, parametros (`{id}`) e middlewares por
  rota; a primeira rota correspondente vence.
- **Request / Response** — abstracao da requisicao (JSON ou formulario) e das
  respostas JSON padronizadas.
- **Validator** — validacao por regras textuais (`required`, `email`, `min`,
  `max`, `cpf`, `cep`, `telefone`, etc.), com mensagens em portugues.
- **Database** — conexao PDO unica (singleton) e controle de transacoes.
- **Config / Env** — leitura de configuracao (notacao por ponto) e carregamento
  do arquivo `.env`.
- **Exceptions** — `HttpException` (status HTTP) e `ValidationException`
  (status 422 com a lista de erros).

## Namespaces (PSR-4)

| Prefixo              | Pasta            |
| -------------------- | ---------------- |
| `App\Core\`          | `core/`          |
| `App\Helpers\`       | `helpers/`       |
| `App\Middlewares\`   | `middlewares/`   |
| `App\Models\`        | `models/`        |
| `App\Repositories\`  | `repositories/`  |
| `App\Services\`      | `services/`      |
| `App\Controllers\`   | `controllers/`   |

O mapeamento e declarado tanto no `composer.json` quanto no autoloader proprio
(`core/Autoload.php`), garantindo o funcionamento com ou sem Composer.

## Fluxo de uma requisicao

```
Requisicao
   -> public/index.php (front controller)
      -> Autoload + ambiente + sessao
      -> CORS (responde preflight OPTIONS)
      -> Rate limit (por IP)
      -> CSRF (exceto metodos seguros e webhook)
      -> Router (web.php + api.php)
         -> Middlewares da rota (Auth/Admin)
         -> Controller
            -> Service (regra de negocio)
               -> Repository (PDO)
      -> Resposta JSON (envelope)
```

Erros sao tratados de forma centralizada no front controller: `ValidationException`
vira 422 com a lista de erros, `HttpException` usa seu proprio status, e qualquer
outra excecao e registrada e devolvida como 500 (sem vazar detalhes internos
quando `APP_DEBUG=false`).

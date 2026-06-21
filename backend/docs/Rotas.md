# Rotas da API

Base: todas as rotas da API ficam sob o prefixo `/api`. As respostas seguem um
**envelope JSON** padrao.

## Envelope de resposta

Sucesso:

```json
{
  "success": true,
  "message": "Mensagem descritiva.",
  "data": { }
}
```

Erro:

```json
{
  "success": false,
  "message": "Mensagem do erro.",
  "errors": { "campo": ["detalhe"] }
}
```

O campo `data` aparece quando ha retorno; `errors` aparece em falhas de
validacao (HTTP 422).

## Convencoes

- **Autenticacao**: baseada em sessao (cookie). Apos o login, o cookie de sessao
  e enviado automaticamente pelo navegador.
- **CSRF**: metodos que alteram estado (`POST`, `PUT`, `PATCH`, `DELETE`) exigem
  o cabecalho `X-CSRF-Token`. Obtenha o token em `GET /api/csrf-token`. Metodos
  seguros (`GET`/`HEAD`) e o webhook sao isentos.
- **Rate limit**: limite global por IP (padrao 120 req/min, configuravel). Ao
  exceder, retorna HTTP 429 com o cabecalho `Retry-After`.
- **Cabecalhos de seguranca**: todas as respostas (inclusive erros) incluem
  `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
  `Content-Security-Policy` e `Permissions-Policy`.
- **Auth?**: coluna que indica se a rota exige usuario autenticado; **Admin**
  indica que exige perfil de administrador.

## Utilitario e status

| Metodo | Caminho           | Auth? | Descricao                              |
| ------ | ----------------- | ----- | -------------------------------------- |
| GET    | `/`               | Nao   | Status da API.                         |
| GET    | `/health`         | Nao   | Verificacao de saude (uptime).         |
| GET    | `/api/csrf-token` | Nao   | Retorna o token CSRF da sessao.        |

## Autenticacao — `/api/auth`

| Metodo | Caminho                    | Auth? | Corpo / parametros                                  |
| ------ | -------------------------- | ----- | --------------------------------------------------- |
| POST   | `/api/auth/registrar`      | Nao   | `nome`, `sobrenome`, `email`, `telefone`, `senha`, `senha_confirmation` |
| GET    | `/api/auth/verificar-email`| Nao   | `?token=...` (link enviado por e-mail)              |
| POST   | `/api/auth/login`          | Nao   | `email`, `senha`                                    |
| POST   | `/api/auth/esqueci-senha`  | Nao   | `email`                                             |
| POST   | `/api/auth/confirmar-codigo`| Nao  | `email`, `codigo` (6 digitos)                       |
| POST   | `/api/auth/redefinir-senha`| Nao   | `email`, `codigo`, `senha`, `senha_confirmation`    |
| POST   | `/api/auth/logout`         | Sim   | —                                                   |
| GET    | `/api/auth/eu`             | Sim   | — (dados do usuario logado)                         |
| POST   | `/api/auth/alterar-senha`  | Sim   | `senha_atual`, `senha`, `senha_confirmation`        |

### Fluxo de recuperacao de senha

`esqueci-senha` (envia codigo de 6 digitos por e-mail) -> `confirmar-codigo`
(valida o codigo) -> `redefinir-senha` (define a nova senha). O codigo expira em
30 minutos, e de uso unico e armazenado com hash; ha limite de tentativas. Por
seguranca, `esqueci-senha` nao revela se o e-mail existe.

## Catalogo (publico)

| Metodo | Caminho                  | Auth? | Descricao                                              |
| ------ | ------------------------ | ----- | ------------------------------------------------------ |
| GET    | `/api/categorias`        | Nao   | Lista as categorias ativas.                            |
| GET    | `/api/produtos`          | Nao   | Lista produtos. Filtros: `?categoria=`, `?pagina=`, `?por_pagina=`. |
| GET    | `/api/produtos/busca`    | Nao   | Busca por termo: `?q=...`.                              |
| GET    | `/api/produtos/{slug}`   | Nao   | Detalhe de um produto pelo slug.                       |

> A rota `/api/produtos/busca` e registrada antes de `/api/produtos/{slug}` para
> que "busca" nao seja interpretado como um slug.

## Carrinho (autenticado) — `/api/carrinho`

| Metodo | Caminho                   | Auth? | Corpo / parametros                |
| ------ | ------------------------- | ----- | --------------------------------- |
| GET    | `/api/carrinho`           | Sim   | — (carrinho do usuario)           |
| POST   | `/api/carrinho/itens`     | Sim   | `produto_id`, `quantidade`        |
| PUT    | `/api/carrinho/itens/{id}`| Sim   | `quantidade` (0 remove o item)    |
| DELETE | `/api/carrinho/itens/{id}`| Sim   | — (remove um item)                |
| DELETE | `/api/carrinho`           | Sim   | — (esvazia o carrinho)            |

Os precos do carrinho sao sempre os do banco; o estoque e validado a cada
operacao.

## Checkout

| Metodo | Caminho                        | Auth? | Corpo / parametros                          |
| ------ | ------------------------------ | ----- | ------------------------------------------- |
| POST   | `/api/checkout/validar-entrega`| Nao   | `cidade`, `estado` (feedback rapido)        |
| POST   | `/api/checkout`                | Sim   | Dados de entrega (`nome`, `sobrenome`, `telefone`, `email`, `cep`, `rua`, `numero`, `bairro`, `complemento?`, `cidade`, `estado`, `observacoes?`) |

`POST /api/checkout` valida a entrega, cria o pedido transacionalmente a partir
do carrinho e retorna o pedido criado (status `AGUARDANDO_PAGAMENTO`).

## Pedidos (autenticado) — `/api/pedidos`

| Metodo | Caminho                          | Auth?  | Corpo / parametros                       |
| ------ | -------------------------------- | ------ | ---------------------------------------- |
| GET    | `/api/pedidos`                   | Sim    | Lista os pedidos do usuario.             |
| GET    | `/api/pedidos/codigo/{codigo}`   | Sim    | Busca por codigo (ex.: `PED-2026-000001`). |
| GET    | `/api/pedidos/{id}`              | Sim    | Detalhe do pedido.                       |
| PATCH  | `/api/pedidos/{id}/status`       | Admin  | `status` (transicao valida)              |

## Pagamentos (autenticado) — `/api/pagamentos`

| Metodo | Caminho                            | Auth? | Corpo / parametros                                  |
| ------ | ---------------------------------- | ----- | --------------------------------------------------- |
| POST   | `/api/pagamentos/pix`              | Sim   | `pedido_id`, `cpf?`                                 |
| POST   | `/api/pagamentos/cartao`           | Sim   | `pedido_id`, `token`, `payment_method_id`, `installments`, `issuer_id?`, `tipo` (`credito`/`debito`), `cpf` |
| POST   | `/api/pagamentos/boleto`           | Sim   | `pedido_id`, `cpf`                                  |
| GET    | `/api/pagamentos/{pedido_id}/status`| Sim  | Status do pagamento do pedido.                      |

Ver `Fluxo_Pagamentos.md` para os campos de resposta de cada metodo.

## Area do cliente (autenticado) — `/api/cliente`

| Metodo | Caminho                  | Auth? | Corpo / parametros            |
| ------ | ------------------------ | ----- | ----------------------------- |
| GET    | `/api/cliente/perfil`    | Sim   | Dados do perfil.              |
| PUT    | `/api/cliente/perfil`    | Sim   | `nome`, `sobrenome`, `telefone` |
| GET    | `/api/cliente/enderecos` | Sim   | Enderecos do usuario.         |
| GET    | `/api/cliente/historico` | Sim   | Historico de compras (pedidos efetivados). |

## Webhooks

| Metodo | Caminho                       | Auth?           | Descricao                                  |
| ------ | ----------------------------- | --------------- | ------------------------------------------ |
| POST   | `/api/webhooks/mercadopago`   | Assinatura HMAC | Notificacoes de pagamento do Mercado Pago. |

Rota publica, isenta de CSRF, autenticada pela assinatura `x-signature`
(HMAC-SHA256). Ver `Fluxo_Pagamentos.md`.

## Codigos de status HTTP

| Codigo | Uso                                                       |
| ------ | --------------------------------------------------------- |
| 200    | Sucesso.                                                  |
| 201    | Recurso criado.                                           |
| 401    | Nao autenticado.                                          |
| 403    | Autenticado, sem permissao (ex.: rota de admin).          |
| 404    | Recurso nao encontrado.                                   |
| 405    | Metodo nao permitido para a rota.                         |
| 419    | Token CSRF invalido ou ausente.                           |
| 422    | Falha de validacao (inclui restricao de entrega).         |
| 429    | Limite de requisicoes excedido (`Retry-After`).           |
| 500    | Erro interno.                                             |

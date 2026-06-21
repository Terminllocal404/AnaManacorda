# Fluxo de Pagamentos

Os pagamentos sao processados pelo **Mercado Pago**, usando o SDK oficial
`mercadopago/dx-php` (v3). Sao suportados PIX, cartao (credito e debito) e
boleto. A confirmacao do pagamento e feita de forma assincrona, via webhook.

> **Pre-requisitos de ambiente:** as credenciais do Mercado Pago
> (`MP_ACCESS_TOKEN`, `MP_PUBLIC_KEY`, `MP_WEBHOOK_SECRET`) e uma URL publica
> acessivel pelo Mercado Pago sao necessarias para o funcionamento de ponta a
> ponta. Ver `README.md`.

## Restricao geografica (antes do pagamento)

Antes de qualquer cobranca, o checkout valida o endereco de entrega. A loja
atende **exclusivamente Juiz de Fora (MG)**. Endereco fora dessa area e
bloqueado com a mensagem oficial:

```
No momento realizamos vendas apenas para Juiz de Fora (MG).
```

A verificacao normaliza acentuacao e caixa (ex.: "JUIZ DE FORA", "Juiz de
Fora" e "juiz de fora" sao equivalentes) e retorna HTTP 422 quando bloqueada.

## Metodos de pagamento

Todos os endpoints de pagamento exigem autenticacao e operam sobre um pedido ja
existente (criado no checkout) no status `AGUARDANDO_PAGAMENTO`.

### PIX — `POST /api/pagamentos/pix`

- Cria uma cobranca PIX no Mercado Pago para o valor total do pedido.
- Retorna os dados para pagamento: **codigo copia-e-cola** (`qr_code`),
  **QR Code em base64** (`qr_code_base64`), link do comprovante (`ticket_url`)
  e a data de expiracao (`expira_em`).
- E **idempotente por pedido**: se ja existe uma cobranca PIX pendente para o
  pedido, ela e reaproveitada em vez de criar outra.

### Cartao — `POST /api/pagamentos/cartao`

- O **token do cartao** e gerado no frontend (MercadoPago.js); o backend nunca
  recebe os dados sensiveis do cartao.
- Parametros principais: `token`, `payment_method_id`, `installments`
  (parcelas), `issuer_id` e o tipo (`credito`/`debito`), alem do e-mail e
  documento (CPF) do pagador.
- O numero de parcelas e validado (inteiro, de 1 a 24).
- O metodo registrado e `cartao_credito` ou `cartao_debito` conforme o tipo.

### Boleto — `POST /api/pagamentos/boleto`

- Gera um boleto (`bolbradesco`) com os dados do pagador (nome, sobrenome, CPF e
  endereco do pedido).
- Retorna a URL do boleto (`boleto_url`) para impressao/visualizacao.

## Estados do pagamento

O status do pagamento acompanha a nomenclatura do Mercado Pago:

| Status        | Significado                                  |
| ------------- | -------------------------------------------- |
| `pending`     | Aguardando pagamento (PIX/boleto emitido).   |
| `in_process`  | Em analise pelo gateway.                     |
| `approved`    | Pagamento aprovado.                          |
| `rejected`    | Pagamento recusado.                          |
| `cancelled`   | Cobranca cancelada/expirada.                 |

Quando o pagamento e **`approved`**, o pedido correspondente transita para
`PAGO` (de forma idempotente).

Consulta de status: `GET /api/pagamentos/{pedido_id}/status` (apenas o dono do
pedido).

## Webhook — `POST /api/webhooks/mercadopago`

O Mercado Pago notifica o backend a cada mudanca de pagamento.

1. A rota e **publica** (o Mercado Pago nao envia CSRF) e, por isso, e
   **isenta da protecao CSRF** no front controller.
2. A autenticidade e garantida pela **validacao da assinatura HMAC-SHA256**: o
   backend monta o *manifest* a partir do `id` do recurso, do cabecalho
   `x-request-id` e do timestamp `ts` extraido de `x-signature`, e compara o
   hash usando o `MP_WEBHOOK_SECRET`. Assinatura invalida e rejeitada **antes**
   de qualquer processamento.
3. Para notificacoes do tipo `payment`, o backend consulta o pagamento real no
   Mercado Pago (`PaymentClient::get`) para obter o status autoritativo — nunca
   confia apenas no corpo da notificacao.
4. Atualiza o registro do pagamento e, se aprovado, marca o pedido como `PAGO`.
5. Responde **HTTP 200** para confirmar o recebimento (evitando reenvios).

## Seguranca e integridade

- Valores **sempre** recalculados no servidor a partir do banco; o cliente nao
  define precos.
- Chave de **idempotencia** (`X-Idempotency-Key`) enviada ao Mercado Pago na
  criacao de pagamentos.
- A transicao do pedido para `PAGO` e idempotente: webhooks repetidos nao
  duplicam efeitos.
- Toda acao relevante de pagamento e registrada em `logs_sistema`.

# Fluxo de Pedidos

Este documento descreve o ciclo de vida de um pedido, da finalizacao do
checkout ate a conclusao da entrega.

## Geracao do codigo do pedido

Cada pedido recebe um codigo unico e legivel no formato:

```
PED-AAAA-NNNNNN
```

- `AAAA` — ano corrente (ex.: 2026).
- `NNNNNN` — sequencia incremental do ano, com 6 digitos e zeros a esquerda.

A sequencia e obtida lendo o **ultimo codigo do ano com lock** (`SELECT ... FOR
UPDATE`) dentro da transacao de criacao do pedido, evitando duplicidade em
requisicoes concorrentes. **Nao** se utiliza `rand`, `mt_rand`, `uniqid` ou
timestamp para compor o codigo. A logica pura de incremento esta isolada em
`CodigoService::proximoCodigo()` (coberta por testes).

Exemplos: primeiro pedido de 2026 -> `PED-2026-000001`; o seguinte ->
`PED-2026-000002`; apos `PED-2026-000999` -> `PED-2026-001000`.

## Criacao do pedido (transacional)

A finalizacao acontece em `PedidoService::criarPedido()`, em uma unica
transacao. Se qualquer etapa falhar, **tudo e revertido** (rollback):

1. Carrega o carrinho do usuario. Carrinho vazio resulta em erro 422.
2. Para cada item, relê o produto **com lock** (`lockPorId`) e revalida:
   - o produto existe e esta ativo;
   - ha estoque suficiente.
3. Usa sempre o **preco autoritativo do banco** (nunca o preco enviado pelo
   cliente). O subtotal de cada item e o total do pedido sao recalculados no
   servidor.
4. Baixa o estoque dos produtos.
5. Gera o codigo sequencial e cria o pedido com status inicial
   `AGUARDANDO_PAGAMENTO`, junto dos itens (snapshot de nome, preco e
   quantidade) e do endereco de entrega ja validado.
6. Registra o primeiro evento no historico de status.
7. Esvazia o carrinho.
8. Dispara o e-mail de confirmacao (o envio nunca derruba a transacao).

> A validacao geografica (somente Juiz de Fora/MG) ocorre **antes**, no
> checkout. Ver `Fluxo_Pagamentos.md` e `CheckoutService`.

## Maquina de estados

Sao exatamente seis status, percorridos de forma linear. Cada status so pode
avancar para o proximo:

```
AGUARDANDO_PAGAMENTO  ->  PAGO  ->  SEPARANDO  ->  PRONTO_PARA_ENTREGA  ->  ENTREGUE  ->  FINALIZADO
```

| De                     | Para permitido        |
| ---------------------- | --------------------- |
| `AGUARDANDO_PAGAMENTO` | `PAGO`                |
| `PAGO`                 | `SEPARANDO`           |
| `SEPARANDO`            | `PRONTO_PARA_ENTREGA` |
| `PRONTO_PARA_ENTREGA`  | `ENTREGUE`            |
| `ENTREGUE`             | `FINALIZADO`          |
| `FINALIZADO`           | (estado final)        |

Qualquer transicao fora dessa tabela e rejeitada. As transicoes validas estao
declaradas em `PedidoService::TRANSICOES`.

### Significado de cada status

- **AGUARDANDO_PAGAMENTO** — pedido criado; aguardando confirmacao do pagamento.
- **PAGO** — pagamento aprovado (via webhook do Mercado Pago ou confirmacao
  manual). A transicao para `PAGO` e **idempotente**: notificacoes repetidas do
  gateway nao reprocessam o pedido.
- **SEPARANDO** — itens em separacao no estoque.
- **PRONTO_PARA_ENTREGA** — pedido embalado, pronto para sair.
- **ENTREGUE** — entregue ao cliente.
- **FINALIZADO** — pedido concluido; nenhuma alteracao posterior.

## Quem altera o status

- A transicao para **PAGO** e disparada pela confirmacao de pagamento
  (webhook do Mercado Pago) ou por um administrador.
- As demais transicoes operacionais (`SEPARANDO` em diante) sao feitas por um
  **administrador**, via `PATCH /api/pedidos/{id}/status` (protegido por
  `AdminMiddleware`).

## Historico de status

Toda mudanca e registrada em `historico_status` com: status anterior, status
novo, usuario responsavel (quando aplicavel), observacao opcional e data/hora.
O historico acompanha o pedido nas consultas de detalhe.

## Consultas

- `GET /api/pedidos` — lista os pedidos do usuario autenticado.
- `GET /api/pedidos/{id}` — detalhe de um pedido do proprio usuario.
- `GET /api/pedidos/codigo/{codigo}` — busca pelo codigo (ex.: `PED-2026-000001`).

Em todos os casos, o usuario so acessa os proprios pedidos.

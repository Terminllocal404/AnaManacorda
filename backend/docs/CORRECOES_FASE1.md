# Correcoes — Fase 1

Registro das correcoes aplicadas conforme o documento "CORRECOES OBRIGATORIAS —
FASE 1 DO BACKEND". O painel administrativo permanece fora de escopo (Fase 2).

## Escopo confirmado

Fase 1 cobre exclusivamente a area publica e a area do cliente. Nenhum modulo
administrativo (dashboard, gestao de usuarios/produtos/pedidos, relatorios) foi
criado.

## Rastreabilidade (requisito -> arquivo -> mudanca)

### Seguranca

| Requisito | Arquivo | Mudanca |
| --- | --- | --- |
| Rate Limiter sem SHA1 (usar SHA-256) | `helpers/RateLimiter.php` | Derivacao da chave trocada de `sha1()` para `hash('sha256', ...)`. |
| Remover SHA1 remanescente | `services/EmailService.php` | Nome do arquivo `.eml` agora usa `hash('sha256', ...)`. |
| `session_regenerate_id(true)` apos login | `helpers/Auth.php` | Ja presente em `Auth::login()` (verificado). |
| Cabecalhos de seguranca globais | `middlewares/SecurityHeadersMiddleware.php` (novo), `public/index.php` | `SecurityHeadersMiddleware::cabecalhos()` define `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Content-Security-Policy`, `Permissions-Policy`; aplicado no front controller para todas as respostas (inclusive erros e preflight). |
| Upload seguro (whitelist + MIME real + tamanho) | `services/UploadService.php` (novo) | `validarMetadados()` valida extensao (jpg/jpeg/png/webp), bloqueia php/phtml/js/exe/bat/svg, exige MIME real permitido e tamanho maximo; `processar()` detecta o MIME pelo conteudo via `finfo` e gera nome no servidor. |
| Webhook MP: assinatura/idempotencia/replay | `services/MercadoPagoService.php`, `controllers/WebhookController.php`, `services/PagamentoService.php` | Assinatura HMAC-SHA256 com `hash_equals` (ja existente); adicionada `timestampValido()` (anti-replay por frescor do `ts`, com tolerancia); atualizacao do pedido idempotente via `PedidoService::marcarComoPago()`. |
| Carrinho validado no backend (estoque, qtd min/max, preco) | `services/CarrinhoService.php` | Adicionados `MAX_QUANTIDADE_ITEM` e `validarLimitesQuantidade()`; aplicados em `adicionar()` (incremento e total acumulado) e `atualizarQuantidade()`; estoque e preco ja vinham do banco. |
| Checkout: restricao geografica no backend | `services/CheckoutService.php` | Ja presente: `validarEntrega()` permite somente Juiz de Fora/MG com a mensagem oficial (verificado). |
| Recuperacao de senha: mensagem generica exata | `services/AuthService.php`, `controllers/AuthController.php` | Constante `MSG_RECUPERACAO_GENERICA` com o texto exato; `AuthController::esqueciSenha()` passa a usa-la. Validacoes de codigo invalido/expirado/reutilizado e senhas diferentes ja existentes. |
| Logs de eventos sem dados sensiveis | `services/AuthService.php`, `services/PedidoService.php`, `services/PagamentoService.php` | Eventos cobertos: `login`, `login_falha`, `logout`, `cadastro`, `verificacao_email`, `recuperacao_*`, `senha_*`, `pedido_criado`, `pedido_pago`, `pedido_status`, `webhook_processado`, erros (front controller). Nao se registram senhas, tokens nem dados de cartao. |

### Loja e Area do Cliente

| Requisito | Arquivo | Mudanca |
| --- | --- | --- |
| Produtos relacionados | `services/ProdutoService.php` | Ja entregue: `detalharPorSlug()` retorna `{produto, relacionados}` (ate 4 da mesma categoria) via `ProdutoRepository::relacionados()`. |
| Historico de Compras | `repositories/PedidoRepository.php`, `services/PedidoService.php`, `controllers/UsuarioController.php`, `routes/api.php` | Novo `historicoPorUsuario()` (pedidos efetivados, status diferente de `AGUARDANDO_PAGAMENTO`); `historicoDeCompras()`; `UsuarioController::historico()`; rota `GET /api/cliente/historico`. |

## Rotas novas

| Metodo | Caminho | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/api/cliente/historico` | Cliente | Historico de compras (pedidos efetivados). |

## Arquivos criados nesta fase

- `middlewares/SecurityHeadersMiddleware.php`
- `services/UploadService.php`
- `docs/CORRECOES_FASE1.md`

## Arquivos alterados nesta fase

- `helpers/RateLimiter.php`
- `services/EmailService.php`
- `public/index.php`
- `services/MercadoPagoService.php`
- `services/CarrinhoService.php`
- `services/AuthService.php`
- `controllers/AuthController.php`
- `repositories/PedidoRepository.php`
- `services/PedidoService.php`
- `controllers/UsuarioController.php`
- `routes/api.php`
- `tests/run.php`
- `README.md`
- `docs/Rotas.md`

## Validacao

- `php -l` em 69 arquivos PHP: 0 erros.
- `php tests/run.php`: 70/70 testes de dominio aprovados (50 anteriores + 20 das
  correcoes desta fase).
- Smoke de roteamento: 34/34 handlers de rota resolvidos; rotas registradas sem
  erro.

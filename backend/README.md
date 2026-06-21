# Ana Manacorda — Backend

Backend da loja virtual **Ana Manacorda**, em **PHP puro** (sem framework),
arquitetura MVC com camadas (Controller / Service / Repository), **API REST**
com respostas JSON, banco **MySQL** via **PDO** e integracao de pagamentos com
o **Mercado Pago** (PIX, cartao e boleto).

O frontend e desenvolvido separadamente; este projeto fornece apenas a API.

## Sumario da documentacao

- `docs/Banco.sql` — schema completo (14 tabelas) + seed inicial.
- `docs/Rotas.md` — referencia de todos os endpoints.
- `docs/Fluxo_Pedidos.md` — ciclo de vida e maquina de estados do pedido.
- `docs/Fluxo_Pagamentos.md` — pagamentos (Mercado Pago) e webhook.
- `docs/Estrutura_Projeto.md` — arquitetura, camadas e organizacao de pastas.

## Requisitos

- PHP **8.3+** com as extensoes: `pdo`, `pdo_mysql`, `mbstring`, `openssl`,
  `curl`, `json`.
- MySQL **8+**.
- Servidor web (Apache com `mod_rewrite`, ou Nginx) com a **webroot apontando
  para `public/`**.
- Composer (recomendado) para instalar as dependencias externas.

## Dependencias externas

Apenas duas, ambas oficiais e publicadas no Packagist:

- `mercadopago/dx-php` (^3.11) — SDK oficial do Mercado Pago.
- `phpmailer/phpmailer` (^6.9) — envio de e-mail por SMTP.

As classes internas (`App\*`) sao carregadas por um **autoloader PSR-4 proprio**
(`core/Autoload.php`) e funcionam **mesmo sem o Composer**. Porem, o envio de
e-mail SMTP e a integracao com o Mercado Pago **exigem** as bibliotecas acima
(portanto, exigem `vendor/`).

## Instalacao

1. **Dependencias:**
   ```bash
   composer install
   ```

2. **Configuracao de ambiente:** copie o modelo e preencha os valores reais.
   ```bash
   cp .env.example .env
   ```
   Variaveis principais:
   - `APP_URL` / `FRONTEND_URL` — URLs do backend e do frontend (CORS).
   - `DB_*` — conexao MySQL.
   - `MAIL_*` — SMTP. Use `MAIL_DRIVER=log` em desenvolvimento para gravar os
     e-mails como arquivos `.eml` em `storage/mail` (sem enviar de verdade).
   - `MP_ACCESS_TOKEN`, `MP_PUBLIC_KEY`, `MP_WEBHOOK_SECRET` — credenciais do
     Mercado Pago.
   - `ENTREGA_CIDADE` / `ENTREGA_ESTADO` — area de entrega (padrao Juiz de
     Fora/MG).

3. **Banco de dados:** crie o schema e a estrutura importando o script.
   ```bash
   mysql -u SEU_USUARIO -p < docs/Banco.sql
   ```
   O script cria o banco `ana_manacorda`, as 14 tabelas e um seed inicial
   (administrador + catalogo de exemplo). Ajuste o nome do banco no `.env`
   (`DB_DATABASE`) caso use outro.

4. **Webroot:** configure o `DocumentRoot` do servidor para a pasta `public/`.
   Em Apache, o `public/.htaccess` ja encaminha as requisicoes para o front
   controller. Caso o `DocumentRoot` aponte para a raiz do projeto, o
   `.htaccess` da raiz redireciona para `public/` (mas o ideal e apontar
   diretamente para `public/`).

5. **Permissoes de escrita:** garanta que `storage/`, `storage/cache/`,
   `storage/mail/` e `logs/` sejam graváveis pelo servidor web (cache do rate
   limit, e-mails em modo "log" e logs de fallback).

## Acesso administrativo (seed)

O `Banco.sql` cria um usuario administrador:

- **E-mail:** `admin@anamanacorda.com.br`
- **Senha:** `Admin@2026`

> Altere a senha apos o primeiro acesso (a senha e armazenada com
> `password_hash`/bcrypt).

## Webhook do Mercado Pago

Configure, no painel do Mercado Pago, a URL de notificacoes apontando para:

```
{APP_URL}/api/webhooks/mercadopago
```

A rota e publica e autenticada pela assinatura HMAC (`MP_WEBHOOK_SECRET`).
Para funcionar de ponta a ponta, o backend precisa estar acessivel
publicamente (em desenvolvimento, use um tunel HTTPS).

## Testes

O projeto inclui um runner proprio (sem dependencias externas) que cobre a
logica de dominio executavel sem banco de dados:

```bash
php tests/run.php
```

Sao verificados: o autoloader PSR-4, a sanitizacao/normalizacao, as regras de
validacao (incluindo CPF pelo algoritmo da Receita Federal), a **restricao de
entrega** (somente Juiz de Fora/MG, com a mensagem oficial) e a **geracao
sequencial** de codigos de pedido (`PED-AAAA-NNNNNN`). A saida indica o total de
testes aprovados; o processo encerra com codigo diferente de zero se algum
falhar.

## Seguranca

- PDO com **prepared statements** em todo acesso a dados.
- Senhas com `password_hash`/`password_verify` (bcrypt).
- Sessao com `session_regenerate_id(true)` apos o login (anti session fixation);
  cookies seguros (configuravel).
- Protecao **CSRF** (cabecalho `X-CSRF-Token`) em metodos que alteram estado.
- **Cabecalhos de seguranca** globais em todas as respostas: `X-Frame-Options`,
  `X-Content-Type-Options`, `Referrer-Policy`, `Content-Security-Policy` e
  `Permissions-Policy`.
- **Rate limiting** por IP, com derivacao de chave em **SHA-256** (sem SHA1 em
  componentes de seguranca).
- **Webhook do Mercado Pago** autenticado por **HMAC-SHA256** (`hash_equals`),
  com **protecao contra replay** (frescor do timestamp) e atualizacao de pedido
  **idempotente** (notificacoes duplicadas nao reprocessam).
- **Upload** restrito a imagens (jpg, jpeg, png, webp): validacao de extensao,
  **MIME real** detectado pelo conteudo (nunca apenas a extensao), bloqueio de
  tipos perigosos (php, phtml, js, exe, bat, svg) e limite de tamanho.
- **Carrinho** sempre validado no servidor: produto existente, estoque,
  quantidade minima e **maxima** por item; preco e total calculados no backend.
- Recuperacao de senha com **mensagem generica** (anti-enumeracao), codigo de 6
  digitos hasheado, expiracao, uso unico e limite de tentativas.
- Sanitizacao de entrada e respostas JSON (sem vazamento de stack trace quando
  `APP_DEBUG=false`).
- Restricao geografica de entrega aplicada no checkout.
- Auditoria de acoes relevantes em `logs_sistema` (login, logout, cadastro,
  recuperacao, criacao de pedido, atualizacao de pagamento, erros) — **sem**
  registrar senhas, tokens ou dados de cartao.

## Observacoes (ambiente)

Este backend e funcional e nao usa mocks. Para operar de ponta a ponta, alguns
recursos dependem de configuracao/infra **do seu ambiente** — nao de codigo:

- um servidor **MySQL** real e acessivel (com o `Banco.sql` importado);
- credenciais validas do **Mercado Pago** para processar pagamentos;
- um servidor **SMTP** (ou `MAIL_DRIVER=log`) para e-mails;
- uma **URL publica HTTPS** para o webhook do Mercado Pago.

Os testes automatizados de dominio (`php tests/run.php`) rodam sem nenhuma
dessas dependencias.

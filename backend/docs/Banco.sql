-- =============================================================================
--  Ana Manacorda - Banco de dados
--  MySQL 8+ / InnoDB / utf8mb4
--
--  Ordem de criacao respeita as dependencias de chave estrangeira.
--  Execute uma unica vez em um schema vazio. Para recriar do zero, derrube o
--  schema antes (DROP DATABASE) ou rode na ordem inversa os DROP TABLE.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Opcional: criacao do schema. Ajuste o nome conforme o .env (DB_DATABASE).
CREATE DATABASE IF NOT EXISTS `ana_manacorda`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;
USE `ana_manacorda`;

-- -----------------------------------------------------------------------------
-- 1. usuarios
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome`             VARCHAR(80)  NOT NULL,
    `sobrenome`        VARCHAR(80)  NOT NULL,
    `email`            VARCHAR(160) NOT NULL,
    `telefone`         VARCHAR(20)  NULL,
    `senha_hash`       VARCHAR(255) NOT NULL,
    `email_verificado` TINYINT(1)   NOT NULL DEFAULT 0,
    `role`             ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
    `status`           ENUM('pendente','ativo','bloqueado') NOT NULL DEFAULT 'pendente',
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_usuarios_email` (`email`),
    KEY `idx_usuarios_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. categorias
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome`       VARCHAR(120) NOT NULL,
    `slug`       VARCHAR(160) NOT NULL,
    `descricao`  TEXT NULL,
    `ativo`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categorias_slug` (`slug`),
    KEY `idx_categorias_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. produtos
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `produtos`;
CREATE TABLE `produtos` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `categoria_id` BIGINT UNSIGNED NULL,
    `nome`         VARCHAR(160) NOT NULL,
    `slug`         VARCHAR(200) NOT NULL,
    `descricao`    TEXT NULL,
    `preco`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estoque`      INT UNSIGNED NOT NULL DEFAULT 0,
    `ativo`        TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_produtos_slug` (`slug`),
    KEY `idx_produtos_categoria` (`categoria_id`),
    KEY `idx_produtos_ativo` (`ativo`),
    CONSTRAINT `fk_produtos_categoria`
        FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. produto_imagens
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `produto_imagens`;
CREATE TABLE `produto_imagens` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `produto_id` BIGINT UNSIGNED NOT NULL,
    `url`        VARCHAR(512) NOT NULL,
    `principal`  TINYINT(1) NOT NULL DEFAULT 0,
    `ordem`      INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_imagens_produto` (`produto_id`),
    CONSTRAINT `fk_imagens_produto`
        FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. enderecos
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `enderecos`;
CREATE TABLE `enderecos` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`  BIGINT UNSIGNED NOT NULL,
    `nome`        VARCHAR(80)  NOT NULL,
    `sobrenome`   VARCHAR(80)  NOT NULL,
    `telefone`    VARCHAR(20)  NOT NULL,
    `cep`         VARCHAR(9)   NOT NULL,
    `rua`         VARCHAR(160) NOT NULL,
    `numero`      VARCHAR(20)  NOT NULL,
    `bairro`      VARCHAR(120) NOT NULL,
    `complemento` VARCHAR(120) NULL,
    `cidade`      VARCHAR(120) NOT NULL,
    `estado`      CHAR(2)      NOT NULL,
    `observacoes` VARCHAR(500) NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_enderecos_usuario` (`usuario_id`),
    CONSTRAINT `fk_enderecos_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. carrinho  (um por usuario)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `carrinho`;
CREATE TABLE `carrinho` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_carrinho_usuario` (`usuario_id`),
    CONSTRAINT `fk_carrinho_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. carrinho_itens
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `carrinho_itens`;
CREATE TABLE `carrinho_itens` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `carrinho_id`    BIGINT UNSIGNED NOT NULL,
    `produto_id`     BIGINT UNSIGNED NOT NULL,
    `quantidade`     INT UNSIGNED NOT NULL DEFAULT 1,
    `preco_unitario` DECIMAL(10,2) NOT NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_item_carrinho_produto` (`carrinho_id`, `produto_id`),
    KEY `idx_itens_produto` (`produto_id`),
    CONSTRAINT `fk_itens_carrinho`
        FOREIGN KEY (`carrinho_id`) REFERENCES `carrinho` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_itens_produto`
        FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. pedidos
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pedidos`;
CREATE TABLE `pedidos` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo`      VARCHAR(20) NOT NULL,
    `usuario_id`  BIGINT UNSIGNED NOT NULL,
    `endereco_id` BIGINT UNSIGNED NOT NULL,
    `status`      ENUM(
                      'AGUARDANDO_PAGAMENTO',
                      'PAGO',
                      'SEPARANDO',
                      'PRONTO_PARA_ENTREGA',
                      'ENTREGUE',
                      'FINALIZADO'
                  ) NOT NULL DEFAULT 'AGUARDANDO_PAGAMENTO',
    `subtotal`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `observacoes` TEXT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pedidos_codigo` (`codigo`),
    KEY `idx_pedidos_usuario` (`usuario_id`),
    KEY `idx_pedidos_status` (`status`),
    KEY `idx_pedidos_endereco` (`endereco_id`),
    CONSTRAINT `fk_pedidos_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pedidos_endereco`
        FOREIGN KEY (`endereco_id`) REFERENCES `enderecos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. pedido_itens  (snapshot do produto no momento da compra)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pedido_itens`;
CREATE TABLE `pedido_itens` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pedido_id`      BIGINT UNSIGNED NOT NULL,
    `produto_id`     BIGINT UNSIGNED NOT NULL,
    `nome_produto`   VARCHAR(160) NOT NULL,
    `quantidade`     INT UNSIGNED NOT NULL,
    `preco_unitario` DECIMAL(10,2) NOT NULL,
    `subtotal`       DECIMAL(10,2) NOT NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pedido_itens_pedido` (`pedido_id`),
    KEY `idx_pedido_itens_produto` (`produto_id`),
    CONSTRAINT `fk_pedido_itens_pedido`
        FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pedido_itens_produto`
        FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. pagamentos
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pagamentos`;
CREATE TABLE `pagamentos` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pedido_id`          BIGINT UNSIGNED NOT NULL,
    `metodo`             VARCHAR(30) NOT NULL,
    `gateway`            VARCHAR(30) NOT NULL DEFAULT 'mercadopago',
    `gateway_payment_id` VARCHAR(64) NULL,
    `status`             VARCHAR(30) NOT NULL DEFAULT 'pending',
    `valor`              DECIMAL(10,2) NOT NULL,
    `qr_code`            TEXT NULL,
    `qr_code_base64`     LONGTEXT NULL,
    `ticket_url`         VARCHAR(512) NULL,
    `boleto_url`         VARCHAR(512) NULL,
    `linha_digitavel`    VARCHAR(120) NULL,
    `expira_em`          DATETIME NULL,
    `payload`            JSON NULL,
    `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pagamentos_pedido` (`pedido_id`),
    KEY `idx_pagamentos_gateway_id` (`gateway_payment_id`),
    KEY `idx_pagamentos_status` (`status`),
    CONSTRAINT `fk_pagamentos_pedido`
        FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. tokens_verificacao  (verificacao de e-mail)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tokens_verificacao`;
CREATE TABLE `tokens_verificacao` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` BIGINT UNSIGNED NOT NULL,
    `token`      VARCHAR(64) NOT NULL,
    `expira_em`  DATETIME NOT NULL,
    `usado`      TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tokens_verificacao_token` (`token`),
    KEY `idx_tokens_verificacao_usuario` (`usuario_id`),
    CONSTRAINT `fk_tokens_verificacao_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. tokens_recuperacao  (recuperacao de senha - codigo de 6 digitos, hasheado)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tokens_recuperacao`;
CREATE TABLE `tokens_recuperacao` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`  BIGINT UNSIGNED NOT NULL,
    `codigo_hash` VARCHAR(255) NOT NULL,
    `expira_em`   DATETIME NOT NULL,
    `tentativas`  INT UNSIGNED NOT NULL DEFAULT 0,
    `usado`       TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tokens_recuperacao_usuario` (`usuario_id`),
    CONSTRAINT `fk_tokens_recuperacao_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 13. historico_status  (trilha de mudancas de status do pedido)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `historico_status`;
CREATE TABLE `historico_status` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pedido_id`       BIGINT UNSIGNED NOT NULL,
    `status_anterior` VARCHAR(30) NULL,
    `status_novo`     VARCHAR(30) NOT NULL,
    `usuario_id`      BIGINT UNSIGNED NULL,
    `observacao`      VARCHAR(255) NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_historico_pedido` (`pedido_id`),
    KEY `idx_historico_usuario` (`usuario_id`),
    CONSTRAINT `fk_historico_pedido`
        FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_historico_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 14. logs_sistema  (auditoria: usuario, acao, ip, contexto)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `logs_sistema`;
CREATE TABLE `logs_sistema` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` BIGINT UNSIGNED NULL,
    `acao`       VARCHAR(80) NOT NULL,
    `nivel`      VARCHAR(20) NOT NULL DEFAULT 'info',
    `descricao`  VARCHAR(255) NULL,
    `ip`         VARCHAR(45) NOT NULL,
    `contexto`   JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_usuario` (`usuario_id`),
    KEY `idx_logs_acao` (`acao`),
    KEY `idx_logs_nivel` (`nivel`),
    CONSTRAINT `fk_logs_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
--  SEED INICIAL
-- =============================================================================

-- Usuario administrador.
-- Credenciais de acesso (ALTERE A SENHA APOS O PRIMEIRO LOGIN):
--   e-mail: admin@anamanacorda.com.br
--   senha:  Admin@2026
-- O hash abaixo foi gerado com password_hash(..., PASSWORD_BCRYPT).
INSERT INTO `usuarios`
    (`nome`, `sobrenome`, `email`, `telefone`, `senha_hash`, `email_verificado`, `role`, `status`)
VALUES
    ('Ana', 'Manacorda', 'admin@anamanacorda.com.br', NULL,
     '$2y$10$IcmVg1JMJuAmqDLP6l27meEcb6jJF4yF5GbNxt9HWJFkhhALrxuti',
     1, 'admin', 'ativo');

-- Categorias.
INSERT INTO `categorias` (`nome`, `slug`, `descricao`, `ativo`) VALUES
    ('Vestidos',        'vestidos',        'Vestidos para o dia a dia e ocasioes especiais.', 1),
    ('Blusas',          'blusas',          'Blusas, camisas e regatas.',                      1),
    ('Saias e Calcas',  'saias-e-calcas',  'Saias, calcas e shorts.',                         1),
    ('Acessorios',      'acessorios',      'Bolsas, cintos e bijuterias.',                    1),
    ('Calcados',        'calcados',        'Sapatos, sandalias e tenis.',                     1);

-- Produtos (categoria_id segue a ordem de insercao das categorias acima).
INSERT INTO `produtos` (`categoria_id`, `nome`, `slug`, `descricao`, `preco`, `estoque`, `ativo`) VALUES
    (1, 'Vestido Midi Floral',        'vestido-midi-floral',        'Vestido midi em viscose com estampa floral e manga curta.', 199.90, 15, 1),
    (1, 'Vestido Longo Liso',         'vestido-longo-liso',         'Vestido longo liso com fenda lateral, ideal para festas.',  289.90,  8, 1),
    (2, 'Blusa de Linho Off-White',   'blusa-de-linho-off-white',   'Blusa de linho leve, gola redonda, cor off-white.',          129.90, 25, 1),
    (2, 'Camisa Social Branca',       'camisa-social-branca',       'Camisa social de algodao com caimento reto.',               149.90, 20, 1),
    (3, 'Calca Pantalona Preta',      'calca-pantalona-preta',      'Calca pantalona de alfaiataria, cintura alta.',             179.90, 12, 1),
    (3, 'Saia Midi Plissada',         'saia-midi-plissada',         'Saia midi plissada com elastico na cintura.',               139.90, 18, 1),
    (4, 'Bolsa Transversal Caramelo', 'bolsa-transversal-caramelo', 'Bolsa transversal em couro sintetico, cor caramelo.',       159.90, 10, 1),
    (4, 'Cinto de Couro Marrom',      'cinto-de-couro-marrom',      'Cinto de couro legitimo com fivela metalica.',               79.90, 30, 1),
    (5, 'Sandalia Salto Bloco',       'sandalia-salto-bloco',       'Sandalia de salto bloco confortavel, cor nude.',            169.90, 14, 1),
    (5, 'Tenis Casual Branco',        'tenis-casual-branco',        'Tenis casual branco em couro ecologico.',                   219.90, 16, 1);

-- Imagens dos produtos.
-- IMPORTANTE: os caminhos abaixo sao referencias de exemplo. Faca o upload das
-- imagens reais para /public/uploads (ou ajuste a URL para sua CDN) e atualize
-- os registros conforme necessario. Os arquivos de imagem nao acompanham o seed.
INSERT INTO `produto_imagens` (`produto_id`, `url`, `principal`, `ordem`) VALUES
    (1,  '/uploads/produtos/vestido-midi-floral.jpg',        1, 0),
    (2,  '/uploads/produtos/vestido-longo-liso.jpg',         1, 0),
    (3,  '/uploads/produtos/blusa-de-linho-off-white.jpg',   1, 0),
    (4,  '/uploads/produtos/camisa-social-branca.jpg',       1, 0),
    (5,  '/uploads/produtos/calca-pantalona-preta.jpg',      1, 0),
    (6,  '/uploads/produtos/saia-midi-plissada.jpg',         1, 0),
    (7,  '/uploads/produtos/bolsa-transversal-caramelo.jpg', 1, 0),
    (8,  '/uploads/produtos/cinto-de-couro-marrom.jpg',      1, 0),
    (9,  '/uploads/produtos/sandalia-salto-bloco.jpg',       1, 0),
    (10, '/uploads/produtos/tenis-casual-branco.jpg',        1, 0);

-- =============================================================================
--  FIM
-- =============================================================================

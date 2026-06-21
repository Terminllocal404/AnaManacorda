<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CarrinhoController;
use App\Controllers\CheckoutController;
use App\Controllers\PagamentoController;
use App\Controllers\PedidoController;
use App\Controllers\ProdutoController;
use App\Controllers\UsuarioController;
use App\Controllers\WebhookController;
use App\Core\Router;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\AuthMiddleware;

/**
 * Definicao das rotas da API. Recebe o Router ja instanciado.
 *
 * Observacao sobre ordem: rotas especificas (ex.: /produtos/busca) sao
 * registradas antes das rotas com parametro (ex.: /produtos/{slug}), pois
 * o roteador retorna a primeira correspondencia.
 */
return static function (Router $router): void {
    $router->group('/api', [], static function (Router $r): void {

        // ---------------- Utilitario ----------------
        $r->get('/csrf-token', [AuthController::class, 'csrf']);

        // ---------------- Autenticacao (publico) ----------------
        $r->group('/auth', [], static function (Router $a): void {
            $a->post('/registrar', [AuthController::class, 'registrar']);
            $a->get('/verificar-email', [AuthController::class, 'verificarEmail']);
            $a->post('/login', [AuthController::class, 'login']);
            $a->post('/esqueci-senha', [AuthController::class, 'esqueciSenha']);
            $a->post('/confirmar-codigo', [AuthController::class, 'confirmarCodigo']);
            $a->post('/redefinir-senha', [AuthController::class, 'redefinirSenha']);

            // Autenticado
            $a->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);
            $a->get('/eu', [AuthController::class, 'eu'], [AuthMiddleware::class]);
            $a->post('/alterar-senha', [AuthController::class, 'alterarSenha'], [AuthMiddleware::class]);
        });

        // ---------------- Catalogo (publico) ----------------
        $r->get('/categorias', [ProdutoController::class, 'categorias']);
        $r->get('/produtos/busca', [ProdutoController::class, 'buscar']);
        $r->get('/produtos', [ProdutoController::class, 'listar']);
        $r->get('/produtos/{slug}', [ProdutoController::class, 'detalhar']);

        // ---------------- Carrinho (autenticado) ----------------
        $r->group('/carrinho', [AuthMiddleware::class], static function (Router $c): void {
            $c->get('', [CarrinhoController::class, 'obter']);
            $c->post('/itens', [CarrinhoController::class, 'adicionar']);
            $c->put('/itens/{id}', [CarrinhoController::class, 'atualizar']);
            $c->delete('/itens/{id}', [CarrinhoController::class, 'remover']);
            $c->delete('', [CarrinhoController::class, 'limpar']);
        });

        // ---------------- Checkout ----------------
        // Validacao de entrega e publica (feedback rapido no frontend).
        $r->post('/checkout/validar-entrega', [CheckoutController::class, 'validarEntrega']);
        // Finalizar exige autenticacao.
        $r->post('/checkout', [CheckoutController::class, 'finalizar'], [AuthMiddleware::class]);

        // ---------------- Pedidos (autenticado) ----------------
        $r->group('/pedidos', [AuthMiddleware::class], static function (Router $p): void {
            $p->get('', [PedidoController::class, 'listar']);
            $p->get('/codigo/{codigo}', [PedidoController::class, 'obterPorCodigo']);
            $p->get('/{id}', [PedidoController::class, 'obter']);
            // Atualizacao de status: somente administradores.
            $p->patch('/{id}/status', [PedidoController::class, 'atualizarStatus'], [AdminMiddleware::class]);
        });

        // ---------------- Pagamentos (autenticado) ----------------
        $r->group('/pagamentos', [AuthMiddleware::class], static function (Router $pg): void {
            $pg->post('/pix', [PagamentoController::class, 'pix']);
            $pg->post('/cartao', [PagamentoController::class, 'cartao']);
            $pg->post('/boleto', [PagamentoController::class, 'boleto']);
            $pg->get('/{pedido_id}/status', [PagamentoController::class, 'status']);
        });

        // ---------------- Area do cliente (autenticado) ----------------
        $r->group('/cliente', [AuthMiddleware::class], static function (Router $u): void {
            $u->get('/perfil', [UsuarioController::class, 'perfil']);
            $u->put('/perfil', [UsuarioController::class, 'atualizarPerfil']);
            $u->get('/enderecos', [UsuarioController::class, 'enderecos']);
            $u->get('/historico', [UsuarioController::class, 'historico']);
        });

        // ---------------- Webhooks (publico, validado por assinatura) ----------------
        $r->post('/webhooks/mercadopago', [WebhookController::class, 'mercadopago']);
    });
};

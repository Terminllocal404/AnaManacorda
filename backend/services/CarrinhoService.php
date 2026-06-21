<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Models\Carrinho;
use App\Repositories\CarrinhoRepository;
use App\Repositories\ProdutoRepository;

/**
 * Regras do carrinho persistente. Os precos sao sempre obtidos do banco
 * (nunca do cliente) e o estoque e validado a cada operacao.
 */
final class CarrinhoService
{
    /** Quantidade maxima permitida por item no carrinho. */
    public const MAX_QUANTIDADE_ITEM = 99;

    /**
     * Valida os limites de quantidade de um item: minimo 1, maximo
     * MAX_QUANTIDADE_ITEM. Logica pura, adequada para testes.
     *
     * @throws ValidationException
     */
    public static function validarLimitesQuantidade(int $quantidade): void
    {
        if ($quantidade <= 0) {
            throw new ValidationException(['quantidade' => ['A quantidade deve ser maior que zero.']]);
        }
        if ($quantidade > self::MAX_QUANTIDADE_ITEM) {
            throw new ValidationException([
                'quantidade' => ['Quantidade maxima por item: ' . self::MAX_QUANTIDADE_ITEM . '.'],
            ]);
        }
    }

    public function __construct(
        private CarrinhoRepository $carrinhos = new CarrinhoRepository(),
        private ProdutoRepository $produtos = new ProdutoRepository()
    ) {
    }

    /** @return array<string,mixed> */
    public function obter(int $usuarioId): array
    {
        $carrinho = $this->carrinhos->obterOuCriar($usuarioId);
        if ($carrinho->id !== null) {
            $carrinho->itens = $this->carrinhos->itens($carrinho->id);
            $carrinho->recalcular();
        }
        return $carrinho->toArray();
    }

    /**
     * Adiciona um produto ao carrinho (ou soma quantidade se ja existir).
     *
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    public function adicionar(int $usuarioId, array $dados): array
    {
        $produtoId  = (int) ($dados['produto_id'] ?? 0);
        $quantidade = (int) ($dados['quantidade'] ?? 1);

        if ($produtoId <= 0) {
            throw new ValidationException(['produto_id' => ['Produto invalido.']]);
        }
        self::validarLimitesQuantidade($quantidade);

        $produto = $this->produtos->buscarPorId($produtoId, true);
        if ($produto === null) {
            throw new HttpException('Produto nao encontrado ou indisponivel.', 404);
        }

        $carrinho = $this->carrinhos->obterOuCriar($usuarioId);
        $existente = $carrinho->id !== null
            ? $this->carrinhos->itemPorProduto($carrinho->id, $produtoId)
            : null;

        $quantidadeFinal = $quantidade + ($existente->quantidade ?? 0);
        // O total acumulado do item tambem respeita o teto maximo.
        self::validarLimitesQuantidade($quantidadeFinal);
        if ($quantidadeFinal > $produto->estoque) {
            throw new ValidationException([
                'quantidade' => ['Estoque insuficiente. Disponivel: ' . $produto->estoque . '.'],
            ]);
        }

        if ($existente !== null && $existente->id !== null) {
            $this->carrinhos->atualizarQuantidade($existente->id, $quantidadeFinal);
            $this->carrinhos->atualizarPreco($existente->id, $produto->preco);
        } else {
            /** @var int $carrinhoId */
            $carrinhoId = $carrinho->id;
            $this->carrinhos->adicionarItem($carrinhoId, $produtoId, $quantidade, $produto->preco);
        }

        if ($carrinho->id !== null) {
            $this->carrinhos->touch($carrinho->id);
        }

        return $this->obter($usuarioId);
    }

    /**
     * Atualiza a quantidade de um item. Quantidade 0 remove o item.
     *
     * @return array<string,mixed>
     */
    public function atualizarQuantidade(int $usuarioId, int $itemId, int $quantidade): array
    {
        if (!$this->carrinhos->itemPertenceAoUsuario($itemId, $usuarioId)) {
            throw new HttpException('Item nao encontrado no carrinho.', 404);
        }

        if ($quantidade <= 0) {
            $this->carrinhos->removerItem($itemId);
            return $this->obter($usuarioId);
        }

        // Quantidade positiva: respeita o teto maximo por item.
        self::validarLimitesQuantidade($quantidade);

        $carrinho = $this->carrinhos->buscarPorUsuario($usuarioId);
        $item     = null;
        foreach ($carrinho?->itens ?? [] as $i) {
            if ($i->id === $itemId) {
                $item = $i;
                break;
            }
        }
        if ($item === null) {
            throw new HttpException('Item nao encontrado no carrinho.', 404);
        }

        $produto = $this->produtos->buscarPorId($item->produto_id, true);
        if ($produto === null) {
            $this->carrinhos->removerItem($itemId);
            throw new HttpException('Produto indisponivel e removido do carrinho.', 409);
        }

        if ($quantidade > $produto->estoque) {
            throw new ValidationException([
                'quantidade' => ['Estoque insuficiente. Disponivel: ' . $produto->estoque . '.'],
            ]);
        }

        $this->carrinhos->atualizarQuantidade($itemId, $quantidade);
        $this->carrinhos->atualizarPreco($itemId, $produto->preco);

        return $this->obter($usuarioId);
    }

    /** @return array<string,mixed> */
    public function remover(int $usuarioId, int $itemId): array
    {
        if (!$this->carrinhos->itemPertenceAoUsuario($itemId, $usuarioId)) {
            throw new HttpException('Item nao encontrado no carrinho.', 404);
        }
        $this->carrinhos->removerItem($itemId);
        return $this->obter($usuarioId);
    }

    /** @return array<string,mixed> */
    public function limpar(int $usuarioId): array
    {
        $carrinho = $this->carrinhos->obterOuCriar($usuarioId);
        if ($carrinho->id !== null) {
            $this->carrinhos->limpar($carrinho->id);
        }
        return $this->obter($usuarioId);
    }

    /** Retorna o modelo do carrinho com itens recalculados (uso interno do checkout). */
    public function modelo(int $usuarioId): Carrinho
    {
        $carrinho = $this->carrinhos->obterOuCriar($usuarioId);
        if ($carrinho->id !== null) {
            $carrinho->itens = $this->carrinhos->itens($carrinho->id);
            $carrinho->recalcular();
        }
        return $carrinho;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\CategoriaRepository;
use App\Repositories\ProdutoRepository;

/**
 * Regras de consulta de catalogo: listagem, detalhes, busca e categorias.
 */
final class ProdutoService
{
    public function __construct(
        private ProdutoRepository $produtos = new ProdutoRepository(),
        private CategoriaRepository $categorias = new CategoriaRepository()
    ) {
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function listar(array $filtros): array
    {
        $resultado = $this->produtos->listar([
            'categoria_id'   => isset($filtros['categoria_id']) ? (int) $filtros['categoria_id'] : null,
            'categoria_slug' => isset($filtros['categoria']) ? (string) $filtros['categoria'] : null,
            'busca'          => isset($filtros['busca']) ? trim((string) $filtros['busca']) : null,
            'pagina'         => isset($filtros['pagina']) ? (int) $filtros['pagina'] : 1,
            'por_pagina'     => isset($filtros['por_pagina']) ? (int) $filtros['por_pagina'] : 12,
            'somente_ativos' => true,
        ]);

        return [
            'produtos'  => array_map(static fn ($p) => $p->toArray(), $resultado['itens']),
            'paginacao' => [
                'pagina'        => $resultado['pagina'],
                'por_pagina'    => $resultado['por_pagina'],
                'total'         => $resultado['total'],
                'total_paginas' => $resultado['total_paginas'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function detalharPorSlug(string $slug): array
    {
        $produto = $this->produtos->buscarPorSlug($slug, true);
        if ($produto === null) {
            throw new HttpException('Produto nao encontrado.', 404);
        }

        $relacionados = [];
        if ($produto->id !== null && $produto->categoria_id !== null) {
            $relacionados = array_map(
                static fn ($p) => $p->toArray(),
                $this->produtos->relacionados($produto->id, $produto->categoria_id, 4)
            );
        }

        return [
            'produto'      => $produto->toArray(),
            'relacionados' => $relacionados,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function listarCategorias(): array
    {
        return array_map(static fn ($c) => $c->toArray(), $this->categorias->listar(true));
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function buscar(string $termo, array $filtros = []): array
    {
        $filtros['busca'] = $termo;
        return $this->listar($filtros);
    }
}

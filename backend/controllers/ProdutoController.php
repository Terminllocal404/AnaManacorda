<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\ProdutoService;

final class ProdutoController extends Controller
{
    public function __construct(
        private ProdutoService $produtos = new ProdutoService()
    ) {
    }

    public function listar(Request $request): void
    {
        $dados = $this->produtos->listar([
            'categoria'    => $request->query('categoria'),
            'categoria_id' => $request->query('categoria_id'),
            'busca'        => $request->query('busca'),
            'pagina'       => $request->query('pagina'),
            'por_pagina'   => $request->query('por_pagina'),
        ]);
        $this->success('Produtos listados.', $dados);
    }

    public function detalhar(Request $request): void
    {
        $slug  = (string) $request->param('slug');
        $dados = $this->produtos->detalharPorSlug($slug);
        $this->success('Detalhes do produto.', $dados);
    }

    public function categorias(Request $request): void
    {
        $this->success('Categorias listadas.', ['categorias' => $this->produtos->listarCategorias()]);
    }

    public function buscar(Request $request): void
    {
        $termo = (string) ($request->query('q') ?? $request->query('busca') ?? '');
        $dados = $this->produtos->buscar($termo, [
            'pagina'     => $request->query('pagina'),
            'por_pagina' => $request->query('por_pagina'),
        ]);
        $this->success('Resultado da busca.', $dados);
    }
}

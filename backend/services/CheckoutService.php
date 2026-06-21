<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\Validator;
use App\Helpers\Security;

/**
 * Regras de checkout: validacao dos dados de entrega e restricao geografica.
 *
 * A entrega e permitida exclusivamente para Juiz de Fora (MG). Qualquer
 * outra cidade/estado e bloqueada com a mensagem oficial.
 */
final class CheckoutService
{
    public const MSG_ENTREGA_BLOQUEADA = 'No momento realizamos vendas apenas para Juiz de Fora (MG).';

    /** @var array<string,mixed> */
    private array $entrega;

    public function __construct(?array $configEntrega = null)
    {
        $this->entrega = $configEntrega ?? (array) config('app.entrega');
    }

    /**
     * Valida os dados de entrega informados no checkout.
     *
     * @param array<string,mixed> $dados
     * @return array<string,mixed> dados normalizados do endereco
     */
    public function validarDadosEntrega(array $dados): array
    {
        Validator::make($dados, [
            'nome'        => 'required|string|max:80',
            'sobrenome'   => 'required|string|max:80',
            'telefone'    => 'required|telefone',
            'email'       => 'required|email|max:160',
            'cep'         => 'required|cep',
            'rua'         => 'required|string|max:160',
            'numero'      => 'required|string|max:20',
            'bairro'      => 'required|string|max:120',
            'complemento' => 'string|max:120',
            'cidade'      => 'required|string|max:120',
            'estado'      => 'required|string|max:2',
            'observacoes' => 'string|max:500',
        ], [
            'nome'      => 'nome',
            'sobrenome' => 'sobrenome',
            'telefone'  => 'telefone',
            'email'     => 'e-mail',
            'cep'       => 'CEP',
            'rua'       => 'rua',
            'numero'    => 'numero',
            'bairro'    => 'bairro',
            'cidade'    => 'cidade',
            'estado'    => 'estado',
        ])->validate();

        $this->validarEntrega((string) $dados['cidade'], (string) $dados['estado']);

        return [
            'nome'        => Security::stripTags((string) $dados['nome']),
            'sobrenome'   => Security::stripTags((string) $dados['sobrenome']),
            'telefone'    => preg_replace('/\D/', '', (string) $dados['telefone']) ?? '',
            'email'       => strtolower(trim((string) $dados['email'])),
            'cep'         => preg_replace('/\D/', '', (string) $dados['cep']) ?? '',
            'rua'         => Security::stripTags((string) $dados['rua']),
            'numero'      => Security::stripTags((string) $dados['numero']),
            'bairro'      => Security::stripTags((string) $dados['bairro']),
            'complemento' => isset($dados['complemento']) ? Security::stripTags((string) $dados['complemento']) : null,
            'cidade'      => Security::stripTags((string) $dados['cidade']),
            'estado'      => strtoupper(Security::stripTags((string) $dados['estado'])),
            'observacoes' => isset($dados['observacoes']) ? Security::stripTags((string) $dados['observacoes']) : null,
        ];
    }

    /**
     * Verifica a restricao geografica. Lanca HttpException 422 com a mensagem
     * oficial caso a cidade/estado nao seja a area atendida.
     */
    public function validarEntrega(string $cidade, string $estado): void
    {
        $cidadePermitida = Security::normalize((string) ($this->entrega['cidade'] ?? 'Juiz de Fora'));
        $estadoPermitido = Security::normalize((string) ($this->entrega['estado'] ?? 'MG'));

        $cidadeInformada = Security::normalize($cidade);
        $estadoInformado = Security::normalize($estado);

        if ($cidadeInformada !== $cidadePermitida || $estadoInformado !== $estadoPermitido) {
            throw new HttpException(self::MSG_ENTREGA_BLOQUEADA, 422);
        }
    }
}

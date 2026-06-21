<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use RuntimeException;

/**
 * Gateway de pagamento sobre o SDK oficial do Mercado Pago (mercadopago/dx-php v3).
 *
 * Responsavel exclusivamente pela comunicacao com a API do Mercado Pago:
 * criar pagamentos (PIX, cartao de credito/debito, boleto), consultar um
 * pagamento e validar a assinatura dos webhooks. A logica de dominio
 * (atualizacao de pedidos, persistencia) fica nas camadas de servico/pedido.
 */
final class MercadoPagoService
{
    /** Tolerancia (em segundos) para o timestamp do webhook, contra replay attack. */
    public const TOLERANCIA_WEBHOOK_SEGUNDOS = 300;

    private bool $configurado = false;

    /** @var array<string,mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('mercadopago');
    }

    private function configurar(): void
    {
        if ($this->configurado) {
            return;
        }

        $token = (string) ($this->config['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('MP_ACCESS_TOKEN nao configurado.');
        }

        MercadoPagoConfig::setAccessToken($token);
        $runtime = strtoupper((string) ($this->config['runtime'] ?? 'SERVER'));
        MercadoPagoConfig::setRuntimeEnviroment(
            $runtime === 'LOCAL'
                ? MercadoPagoConfig::LOCAL
                : MercadoPagoConfig::SERVER
        );

        $this->configurado = true;
    }

    private function client(): PaymentClient
    {
        $this->configurar();
        return new PaymentClient();
    }

    private function requestOptions(string $idempotencyKey): RequestOptions
    {
        $options = new RequestOptions();
        $options->setCustomHeaders(['X-Idempotency-Key: ' . $idempotencyKey]);
        return $options;
    }

    /**
     * Cria um pagamento PIX.
     *
     * @param array{valor:float,email:string,descricao:string,referencia:string,nome?:string,sobrenome?:string,cpf?:string} $dados
     * @return array<string,mixed>
     */
    public function criarPagamentoPix(array $dados): array
    {
        $minutos = (int) ($this->config['pix_expiration_minutes'] ?? 30);
        $expira  = (new \DateTimeImmutable('+' . $minutos . ' minutes'))->format('Y-m-d\TH:i:s.000P');

        $payer = ['email' => $dados['email']];
        if (!empty($dados['nome'])) {
            $payer['first_name'] = $dados['nome'];
        }
        if (!empty($dados['sobrenome'])) {
            $payer['last_name'] = $dados['sobrenome'];
        }
        if (!empty($dados['cpf'])) {
            $payer['identification'] = ['type' => 'CPF', 'number' => $dados['cpf']];
        }

        $request = [
            'transaction_amount' => round((float) $dados['valor'], 2),
            'description'        => $dados['descricao'],
            'payment_method_id'  => 'pix',
            'date_of_expiration' => $expira,
            'external_reference' => $dados['referencia'],
            'notification_url'   => (string) $this->config['notification_url'],
            'payer'              => $payer,
        ];

        return $this->executarCriacao($request, $dados['referencia'] . '-pix');
    }

    /**
     * Cria um pagamento com cartao (credito ou debito) a partir do token gerado no frontend.
     *
     * @param array{token:string,valor:float,email:string,descricao:string,referencia:string,parcelas?:int,payment_method_id:string,issuer_id?:string,cpf?:string} $dados
     * @return array<string,mixed>
     */
    public function criarPagamentoCartao(array $dados): array
    {
        $payer = ['email' => $dados['email']];
        if (!empty($dados['cpf'])) {
            $payer['identification'] = ['type' => 'CPF', 'number' => $dados['cpf']];
        }

        $request = [
            'transaction_amount' => round((float) $dados['valor'], 2),
            'token'              => $dados['token'],
            'description'        => $dados['descricao'],
            'installments'       => (int) ($dados['parcelas'] ?? 1),
            'payment_method_id'  => $dados['payment_method_id'],
            'external_reference' => $dados['referencia'],
            'notification_url'   => (string) $this->config['notification_url'],
            'payer'              => $payer,
        ];

        if (!empty($dados['issuer_id'])) {
            $request['issuer_id'] = $dados['issuer_id'];
        }

        return $this->executarCriacao($request, $dados['referencia'] . '-card');
    }

    /**
     * Cria um pagamento por boleto bancario.
     *
     * @param array{valor:float,email:string,descricao:string,referencia:string,nome:string,sobrenome:string,cpf:string,cep:string,rua:string,numero:string,bairro:string,cidade:string,estado:string} $dados
     * @return array<string,mixed>
     */
    public function criarPagamentoBoleto(array $dados): array
    {
        $request = [
            'transaction_amount' => round((float) $dados['valor'], 2),
            'description'        => $dados['descricao'],
            'payment_method_id'  => 'bolbradesco',
            'external_reference' => $dados['referencia'],
            'notification_url'   => (string) $this->config['notification_url'],
            'payer'              => [
                'email'          => $dados['email'],
                'first_name'     => $dados['nome'],
                'last_name'      => $dados['sobrenome'],
                'identification' => ['type' => 'CPF', 'number' => $dados['cpf']],
                'address'        => [
                    'zip_code'      => $dados['cep'],
                    'street_name'   => $dados['rua'],
                    'street_number' => $dados['numero'],
                    'neighborhood'  => $dados['bairro'],
                    'city'          => $dados['cidade'],
                    'federal_unit'  => $dados['estado'],
                ],
            ],
        ];

        return $this->executarCriacao($request, $dados['referencia'] . '-boleto');
    }

    /**
     * Consulta um pagamento pelo id do gateway. Retorna o status e dados normalizados.
     *
     * @return array<string,mixed>
     */
    public function obterPagamento(string $gatewayPaymentId): array
    {
        try {
            $payment = $this->client()->get((int) $gatewayPaymentId);
            return $this->normalizar($payment);
        } catch (MPApiException $e) {
            throw $this->traduzirErro($e);
        }
    }

    /**
     * Valida a assinatura HMAC de um webhook do Mercado Pago.
     *
     * O cabecalho x-signature traz "ts=<timestamp>,v1=<hash>". O manifest
     * assinado e "id:<dataId>;request-id:<xRequestId>;ts:<ts>;" e o hash e
     * HMAC-SHA256 usando o webhook secret.
     */
    public function validarAssinaturaWebhook(string $xSignature, string $xRequestId, string $dataId): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        if ($secret === '') {
            // Sem secret configurado nao ha como validar com seguranca.
            return false;
        }

        $ts = null;
        $v1 = null;
        foreach (explode(',', $xSignature) as $parte) {
            $kv = explode('=', trim($parte), 2);
            if (count($kv) !== 2) {
                continue;
            }
            [$chave, $valor] = $kv;
            $chave = trim($chave);
            if ($chave === 'ts') {
                $ts = trim($valor);
            } elseif ($chave === 'v1') {
                $v1 = trim($valor);
            }
        }

        if ($ts === null || $v1 === null) {
            return false;
        }

        // Protecao contra replay: rejeita notificacoes com timestamp fora da
        // janela de tolerancia.
        if (!self::timestampValido($ts)) {
            return false;
        }

        $manifest  = sprintf('id:%s;request-id:%s;ts:%s;', strtolower($dataId), $xRequestId, $ts);
        $calculado = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($calculado, $v1);
    }

    /**
     * Verifica se o timestamp do webhook esta dentro da janela de tolerancia
     * (protecao contra replay). Aceita timestamp em segundos ou milissegundos.
     * Logica pura, adequada para testes.
     */
    public static function timestampValido(
        string $ts,
        int $toleranciaSegundos = self::TOLERANCIA_WEBHOOK_SEGUNDOS,
        ?int $agora = null
    ): bool {
        if (!ctype_digit($ts)) {
            return false;
        }

        $valor = (int) $ts;
        // Timestamps com 13+ digitos estao em milissegundos.
        if (strlen($ts) >= 13) {
            $valor = (int) ($valor / 1000);
        }

        if ($valor <= 0) {
            return false;
        }

        $agora = $agora ?? time();

        return abs($agora - $valor) <= $toleranciaSegundos;
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function executarCriacao(array $request, string $idempotencyKey): array
    {
        try {
            $payment = $this->client()->create($request, $this->requestOptions($idempotencyKey));
            return $this->normalizar($payment);
        } catch (MPApiException $e) {
            throw $this->traduzirErro($e);
        }
    }

    /**
     * Converte o objeto Payment do SDK numa estrutura simples de dominio.
     *
     * @return array<string,mixed>
     */
    private function normalizar(object $payment): array
    {
        $poi = $payment->point_of_interaction->transaction_data ?? null;
        $td  = $payment->transaction_details ?? null;

        return [
            'id'              => (string) ($payment->id ?? ''),
            'status'          => (string) ($payment->status ?? 'pending'),
            'status_detail'   => (string) ($payment->status_detail ?? ''),
            'valor'           => (float) ($payment->transaction_amount ?? 0),
            'qr_code'         => $poi->qr_code ?? null,
            'qr_code_base64'  => $poi->qr_code_base64 ?? null,
            'ticket_url'      => $poi->ticket_url ?? ($td->external_resource_url ?? null),
            'boleto_url'      => $td->external_resource_url ?? null,
            'linha_digitavel' => $payment->barcode->content ?? null,
        ];
    }

    private function traduzirErro(MPApiException $e): HttpException
    {
        $statusCode = 502;
        $mensagem   = 'Falha na comunicacao com o Mercado Pago.';

        $resposta = $e->getApiResponse();
        if ($resposta !== null) {
            $statusCode = $resposta->getStatusCode();
            $conteudo   = $resposta->getContent();
            if (is_array($conteudo) && isset($conteudo['message'])) {
                $mensagem = 'Mercado Pago: ' . (string) $conteudo['message'];
            }
        }

        // Erros 4xx do gateway viram 422 (entrada invalida); demais viram 502.
        $http = ($statusCode >= 400 && $statusCode < 500) ? 422 : 502;
        return new HttpException($mensagem, $http);
    }
}

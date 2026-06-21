<?php

declare(strict_types=1);

/**
 * Runner de testes proprio (sem dependencias externas).
 *
 * Cobre a logica de dominio que pode ser exercitada sem banco de dados:
 * autoload, sanitizacao, validacoes, a regra geografica de entrega e a
 * geracao sequencial de codigos de pedido.
 *
 * Os testes que exigem MySQL (repositorios e fluxos transacionais completos)
 * nao sao executados aqui por dependerem de uma conexao real; sua verificacao
 * e feita no ambiente de homologacao, conforme descrito no README.
 *
 * Uso:  php tests/run.php
 */

use App\Core\Autoload;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\Validator;
use App\Helpers\Security;
use App\Middlewares\SecurityHeadersMiddleware;
use App\Services\AuthService;
use App\Services\CarrinhoService;
use App\Services\CheckoutService;
use App\Services\CodigoService;
use App\Services\MercadoPagoService;
use App\Services\UploadService;

$root = dirname(__DIR__);
require $root . '/core/Autoload.php';
Autoload::register();
require $root . '/helpers/functions.php';

/* --------------------------------------------------------------------------
 | Mini-framework de asserts
 -------------------------------------------------------------------------- */
$GLOBALS['__tests']  = ['pass' => 0, 'fail' => 0];
$GLOBALS['__falhas'] = [];

function teste(string $nome, callable $fn): void
{
    try {
        $fn();
        $GLOBALS['__tests']['pass']++;
        echo "  \033[32m✓\033[0m {$nome}\n";
    } catch (Throwable $e) {
        $GLOBALS['__tests']['fail']++;
        $GLOBALS['__falhas'][] = $nome . ' -> ' . $e->getMessage();
        echo "  \033[31m✗\033[0m {$nome}\n      {$e->getMessage()}\n";
    }
}

function grupo(string $titulo): void
{
    echo "\n\033[1m{$titulo}\033[0m\n";
}

/** @param mixed $esperado @param mixed $obtido */
function assertSame(mixed $esperado, mixed $obtido, string $msg = ''): void
{
    if ($esperado !== $obtido) {
        throw new RuntimeException(
            ($msg !== '' ? $msg . ' | ' : '') .
            'esperado ' . var_export($esperado, true) . ', obtido ' . var_export($obtido, true)
        );
    }
}

function assertTrue(bool $cond, string $msg = 'condicao falsa'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function assertFalse(bool $cond, string $msg = 'condicao verdadeira'): void
{
    if ($cond) {
        throw new RuntimeException($msg);
    }
}

/** Garante que o callable lance uma excecao do tipo informado (e, opcionalmente, com a mensagem exata). */
function assertLanca(string $classe, callable $fn, ?string $mensagemExata = null): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if (!($e instanceof $classe)) {
            throw new RuntimeException("esperava {$classe}, veio " . $e::class . ' (' . $e->getMessage() . ')');
        }
        if ($mensagemExata !== null && $e->getMessage() !== $mensagemExata) {
            throw new RuntimeException("mensagem divergente: esperado \"{$mensagemExata}\", obtido \"{$e->getMessage()}\"");
        }
        return;
    }
    throw new RuntimeException("nenhuma excecao lancada (esperava {$classe})");
}

echo "\n=============================================\n";
echo "  Ana Manacorda - Testes de dominio (sem DB)\n";
echo "=============================================\n";

/* --------------------------------------------------------------------------
 | 1. Autoload PSR-4
 -------------------------------------------------------------------------- */
grupo('1. Autoload PSR-4 (classes App\\*)');

foreach ([
    'App\\Core\\Router',
    'App\\Core\\Request',
    'App\\Core\\Response',
    'App\\Core\\Validator',
    'App\\Core\\Exceptions\\HttpException',
    'App\\Core\\Exceptions\\ValidationException',
    'App\\Helpers\\Auth',
    'App\\Helpers\\Csrf',
    'App\\Helpers\\Security',
    'App\\Middlewares\\AuthMiddleware',
    'App\\Models\\Usuario',
    'App\\Models\\Pedido',
    'App\\Repositories\\PedidoRepository',
    'App\\Services\\CheckoutService',
    'App\\Services\\CodigoService',
    'App\\Services\\PedidoService',
    'App\\Controllers\\AuthController',
    'App\\Controllers\\WebhookController',
] as $classe) {
    teste("carrega {$classe}", static function () use ($classe): void {
        assertTrue(class_exists($classe) || interface_exists($classe), "classe nao encontrada: {$classe}");
    });
}

/* --------------------------------------------------------------------------
 | 2. Security::normalize
 -------------------------------------------------------------------------- */
grupo('2. Security::normalize (acentos e caixa)');

teste('remove acentos e converte para maiusculas', static function (): void {
    assertSame('JUIZ DE FORA', Security::normalize('Juiz de Fora'));
});
teste('normaliza variacoes de caixa', static function (): void {
    assertSame('JUIZ DE FORA', Security::normalize('juiz de fora'));
});
teste('normaliza acentuacao diversa', static function (): void {
    assertSame('SAO JOAO DEL REI', Security::normalize('São João del Rei'));
});
teste('apara espacos das pontas', static function (): void {
    assertSame('MG', Security::normalize('  mg  '));
});

/* --------------------------------------------------------------------------
 | 3. Validator::validarCpf
 -------------------------------------------------------------------------- */
grupo('3. Validator::validarCpf (algoritmo Receita Federal)');

teste('aceita CPF valido formatado', static function (): void {
    assertTrue(Validator::validarCpf('529.982.247-25'));
});
teste('aceita CPF valido sem formatacao', static function (): void {
    assertTrue(Validator::validarCpf('11144477735'));
});
teste('rejeita digito verificador incorreto', static function (): void {
    assertFalse(Validator::validarCpf('529.982.247-20'));
});
teste('rejeita sequencia repetida', static function (): void {
    assertFalse(Validator::validarCpf('111.111.111-11'));
});
teste('rejeita quantidade de digitos invalida', static function (): void {
    assertFalse(Validator::validarCpf('123'));
});

/* --------------------------------------------------------------------------
 | 4. Validator - regras gerais
 -------------------------------------------------------------------------- */
grupo('4. Validator (regras de campo)');

teste('aprova conjunto valido', static function (): void {
    $v = Validator::make(
        ['email' => 'ana@exemplo.com', 'idade' => '30', 'cep' => '36000-000'],
        ['email' => 'required|email', 'idade' => 'integer|min:18', 'cep' => 'required|cep']
    );
    assertFalse($v->fails(), 'nao deveria falhar');
});
teste('reprova email invalido e campo obrigatorio ausente', static function (): void {
    $v = Validator::make(
        ['email' => 'invalido'],
        ['email' => 'required|email', 'nome' => 'required|string']
    );
    assertTrue($v->fails());
    $erros = $v->errors();
    assertTrue(isset($erros['email']), 'faltou erro de email');
    assertTrue(isset($erros['nome']), 'faltou erro de nome obrigatorio');
});
teste('regra max limita tamanho de string', static function (): void {
    $v = Validator::make(['nome' => str_repeat('a', 81)], ['nome' => 'max:80']);
    assertTrue($v->fails());
});
teste('regra in restringe a valores permitidos', static function (): void {
    $ok  = Validator::make(['m' => 'pix'], ['m' => 'in:pix,cartao,boleto']);
    $bad = Validator::make(['m' => 'cheque'], ['m' => 'in:pix,cartao,boleto']);
    assertFalse($ok->fails());
    assertTrue($bad->fails());
});
teste('telefone aceita 10 e 11 digitos e rejeita curtos', static function (): void {
    assertFalse(Validator::make(['t' => '(32) 99999-9999'], ['t' => 'telefone'])->fails());
    assertTrue(Validator::make(['t' => '123'], ['t' => 'telefone'])->fails());
});
teste('campo opcional ausente nao gera erro', static function (): void {
    $v = Validator::make([], ['complemento' => 'string|max:120']);
    assertFalse($v->fails());
});
teste('validate() lanca ValidationException quando invalido', static function (): void {
    assertLanca(ValidationException::class, static function (): void {
        Validator::make(['email' => 'x'], ['email' => 'required|email'])->validate();
    });
});

/* --------------------------------------------------------------------------
 | 5. CheckoutService - restricao geografica (mensagem oficial)
 -------------------------------------------------------------------------- */
grupo('5. CheckoutService::validarEntrega (somente Juiz de Fora/MG)');

$checkout = new CheckoutService(['cidade' => 'Juiz de Fora', 'estado' => 'MG']);

teste('aceita Juiz de Fora / MG', static function () use ($checkout): void {
    $checkout->validarEntrega('Juiz de Fora', 'MG');
    assertTrue(true);
});
teste('aceita variacoes de caixa e acento', static function () use ($checkout): void {
    $checkout->validarEntrega('JUIZ DE FORA', 'mg');
    $checkout->validarEntrega('juiz de fora', 'Mg');
    assertTrue(true);
});
teste('bloqueia outra cidade com a mensagem EXATA', static function () use ($checkout): void {
    assertLanca(
        HttpException::class,
        static fn () => $checkout->validarEntrega('Belo Horizonte', 'MG'),
        CheckoutService::MSG_ENTREGA_BLOQUEADA
    );
});
teste('bloqueia estado divergente', static function () use ($checkout): void {
    assertLanca(
        HttpException::class,
        static fn () => $checkout->validarEntrega('Juiz de Fora', 'SP'),
        CheckoutService::MSG_ENTREGA_BLOQUEADA
    );
});
teste('mensagem oficial inalterada', static function (): void {
    assertSame(
        'No momento realizamos vendas apenas para Juiz de Fora (MG).',
        CheckoutService::MSG_ENTREGA_BLOQUEADA
    );
});
teste('status HTTP do bloqueio e 422', static function () use ($checkout): void {
    try {
        $checkout->validarEntrega('Rio de Janeiro', 'RJ');
        throw new RuntimeException('deveria ter bloqueado');
    } catch (HttpException $e) {
        assertSame(422, $e->getStatusCode());
    }
});

/* --------------------------------------------------------------------------
 | 6. CheckoutService - validacao e normalizacao dos dados de entrega
 -------------------------------------------------------------------------- */
grupo('6. CheckoutService::validarDadosEntrega (normalizacao)');

$dadosValidos = [
    'nome'        => 'Ana',
    'sobrenome'   => 'Manacorda',
    'telefone'    => '(32) 98888-7777',
    'email'       => 'ANA@Exemplo.com',
    'cep'         => '36000-100',
    'rua'         => 'Rua das Flores',
    'numero'      => '123',
    'bairro'      => 'Centro',
    'complemento' => 'Apto 201',
    'cidade'      => 'Juiz de Fora',
    'estado'      => 'mg',
    'observacoes' => 'Entregar a tarde',
];

teste('retorna endereco normalizado para dados validos', static function () use ($dadosValidos): void {
    $svc  = new CheckoutService(['cidade' => 'Juiz de Fora', 'estado' => 'MG']);
    $norm = $svc->validarDadosEntrega($dadosValidos);
    assertSame('36000100', $norm['cep'], 'CEP deve conter apenas digitos');
    assertSame('Apto 201', $norm['complemento'], 'complemento deve ser preservado');
});
teste('telefone e e-mail sao normalizados', static function () use ($dadosValidos): void {
    $svc  = new CheckoutService(['cidade' => 'Juiz de Fora', 'estado' => 'MG']);
    $norm = $svc->validarDadosEntrega($dadosValidos);
    assertSame('32988887777', $norm['telefone'], 'telefone deve conter apenas digitos');
    assertSame('ana@exemplo.com', $norm['email'], 'email deve ser minusculo e aparado');
    assertSame('MG', $norm['estado'], 'estado deve ser maiusculo');
});
teste('rejeita dados invalidos com ValidationException', static function (): void {
    $svc = new CheckoutService(['cidade' => 'Juiz de Fora', 'estado' => 'MG']);
    assertLanca(ValidationException::class, static function () use ($svc): void {
        $svc->validarDadosEntrega([
            'nome'   => '',
            'email'  => 'invalido',
            'cep'    => 'xxxxx',
            'cidade' => 'Juiz de Fora',
            'estado' => 'MG',
        ]);
    });
});
teste('dados validos de outra cidade param na restricao geografica', static function () use ($dadosValidos): void {
    $svc   = new CheckoutService(['cidade' => 'Juiz de Fora', 'estado' => 'MG']);
    $dados = array_merge($dadosValidos, ['cidade' => 'Niteroi', 'estado' => 'RJ']);
    assertLanca(
        HttpException::class,
        static fn () => $svc->validarDadosEntrega($dados),
        CheckoutService::MSG_ENTREGA_BLOQUEADA
    );
});

/* --------------------------------------------------------------------------
 | 7. CodigoService - geracao sequencial PED-AAAA-NNNNNN
 -------------------------------------------------------------------------- */
grupo('7. CodigoService::proximoCodigo (sequencial, sem rand/uniqid)');

teste('primeiro codigo do ano e 000001', static function (): void {
    assertSame('PED-2026-000001', CodigoService::proximoCodigo(null, '2026'));
});
teste('incrementa a partir do ultimo codigo', static function (): void {
    assertSame('PED-2026-000042', CodigoService::proximoCodigo('PED-2026-000041', '2026'));
});
teste('mantem o padding de 6 digitos na virada de casa', static function (): void {
    assertSame('PED-2026-001000', CodigoService::proximoCodigo('PED-2026-000999', '2026'));
});
teste('respeita o ano informado', static function (): void {
    assertSame('PED-2027-000001', CodigoService::proximoCodigo(null, '2027'));
});
teste('codigo sequencial e estritamente crescente', static function (): void {
    $a = CodigoService::proximoCodigo('PED-2026-000007', '2026');
    $b = CodigoService::proximoCodigo($a, '2026');
    assertSame('PED-2026-000008', $a);
    assertSame('PED-2026-000009', $b);
});
teste('rejeita codigo anterior em formato invalido', static function (): void {
    assertLanca(RuntimeException::class, static function (): void {
        CodigoService::proximoCodigo('PED-2026-ABCDEF', '2026');
    });
});

/* --------------------------------------------------------------------------
 | 8. Correcoes Fase 1 - Headers de seguranca
 -------------------------------------------------------------------------- */
grupo('8. SecurityHeadersMiddleware (cabecalhos obrigatorios)');

teste('define todos os cabecalhos exigidos', static function (): void {
    $h = SecurityHeadersMiddleware::cabecalhos();
    foreach (['X-Frame-Options', 'X-Content-Type-Options', 'Referrer-Policy', 'Content-Security-Policy', 'Permissions-Policy'] as $nome) {
        assertTrue(isset($h[$nome]) && $h[$nome] !== '', "faltou {$nome}");
    }
});
teste('X-Content-Type-Options e nosniff', static function (): void {
    assertSame('nosniff', SecurityHeadersMiddleware::cabecalhos()['X-Content-Type-Options']);
});
teste('X-Frame-Options bloqueia enquadramento', static function (): void {
    assertSame('DENY', SecurityHeadersMiddleware::cabecalhos()['X-Frame-Options']);
});

/* --------------------------------------------------------------------------
 | 9. Correcoes Fase 1 - Validacao de upload
 -------------------------------------------------------------------------- */
grupo('9. UploadService::validarMetadados (whitelist + MIME real)');

teste('aceita JPG com MIME correto', static function (): void {
    UploadService::validarMetadados('foto.jpg', 'image/jpeg', 1024);
    assertTrue(true);
});
teste('aceita PNG e WEBP com MIME correto', static function (): void {
    UploadService::validarMetadados('img.png', 'image/png', 2048);
    UploadService::validarMetadados('img.webp', 'image/webp', 2048);
    assertTrue(true);
});
teste('bloqueia extensao .php', static function (): void {
    assertLanca(ValidationException::class, static function (): void {
        UploadService::validarMetadados('shell.php', 'image/jpeg', 1024);
    });
});
teste('bloqueia .svg (lista de bloqueio)', static function (): void {
    assertLanca(ValidationException::class, static function (): void {
        UploadService::validarMetadados('vetor.svg', 'image/svg+xml', 1024);
    });
});
teste('rejeita extensao permitida com conteudo (MIME) divergente', static function (): void {
    assertLanca(ValidationException::class, static function (): void {
        UploadService::validarMetadados('falsa.jpg', 'text/html', 1024);
    });
});
teste('rejeita arquivo acima do tamanho maximo', static function (): void {
    assertLanca(ValidationException::class, static function (): void {
        UploadService::validarMetadados('grande.jpg', 'image/jpeg', UploadService::TAMANHO_MAXIMO + 1);
    });
});
teste('rejeita arquivo vazio', static function (): void {
    assertLanca(ValidationException::class, static function (): void {
        UploadService::validarMetadados('vazio.png', 'image/png', 0);
    });
});

/* --------------------------------------------------------------------------
 | 10. Correcoes Fase 1 - Webhook (protecao contra replay)
 -------------------------------------------------------------------------- */
grupo('10. MercadoPagoService::timestampValido (anti-replay)');

teste('aceita timestamp atual (segundos)', static function (): void {
    assertTrue(MercadoPagoService::timestampValido((string) time()));
});
teste('rejeita timestamp antigo fora da tolerancia', static function (): void {
    assertFalse(MercadoPagoService::timestampValido((string) (time() - 3600)));
});
teste('aceita timestamp recente em milissegundos', static function (): void {
    $ms = (string) (time() * 1000);
    assertTrue(MercadoPagoService::timestampValido($ms));
});
teste('rejeita valor nao numerico', static function (): void {
    assertFalse(MercadoPagoService::timestampValido('abc'));
});
teste('respeita janela de tolerancia explicita', static function (): void {
    $agora = 1_000_000;
    assertTrue(MercadoPagoService::timestampValido((string) (1_000_000 - 100), 300, $agora));
    assertFalse(MercadoPagoService::timestampValido((string) (1_000_000 - 400), 300, $agora));
});

/* --------------------------------------------------------------------------
 | 11. Correcoes Fase 1 - Limite de quantidade no carrinho
 -------------------------------------------------------------------------- */
grupo('11. CarrinhoService::validarLimitesQuantidade (min/max)');

teste('aceita quantidade 1', static function (): void {
    CarrinhoService::validarLimitesQuantidade(1);
    assertTrue(true);
});
teste('aceita quantidade no limite maximo', static function (): void {
    CarrinhoService::validarLimitesQuantidade(CarrinhoService::MAX_QUANTIDADE_ITEM);
    assertTrue(true);
});
teste('rejeita quantidade zero ou negativa', static function (): void {
    assertLanca(ValidationException::class, static fn () => CarrinhoService::validarLimitesQuantidade(0));
    assertLanca(ValidationException::class, static fn () => CarrinhoService::validarLimitesQuantidade(-3));
});
teste('rejeita quantidade acima do maximo', static function (): void {
    assertLanca(ValidationException::class, static function (): void {
        CarrinhoService::validarLimitesQuantidade(CarrinhoService::MAX_QUANTIDADE_ITEM + 1);
    });
});

/* --------------------------------------------------------------------------
 | 12. Correcoes Fase 1 - Mensagem anti-enumeracao (recuperacao de senha)
 -------------------------------------------------------------------------- */
grupo('12. AuthService::MSG_RECUPERACAO_GENERICA (mensagem exata)');

teste('mensagem generica corresponde exatamente a especificacao', static function (): void {
    assertSame(
        'Se existir uma conta vinculada a este e-mail, você receberá as instruções para recuperação.',
        AuthService::MSG_RECUPERACAO_GENERICA
    );
});

/* --------------------------------------------------------------------------
 | Resumo
 -------------------------------------------------------------------------- */
$pass = $GLOBALS['__tests']['pass'];
$fail = $GLOBALS['__tests']['fail'];

echo "\n=============================================\n";
echo "  Resultado: {$pass} passou(aram), {$fail} falhou(aram)\n";
echo "=============================================\n";

if ($fail > 0) {
    echo "\nFalhas:\n";
    foreach ($GLOBALS['__falhas'] as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "\nTodos os testes de dominio passaram.\n";
exit(0);

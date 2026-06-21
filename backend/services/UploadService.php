<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use RuntimeException;

/**
 * Validacao e processamento seguro de upload de imagens.
 *
 * Regras (nunca confiar apenas na extensao):
 *  - extensao deve estar na lista permitida (jpg, jpeg, png, webp);
 *  - extensoes perigosas sao explicitamente bloqueadas (php, phtml, js, exe,
 *    bat, svg);
 *  - o MIME real e detectado a partir do conteudo do arquivo (finfo) e deve
 *    constar na lista permitida;
 *  - o tamanho nao pode exceder o limite.
 *
 * O nome final e gerado pelo servidor (sem reutilizar o nome enviado pelo
 * cliente), evitando path traversal e sobrescrita.
 */
final class UploadService
{
    /** @var string[] */
    public const EXTENSOES_PERMITIDAS = ['jpg', 'jpeg', 'png', 'webp'];

    /** @var string[] */
    public const EXTENSOES_BLOQUEADAS = ['php', 'phtml', 'js', 'exe', 'bat', 'svg'];

    /** @var string[] */
    public const MIME_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];

    /** Tamanho maximo padrao: 5 MB. */
    public const TAMANHO_MAXIMO = 5 * 1024 * 1024;

    /**
     * Valida os metadados do arquivo (extensao, blacklist, MIME e tamanho).
     * Logica pura, sem acesso a disco — adequada para testes.
     *
     * @param string $nomeOriginal nome original enviado (usado apenas para extrair a extensao)
     * @param string $mimeReal      MIME detectado a partir do conteudo do arquivo
     * @param int    $tamanho       tamanho em bytes
     * @param int    $tamanhoMaximo limite em bytes
     *
     * @throws ValidationException quando o arquivo nao atende as regras
     */
    public static function validarMetadados(
        string $nomeOriginal,
        string $mimeReal,
        int $tamanho,
        int $tamanhoMaximo = self::TAMANHO_MAXIMO
    ): void {
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        if ($extensao === '') {
            throw new ValidationException(['arquivo' => ['Arquivo sem extensao.']]);
        }

        if (in_array($extensao, self::EXTENSOES_BLOQUEADAS, true)) {
            throw new ValidationException(['arquivo' => ['Tipo de arquivo nao permitido.']]);
        }

        if (!in_array($extensao, self::EXTENSOES_PERMITIDAS, true)) {
            throw new ValidationException([
                'arquivo' => ['Extensao invalida. Permitidas: ' . implode(', ', self::EXTENSOES_PERMITIDAS) . '.'],
            ]);
        }

        if (!in_array($mimeReal, self::MIME_PERMITIDOS, true)) {
            // Conteudo nao corresponde a uma imagem permitida (extensao mascarada).
            throw new ValidationException(['arquivo' => ['Conteudo do arquivo nao corresponde a uma imagem valida.']]);
        }

        if ($tamanho <= 0) {
            throw new ValidationException(['arquivo' => ['Arquivo vazio.']]);
        }

        if ($tamanho > $tamanhoMaximo) {
            $mb = (int) round($tamanhoMaximo / (1024 * 1024));
            throw new ValidationException(['arquivo' => ['Arquivo excede o tamanho maximo de ' . $mb . ' MB.']]);
        }
    }

    /**
     * Processa um arquivo recebido (estrutura de $_FILES) e o move para o
     * diretorio de destino, retornando o nome final gerado pelo servidor.
     *
     * @param array<string,mixed> $arquivo     item de $_FILES (com tmp_name, name, size, error)
     * @param string              $destinoDir  diretorio de destino (sera criado se necessario)
     * @param int                 $tamanhoMaximo
     *
     * @return string nome do arquivo gravado
     *
     * @throws ValidationException|HttpException
     */
    public function processar(array $arquivo, string $destinoDir, int $tamanhoMaximo = self::TAMANHO_MAXIMO): string
    {
        $erro = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro !== UPLOAD_ERR_OK) {
            throw new ValidationException(['arquivo' => ['Falha no envio do arquivo (codigo ' . $erro . ').']]);
        }

        $tmp = (string) ($arquivo['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new HttpException('Upload invalido.', 400);
        }

        $nomeOriginal = (string) ($arquivo['name'] ?? '');
        $tamanho      = (int) ($arquivo['size'] ?? 0);

        // MIME real a partir do conteudo (nunca confiar na extensao/Content-Type).
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('Nao foi possivel inspecionar o arquivo enviado.');
        }
        $mimeReal = (string) finfo_file($finfo, $tmp);
        finfo_close($finfo);

        self::validarMetadados($nomeOriginal, $mimeReal, $tamanho, $tamanhoMaximo);

        if (!is_dir($destinoDir) && !mkdir($destinoDir, 0775, true) && !is_dir($destinoDir)) {
            throw new RuntimeException('Nao foi possivel criar o diretorio de destino.');
        }

        $extensao    = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensao;
        $destino     = rtrim($destinoDir, '/') . '/' . $nomeArquivo;

        if (!move_uploaded_file($tmp, $destino)) {
            throw new RuntimeException('Falha ao gravar o arquivo enviado.');
        }

        return $nomeArquivo;
    }
}

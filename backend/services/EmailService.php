<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 * Envio de e-mails transacionais via PHPMailer.
 *
 * Driver configuravel em config/mail.php:
 *  - "smtp": envio real via servidor SMTP.
 *  - "log" : grava o e-mail em storage/mail/*.eml (transporte local real,
 *            util para desenvolvimento e para inspecao do conteudo enviado).
 */
final class EmailService
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('mail');
    }

    public function enviarVerificacao(string $email, string $nome, string $token): void
    {
        $url = config('app.frontend_url') . '/verificar-email?token=' . urlencode($token);
        $html = $this->layout(
            'Confirme seu e-mail',
            "<p>Ola, {$this->h($nome)}!</p>"
            . '<p>Obrigado por criar sua conta na Ana Manacorda. Para ativa-la, confirme seu e-mail clicando no botao abaixo:</p>'
            . "<p style=\"text-align:center;margin:32px 0;\"><a href=\"{$this->h($url)}\" "
            . 'style="background:#b76e79;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;display:inline-block;">'
            . 'Confirmar e-mail</a></p>'
            . '<p style="font-size:13px;color:#666;">Se o botao nao funcionar, copie e cole este endereco no navegador:<br>'
            . "<span style=\"word-break:break-all;\">{$this->h($url)}</span></p>"
        );

        $this->enviar($email, $nome, 'Confirme seu e-mail - Ana Manacorda', $html);
    }

    public function enviarCodigoRecuperacao(string $email, string $nome, string $codigo): void
    {
        $html = $this->layout(
            'Recuperacao de senha',
            "<p>Ola, {$this->h($nome)}!</p>"
            . '<p>Recebemos uma solicitacao para redefinir a senha da sua conta. Utilize o codigo abaixo para continuar:</p>'
            . "<p style=\"text-align:center;margin:32px 0;\"><span style=\"font-size:32px;letter-spacing:8px;"
            . "font-weight:bold;color:#b76e79;\">{$this->h($codigo)}</span></p>"
            . '<p style="font-size:13px;color:#666;">Este codigo expira em 30 minutos. Se voce nao solicitou a '
            . 'troca de senha, ignore este e-mail.</p>'
        );

        $this->enviar($email, $nome, 'Codigo de recuperacao de senha - Ana Manacorda', $html);
    }

    /** @param array<string,mixed> $pedido */
    public function enviarConfirmacaoPedido(string $email, string $nome, array $pedido): void
    {
        $linhas = '';
        foreach (($pedido['itens'] ?? []) as $item) {
            $linhas .= '<tr>'
                . '<td style="padding:6px 0;border-bottom:1px solid #eee;">'
                . $this->h((string) ($item['nome'] ?? '')) . ' x' . (int) ($item['quantidade'] ?? 0)
                . '</td>'
                . '<td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right;">R$ '
                . number_format((float) ($item['subtotal'] ?? 0), 2, ',', '.')
                . '</td></tr>';
        }

        $total = number_format((float) ($pedido['total'] ?? 0), 2, ',', '.');
        $html = $this->layout(
            'Pedido confirmado',
            "<p>Ola, {$this->h($nome)}!</p>"
            . '<p>Seu pedido foi registrado com sucesso.</p>'
            . "<p><strong>Codigo:</strong> {$this->h((string) ($pedido['codigo'] ?? ''))}</p>"
            . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">' . $linhas
            . "<tr><td style=\"padding:10px 0;font-weight:bold;\">Total</td>"
            . "<td style=\"padding:10px 0;font-weight:bold;text-align:right;\">R$ {$total}</td></tr></table>"
            . '<p>Voce pode acompanhar o status do seu pedido na area do cliente.</p>'
        );

        $this->enviar($email, $nome, 'Pedido ' . ($pedido['codigo'] ?? '') . ' confirmado - Ana Manacorda', $html);
    }

    private function enviar(string $para, string $nomePara, string $assunto, string $html): void
    {
        $driver = (string) ($this->config['driver'] ?? 'smtp');

        if ($driver === 'log') {
            $this->gravarArquivo($para, $assunto, $html);
            return;
        }

        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host       = (string) $this->config['host'];
            $mailer->SMTPAuth   = true;
            $mailer->Username   = (string) $this->config['username'];
            $mailer->Password   = (string) $this->config['password'];
            $mailer->Port       = (int) $this->config['port'];
            $mailer->CharSet    = 'UTF-8';

            $encryption = (string) ($this->config['encryption'] ?? 'tls');
            if ($encryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $from = (array) $this->config['from'];
            $mailer->setFrom((string) $from['address'], (string) $from['name']);
            $mailer->addAddress($para, $nomePara);

            $mailer->isHTML(true);
            $mailer->Subject = $assunto;
            $mailer->Body    = $html;
            $mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $html));

            $mailer->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('Falha ao enviar e-mail: ' . $mailer->ErrorInfo, 0, $e);
        }
    }

    private function gravarArquivo(string $para, string $assunto, string $html): void
    {
        $dir = storage_path('mail');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $from = (array) $this->config['from'];
        $conteudo = "From: {$from['name']} <{$from['address']}>\r\n"
            . "To: {$para}\r\n"
            . "Subject: {$assunto}\r\n"
            . "Date: " . date('r') . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $html;

        $arquivo = $dir . '/' . date('Ymd_His') . '_' . substr(hash('sha256', $para . $assunto . microtime()), 0, 8) . '.eml';
        file_put_contents($arquivo, $conteudo);
    }

    private function layout(string $titulo, string $corpo): string
    {
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background:#f6f3f4;font-family:Arial,Helvetica,sans-serif;color:#333;">'
            . '<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:10px;overflow:hidden;'
            . 'box-shadow:0 2px 8px rgba(0,0,0,0.06);">'
            . '<div style="background:#b76e79;padding:20px 32px;"><h1 style="margin:0;color:#fff;font-size:20px;">'
            . 'Ana Manacorda</h1></div>'
            . "<div style=\"padding:32px;\"><h2 style=\"margin-top:0;font-size:18px;color:#444;\">{$this->h($titulo)}</h2>"
            . $corpo . '</div>'
            . '<div style="padding:18px 32px;background:#faf7f8;font-size:12px;color:#999;text-align:center;">'
            . 'Este e um e-mail automatico. Por favor, nao responda.</div>'
            . '</div></body></html>';
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

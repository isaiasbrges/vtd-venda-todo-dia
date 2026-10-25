<?php
declare(strict_types=1);

namespace VTD\Services;

use VTD\Config\Config;

class MailerService
{
    private array $config;
    private array $sentMails = [];

    public function __construct(array $customConfig = [])
    {
        $defaultConfig = Config::get('mail', []);
        $this->config = array_merge($defaultConfig, $customConfig);
    }

    public function getSentMails(): array
    {
        return $this->sentMails;
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $driver = $this->config['driver'] ?? 'mock';

        if ($driver === 'mock') {
            $this->sentMails[] = [
                'to'      => $toEmail,
                'name'    => $toName,
                'subject' => $subject,
                'body'    => $htmlBody,
                'time'    => time()
            ];
            return true;
        }

        if ($driver === 'smtp') {
            return $this->sendViaSmtp($toEmail, $toName, $subject, $htmlBody);
        }

        // Fallback nativo mail()
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . ($this->config['from_name'] ?? 'VTD') . ' <' . ($this->config['from_address'] ?? 'nao-responda@vtd.local') . '>',
            'Reply-To: ' . ($this->config['from_address'] ?? 'nao-responda@vtd.local'),
            'X-Mailer: PHP/' . PHP_VERSION
        ];

        return @mail($toEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
    }

    public function sendWelcomeCredentials(string $email, string $name, string $password, string $plan): bool
    {
        $appUrl = rtrim((string)Config::get('app_url', 'https://vtd.local'), '/');
        $loginUrl = $appUrl . '/#/login';

        ob_start();
        require dirname(__DIR__) . '/Views/emails/welcome.php';
        $html = ob_get_clean();

        $subject = 'Seu acesso ao Venda Todo Dia chegou!';
        return $this->send($email, $name, $subject, $html);
    }

    public function sendPasswordReset(string $email, string $name, string $token): bool
    {
        $appUrl = rtrim((string)Config::get('app_url', 'https://vtd.local'), '/');
        $resetUrl = $appUrl . '/#/recovery?token=' . urlencode($token);

        ob_start();
        require dirname(__DIR__) . '/Views/emails/reset_password.php';
        $html = ob_get_clean();

        $subject = 'Recuperação de acesso · Venda Todo Dia';
        return $this->send($email, $name, $subject, $html);
    }

    public function sendUpgradeNotification(string $email, string $name): bool
    {
        $appUrl = rtrim((string)Config::get('app_url', 'https://vtd.local'), '/');
        $loginUrl = $appUrl . '/#/finance';

        ob_start();
        require dirname(__DIR__) . '/Views/emails/upgrade_plus.php';
        $html = ob_get_clean();

        $subject = 'Módulo Financeiro liberado na sua conta!';
        return $this->send($email, $name, $subject, $html);
    }

    private function sendViaSmtp(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $host = $this->config['host'] ?? 'smtp.hostinger.com';
        $port = (int)($this->config['port'] ?? 465);
        $user = $this->config['username'] ?? '';
        $pass = $this->config['password'] ?? '';
        $fromName = $this->config['from_name'] ?? 'VTD';
        $fromAddress = $this->config['from_address'] ?? $user;
        $encryption = $this->config['encryption'] ?? 'ssl';

        $protocol = ($encryption === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($protocol . $host, $port, $errno, $errstr, 15);
        if (!$socket) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return false;
        }

        $read = fn() => fgets($socket, 515);
        $write = fn($cmd) => fputs($socket, $cmd . "\r\n");

        $read(); // banner
        $write("EHLO " . gethostname());
        while ($line = $read()) {
            if (substr($line, 3, 1) === ' ') break;
        }

        if ($encryption === 'tls') {
            $write("STARTTLS");
            $read();
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write("EHLO " . gethostname());
            while ($line = $read()) {
                if (substr($line, 3, 1) === ' ') break;
            }
        }

        $write("AUTH LOGIN");
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $authResponse = $read();
        if (!str_starts_with($authResponse, '235')) {
            fclose($socket);
            error_log("SMTP Auth failed: " . $authResponse);
            return false;
        }

        $write("MAIL FROM: <$fromAddress>");
        $read();
        $write("RCPT TO: <$toEmail>");
        $read();
        $write("DATA");
        $read();

        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromAddress>",
            "To: =?UTF-8?B?" . base64_encode($toName) . "?= <$toEmail>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date(DATE_RFC2822),
            "X-Mailer: VTD-Mailer/1.0"
        ];

        $data = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
        $write($data);
        $result = $read();

        $write("QUIT");
        fclose($socket);

        return str_starts_with($result, '250');
    }
}

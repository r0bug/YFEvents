<?php
/**
 * Email Service
 * Sends emails via SMTP
 */

namespace YFEvents\Modules\YFClaim\Services;

class EmailService
{
    private array $config;
    private bool $debug = false;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    private function loadConfig(): array
    {
        $configPath = dirname(__DIR__, 4) . '/config/email.php';
        if (file_exists($configPath)) {
            return require $configPath;
        }
        return ['enabled' => false];
    }

    /**
     * Send an email
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        if (!$this->config['enabled']) {
            error_log("Email disabled - would send to: {$to}, subject: {$subject}");
            return false;
        }

        $smtp = $this->config['smtp'];

        // If no SMTP credentials, use fallback mail()
        if (empty($smtp['username']) || empty($smtp['password'])) {
            return $this->sendViaMail($to, $subject, $htmlBody);
        }

        return $this->sendViaSmtp($to, $subject, $htmlBody, $textBody);
    }

    /**
     * Send via PHP mail() function (fallback)
     */
    private function sendViaMail(string $to, string $subject, string $htmlBody): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $this->config['from']['name'] . ' <' . $this->config['from']['email'] . '>',
            'Reply-To: ' . $this->config['reply_to']['email'],
            'X-Mailer: YFClaim'
        ];

        $result = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        error_log("Email via mail() to {$to}: " . ($result ? 'sent' : 'failed'));
        return $result;
    }

    /**
     * Send via SMTP
     */
    private function sendViaSmtp(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $smtp = $this->config['smtp'];
        $from = $this->config['from'];

        try {
            // Connect to SMTP server
            $host = ($smtp['encryption'] === 'ssl' ? 'ssl://' : '') . $smtp['host'];
            $socket = @fsockopen($host, $smtp['port'], $errno, $errstr, 30);

            if (!$socket) {
                error_log("SMTP connection failed: {$errstr} ({$errno})");
                return false;
            }

            // Set timeout
            stream_set_timeout($socket, 30);

            // Read greeting
            $this->smtpRead($socket);

            // EHLO
            $this->smtpCommand($socket, "EHLO " . gethostname());

            // STARTTLS for TLS encryption
            if ($smtp['encryption'] === 'tls') {
                $this->smtpCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->smtpCommand($socket, "EHLO " . gethostname());
            }

            // AUTH LOGIN
            $this->smtpCommand($socket, "AUTH LOGIN");
            $this->smtpCommand($socket, base64_encode($smtp['username']));
            $this->smtpCommand($socket, base64_encode($smtp['password']));

            // MAIL FROM
            $this->smtpCommand($socket, "MAIL FROM:<{$from['email']}>");

            // RCPT TO
            $this->smtpCommand($socket, "RCPT TO:<{$to}>");

            // DATA
            $this->smtpCommand($socket, "DATA");

            // Build message
            $boundary = md5(uniqid(time()));
            $message = "From: {$from['name']} <{$from['email']}>\r\n";
            $message .= "To: {$to}\r\n";
            $message .= "Subject: {$subject}\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $message .= "\r\n";

            // Plain text part
            $plainText = $textBody ?? strip_tags($htmlBody);
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=utf-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $plainText . "\r\n";

            // HTML part
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=utf-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $htmlBody . "\r\n";
            $message .= "--{$boundary}--\r\n";
            $message .= ".";

            $this->smtpCommand($socket, $message);

            // QUIT
            $this->smtpCommand($socket, "QUIT");

            fclose($socket);

            error_log("Email sent via SMTP to {$to}");
            return true;

        } catch (\Exception $e) {
            error_log("SMTP error: " . $e->getMessage());
            if (isset($socket) && $socket) {
                fclose($socket);
            }
            return false;
        }
    }

    private function smtpCommand($socket, string $command): string
    {
        fwrite($socket, $command . "\r\n");
        if ($this->debug) {
            error_log("SMTP >> " . $command);
        }
        return $this->smtpRead($socket);
    }

    private function smtpRead($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        if ($this->debug) {
            error_log("SMTP << " . trim($response));
        }

        $code = substr($response, 0, 3);
        if ($code >= 400) {
            throw new \Exception("SMTP error: " . trim($response));
        }

        return $response;
    }

    /**
     * Enable debug mode
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Test SMTP connection
     */
    public function testConnection(): array
    {
        if (!$this->config['enabled']) {
            return ['success' => false, 'error' => 'Email disabled'];
        }

        $smtp = $this->config['smtp'];

        if (empty($smtp['username'])) {
            return ['success' => false, 'error' => 'No SMTP credentials configured'];
        }

        try {
            $host = ($smtp['encryption'] === 'ssl' ? 'ssl://' : '') . $smtp['host'];
            $socket = @fsockopen($host, $smtp['port'], $errno, $errstr, 10);

            if (!$socket) {
                return ['success' => false, 'error' => "Connection failed: {$errstr}"];
            }

            fclose($socket);
            return ['success' => true, 'message' => 'SMTP connection successful'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

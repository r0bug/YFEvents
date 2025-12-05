<?php
/**
 * SMS Service (Twilio)
 * Sends SMS notifications via Twilio API
 */

namespace YFEvents\Modules\YFClaim\Services;

class SmsService
{
    private array $config;
    private string $apiUrl = 'https://api.twilio.com/2010-04-01/Accounts';

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    private function loadConfig(): array
    {
        $configPath = dirname(__DIR__, 4) . '/config/sms.php';
        if (file_exists($configPath)) {
            return require $configPath;
        }
        return ['enabled' => false];
    }

    /**
     * Send an SMS message
     */
    public function send(string $to, string $message): array
    {
        if (!$this->config['enabled']) {
            error_log("SMS disabled - would send to: {$to}, message: {$message}");
            return ['success' => false, 'error' => 'SMS disabled'];
        }

        $twilio = $this->config['twilio'];

        if (empty($twilio['account_sid']) || empty($twilio['auth_token'])) {
            return ['success' => false, 'error' => 'Twilio not configured'];
        }

        // Normalize phone number
        $to = $this->normalizePhoneNumber($to);
        if (!$to) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        // Send via Twilio API
        return $this->sendViaTwilio($to, $message);
    }

    /**
     * Send SMS via Twilio REST API
     */
    private function sendViaTwilio(string $to, string $message): array
    {
        $twilio = $this->config['twilio'];
        $url = "{$this->apiUrl}/{$twilio['account_sid']}/Messages.json";

        $postData = [
            'From' => $twilio['from_number'],
            'To' => $to,
            'Body' => $message
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$twilio['account_sid']}:{$twilio['auth_token']}",
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Twilio curl error: {$error}");
            return ['success' => false, 'error' => 'Connection error'];
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("SMS sent to {$to}: SID {$result['sid']}");
            return [
                'success' => true,
                'message_sid' => $result['sid'],
                'status' => $result['status']
            ];
        }

        $errorMessage = $result['message'] ?? 'Unknown error';
        error_log("Twilio error ({$httpCode}): {$errorMessage}");
        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Normalize phone number to E.164 format
     */
    private function normalizePhoneNumber(string $phone): ?string
    {
        // Remove all non-digits
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // US number handling
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }

        if (strlen($digits) === 11 && $digits[0] === '1') {
            return '+' . $digits;
        }

        // Already has country code
        if (strlen($digits) >= 11) {
            return '+' . $digits;
        }

        return null;
    }

    /**
     * Send verification code
     */
    public function sendVerificationCode(string $phone, string $code): array
    {
        $message = "Your YFClaim verification code is: {$code}. This code expires in 10 minutes.";
        return $this->send($phone, $message);
    }

    /**
     * Send offer notification
     */
    public function sendOfferNotification(string $phone, string $itemTitle, float $amount): array
    {
        $message = "YFClaim: New offer of \${$amount} received on \"{$itemTitle}\". View offers at yfevents.yakimafinds.com/seller";
        return $this->send($phone, $message);
    }

    /**
     * Send offer accepted notification
     */
    public function sendOfferAcceptedNotification(string $phone, string $itemTitle): array
    {
        $message = "YFClaim: Your offer on \"{$itemTitle}\" was accepted! Check your offers at yfevents.yakimafinds.com/claims/my-offers.php";
        return $this->send($phone, $message);
    }

    /**
     * Send outbid notification
     */
    public function sendOutbidNotification(string $phone, string $itemTitle, float $newHighBid): array
    {
        $message = "YFClaim: You've been outbid on \"{$itemTitle}\". New high offer: \${$newHighBid}. Place a new offer at yfevents.yakimafinds.com/claims";
        return $this->send($phone, $message);
    }

    /**
     * Test Twilio connection
     */
    public function testConnection(): array
    {
        if (!$this->config['enabled']) {
            return ['success' => false, 'error' => 'SMS disabled'];
        }

        $twilio = $this->config['twilio'];

        if (empty($twilio['account_sid'])) {
            return ['success' => false, 'error' => 'Twilio not configured'];
        }

        // Test API access
        $url = "{$this->apiUrl}/{$twilio['account_sid']}.json";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$twilio['account_sid']}:{$twilio['auth_token']}",
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'Twilio connection successful'];
        }

        return ['success' => false, 'error' => "Twilio API error (HTTP {$httpCode})"];
    }
}

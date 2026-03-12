<?php

declare(strict_types=1);

namespace App\Services;

class SMSService
{
    private string $apiKey;
    private string $apiUrl = 'https://sms.arkesel.com/api/v2/sms/send';
    private string $sender;

    public function __construct()
    {
        $this->apiKey = $_ENV['SMS_API_KEY'] ?? '';
        $this->apiUrl = $_ENV['SMS_API_URL'] ?? 'https://sms.arkesel.com/api/v2/sms/send';
        $this->sender = $_ENV['SMS_SENDER_ID'] ?? 'Eventic';
    }

    /**
     * Send Password Reset SMS
     *
     * @param string $phone Recipient phone number
     * @param string $token Reset token
     * @return bool Success
     */
    public function sendPasswordResetSMS(string $phone, string $token): bool
    {
        if (empty($this->apiKey)) {
            error_log('SMS Service Error: API Key is missing.');
            return false;
        }

        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
        $resetLink = "{$appUrl}/reset-password?token={$token}";
        $message = "Reset your password here: {$resetLink}";

        return $this->send($phone, $message);
    }

    /**
     * Send a generic SMS
     *
     * @param string $phone Recipient phone number
     * @param string $message Message content
     * @return bool Success
     */
    public function send(string $phone, string $message): bool
    {
        $payload = [
            "sender"     => $this->sender,
            "message"    => $message,
            "recipients" => [$phone]
        ];

        $headers = [
            "Authorization: Bearer " . $this->apiKey,
            "Content-Type: application/json"
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log('SMS Curl Error: ' . curl_error($ch));
        }
        
        curl_close($ch);

        return $httpCode === 200;
    }
}

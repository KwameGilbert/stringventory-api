<?php

declare(strict_types=1);

namespace App\Services;

use Exception;

/**
 * TemplateEngine - Renders email and SMS templates
 * 
 * Simple, secure template system with:
 * - Variable substitution
 * - HTML email support
 * - SMS formatting
 * - XSS protection
 */
class TemplateEngine
{
    private string $emailTemplatesDir;
    private string $smsTemplatesDir;
    private array $config;

    public function __construct()
    {
        $this->emailTemplatesDir = dirname(__DIR__, 2) . '/templates/email';
        $this->smsTemplatesDir = dirname(__DIR__, 2) . '/templates/sms';
        
        $this->config = [
            'app_name' => $_ENV['APP_NAME'] ?? 'Eventic',
            'app_url' => $_ENV['FRONTEND_URL'] ?? 'https://eventic.com',
            'support_email' => $_ENV['SUPPORT_EMAIL'] ?? 'support@eventic.com',
            'support_phone' => $_ENV['SUPPORT_PHONE'] ?? '+233XXXXXXXXX',
        ];
    }

    /**
     * Render email template
     */
    public function renderEmail(string $type, array $data): array
    {
        try {
            $templateFile = $this->emailTemplatesDir . "/{$type}.json";

            if (!file_exists($templateFile)) {
                throw new Exception("Email template not found: {$type}");
            }

            $template = json_decode(file_get_contents($templateFile), true);

            // Merge with app config
            $data = array_merge($this->config, $data);

            return [
                'subject' => $this->replaceVariables($template['subject'], $data),
                'body' => $this->replaceVariables($template['body'], $data),
                'from_name' => $template['from_name'] ?? $this->config['app_name']
            ];
        } catch (Exception $e) {
            error_log('Template render error: ' . $e->getMessage());
            
            // Fallback template
            return [
                'subject' => 'Notification from ' . $this->config['app_name'],
                'body' => 'You have a new notification. Please check your account.',
                'from_name' => $this->config['app_name']
            ];
        }
    }

    /**
     * Render SMS template
     */
    public function renderSMS(string $type, array $data): string
    {
        try {
            $templateFile = $this->smsTemplatesDir . "/{$type}.txt";

            if (!file_exists($templateFile)) {
                throw new Exception("SMS template not found: {$type}");
            }

            $template = file_get_contents($templateFile);

            // Merge with app config
            $data = array_merge($this->config, $data);

            return $this->replaceVariables($template, $data);
        } catch (Exception $e) {
            error_log('SMS template render error: ' . $e->getMessage());
            
            // Fallback message
            return "You have a new notification from {$this->config['app_name']}.";
        }
    }

    /**
     * Replace template variables with data
     */
    private function replaceVariables(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            // Only replace string and numeric values
            if (is_string($value) || is_numeric($value)) {
                // Escape HTML for security
                $safeValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                
                // Replace {{variable}} and {variable}
                $template = str_replace([
                    '{{' . $key . '}}',
                    '{' . $key . '}'
                ], $safeValue, $template);
            }
        }

        return $template;
    }

    /**
     * Validate template exists
     */
    public function templateExists(string $type, string $channel = 'email'): bool
    {
        if ($channel === 'email') {
            return file_exists($this->emailTemplatesDir . "/{$type}.json");
        } else {
            return file_exists($this->smsTemplatesDir . "/{$type}.txt");
        }
    }
}

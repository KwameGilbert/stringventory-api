<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * EmailService
 * 
 * Handles all email sending operations using external template files
 */
class EmailService
{
    private PHPMailer $mailer;
    private string $fromEmail;
    private string $fromName;
    private string $templatePath;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        
        // Configure SMTP
        $this->configureSMTP();
        
        $this->fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@eventic.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Eventic';
        $this->templatePath = dirname(__DIR__, 2) . '/templates/email/';
    }

    /**
     * Configure SMTP settings
     */
    private function configureSMTP(): void
    {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $_ENV['MAIL_USERNAME'] ?? 'eventic@gmail.com';
            $this->mailer->Password = $_ENV['MAIL_PASSWORD'] ?? 'eventic123';
            $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
            $this->mailer->Port = (int)($_ENV['MAIL_PORT'] ?? 587);
            $this->mailer->CharSet = 'UTF-8';
        } catch (Exception $e) {
            error_log('SMTP configuration error: ' . $e->getMessage());
        }
    }

    // ========================================
    // TEMPLATE LOADING SYSTEM
    // ========================================

    /**
     * Load a template configuration from JSON file
     */
    private function loadTemplateConfig(string $templateName): ?array
    {
        $jsonPath = $this->templatePath . $templateName . '.json';
        
        if (!file_exists($jsonPath)) {
            error_log("Email template config not found: {$jsonPath}");
            return null;
        }
        
        $config = json_decode(file_get_contents($jsonPath), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON in email template config: {$jsonPath}");
            return null;
        }
        
        return $config;
    }

    /**
     * Load HTML content from a template file
     */
    private function loadTemplateContent(string $filename): ?string
    {
        $htmlPath = $this->templatePath . $filename;
        
        if (!file_exists($htmlPath)) {
            error_log("Email template content not found: {$htmlPath}");
            return null;
        }
        
        return file_get_contents($htmlPath);
    }

    /**
     * Load the base template
     */
    private function loadBaseTemplate(): string
    {
        $basePath = $this->templatePath . 'base.html';
        
        if (!file_exists($basePath)) {
            error_log("Base email template not found: {$basePath}");
            return '{{content}}'; // Fallback to just content
        }
        
        return file_get_contents($basePath);
    }

    /**
     * Replace placeholders in template with actual values
     */
    private function replacePlaceholders(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string)$value, $template);
        }
        
        return $template;
    }

    /**
     * Get common template variables
     */
    private function getCommonVariables(): array
    {
        return [
            'app_url' => $_ENV['FRONTEND_URL'] ?? 'https://eventic.com',
            'support_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'support@eventic.com',
            'year' => date('Y'),
            'social_facebook' => $_ENV['SOCIAL_FACEBOOK'] ?? '#',
            'social_twitter' => $_ENV['SOCIAL_TWITTER'] ?? '#',
            'social_instagram' => $_ENV['SOCIAL_INSTAGRAM'] ?? '#',
        ];
    }

    /**
     * Build a complete email from template
     */
    private function buildEmailFromTemplate(string $templateName, array $variables = []): ?array
    {
        // Load template config
        $config = $this->loadTemplateConfig($templateName);
        if (!$config) {
            return null;
        }
        
        // Load content template
        $contentHtml = $this->loadTemplateContent($config['content_file']);
        if (!$contentHtml) {
            return null;
        }
        
        // Merge variables with common ones
        $allVariables = array_merge($this->getCommonVariables(), $variables);
        
        // Replace placeholders in content
        $content = $this->replacePlaceholders($contentHtml, $allVariables);
        
        // Load base template and inject content
        $baseTemplate = $this->loadBaseTemplate();
        $allVariables['content'] = $content;
        $allVariables['subject'] = $config['subject'];
        $allVariables['preheader'] = $config['preheader'] ?? '';
        
        // Build final HTML
        $finalHtml = $this->replacePlaceholders($baseTemplate, $allVariables);
        
        return [
            'subject' => $this->replacePlaceholders($config['subject'], $allVariables),
            'body' => $finalHtml,
        ];
    }

    // ========================================
    // EMAIL SENDING METHODS
    // ========================================

    /**
     * Send welcome email on registration
     */
    public function sendWelcomeEmail(User $user): bool
    {
        try {
            $email = $this->buildEmailFromTemplate('welcome', [
                'user_name' => htmlspecialchars($user->name),
                'user_email' => htmlspecialchars($user->email),
            ]);
            
            if (!$email) {
                error_log('Failed to build welcome email template');
                return false;
            }

            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($user->email, $user->name);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $email['subject'];
            $this->mailer->Body = $email['body'];
            
            $this->mailer->send();
            return true;

        } catch (Exception $e) {
            error_log('Welcome email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email verification email
     */
    public function sendEmailVerificationEmail(User $user, string $verificationUrl): bool
    {
        try {
            $email = $this->buildEmailFromTemplate('email_verification', [
                'user_name' => htmlspecialchars($user->name),
                'user_email' => htmlspecialchars($user->email),
                'verification_url' => $verificationUrl,
            ]);
            
            if (!$email) {
                error_log('Failed to build verification email template');
                return false;
            }

            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($user->email, $user->name);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $email['subject'];
            $this->mailer->Body = $email['body'];
            
            $this->mailer->send();
            return true;

        } catch (Exception $e) {
            error_log('Verification email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(User $user, string $token): bool
    {
        try {
            $resetUrl = ($_ENV['FRONTEND_URL'] ?? 'http://localhost:5173') . "/reset-password?token={$token}&email=" . urlencode($user->email);
            
            $email = $this->buildEmailFromTemplate('password_reset', [
                'user_name' => htmlspecialchars($user->name),
                'user_email' => htmlspecialchars($user->email),
                'reset_url' => $resetUrl,
            ]);
            
            if (!$email) {
                error_log('Failed to build password reset email template');
                return false;
            }

            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($user->email, $user->name);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $email['subject'];
            $this->mailer->Body = $email['body'];
            
            $this->mailer->send();
            return true;

        } catch (Exception $e) {
            error_log('Password reset email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password changed confirmation email
     */
    public function sendPasswordChangedEmail(User $user): bool
    {
        try {
            $email = $this->buildEmailFromTemplate('password_changed', [
                'user_name' => htmlspecialchars($user->name),
                'user_email' => htmlspecialchars($user->email),
                'timestamp' => date('F j, Y \a\t g:i A T'),
            ]);
            
            if (!$email) {
                error_log('Failed to build password changed email template');
                return false;
            }

            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($user->email, $user->name);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $email['subject'];
            $this->mailer->Body = $email['body'];
            
            $this->mailer->send();
            return true;

        } catch (Exception $e) {
            error_log('Password changed email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generic send method for NotificationService compatibility
     */
    public function send(string $to, string $subject, string $body, ?string $fromName = null): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $fromName ?? $this->fromName);
            $this->mailer->addAddress($to);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            $this->mailer->send();
            return true;

        } catch (Exception $e) {
            error_log('Email send error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a custom email using a template
     * 
     * @param string $templateName Name of the template (without extension)
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param array $variables Template variables
     * @return bool
     */
    public function sendFromTemplate(string $templateName, string $toEmail, string $toName, array $variables = []): bool
    {
        try {
            $email = $this->buildEmailFromTemplate($templateName, $variables);
            
            if (!$email) {
                error_log("Failed to build email template: {$templateName}");
                return false;
            }

            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($toEmail, $toName);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $email['subject'];
            $this->mailer->Body = $email['body'];
            
            $this->mailer->send();
            return true;

        } catch (Exception $e) {
            error_log("Template email error ({$templateName}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send ticket confirmation email after successful payment
     * 
     * @param \App\Models\Order $order The order with tickets
     * @param array $tickets Array of ticket details
     * @return bool
     */
    public function sendTicketConfirmationEmail($order, array $tickets = []): bool
    {
        try {
            error_log('=== SENDING TICKET CONFIRMATION EMAIL ===');
            error_log('Order ID: ' . $order->id);
            error_log('Order User ID: ' . $order->user_id);
            error_log('Customer Email: ' . ($order->customer_email ?? 'NULL'));
            
            // Get user
            $user = \App\Models\User::find($order->user_id);
            if (!$user) {
                error_log('Ticket confirmation email: User not found for order ' . $order->id);
                return false;
            }
            error_log('User found: ' . $user->email);

            // Make sure order items are loaded
            if (!$order->relationLoaded('items')) {
                error_log('Loading order items...');
                $order->load(['items.event', 'items.ticketType']);
            }
            
            error_log('Order items count: ' . $order->items->count());

            // Build tickets list HTML
            $ticketsListHtml = $this->buildTicketsListHtml($order, $tickets);
            error_log('Tickets list HTML length: ' . strlen($ticketsListHtml));
            
            // Calculate total tickets
            $totalTickets = 0;
            foreach ($order->items as $item) {
                $totalTickets += $item->quantity;
            }
            error_log('Total tickets: ' . $totalTickets);

            // Get currency
            $currency = $_ENV['CURRENCY'] ?? 'GHS';
            
            // Build email
            $email = $this->buildEmailFromTemplate('ticket_confirmation', [
                'user_name' => htmlspecialchars($order->customer_name ?? $user->name),
                'user_email' => htmlspecialchars($order->customer_email ?? $user->email),
                'order_reference' => $order->payment_reference ?? ('EVT-' . $order->id),
                'total_amount' => number_format((float)$order->total_amount, 2),
                'currency' => $currency,
                'total_tickets' => $totalTickets,
                'tickets_list' => $ticketsListHtml,
                'tickets_url' => ($_ENV['FRONTEND_URL'] ?? 'https://eventic.com') . '/my-tickets',
            ]);
            
            if (!$email) {
                error_log('Failed to build ticket confirmation email template - template returned null');
                return false;
            }
            
            error_log('Email template built successfully');
            error_log('Email subject: ' . $email['subject']);

            $recipientEmail = $order->customer_email ?? $user->email;
            $recipientName = $order->customer_name ?? $user->name;
            
            error_log('Sending to: ' . $recipientEmail);

            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($recipientEmail, $recipientName);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $email['subject'];
            $this->mailer->Body = $email['body'];
            
            $this->mailer->send();
            error_log('=== TICKET CONFIRMATION EMAIL SENT SUCCESSFULLY ===');
            return true;

        } catch (Exception $e) {
            error_log('Ticket confirmation email error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Build HTML for tickets list in confirmation email
     */
    private function buildTicketsListHtml($order, array $tickets = []): string
    {
        $html = '';
        
        // Group tickets by event
        $ticketsByEvent = [];
        
        foreach ($order->items as $item) {
            $event = $item->event;
            $ticketType = $item->ticketType;
            
            if (!$event) continue;
            
            $eventId = $event->id;
            if (!isset($ticketsByEvent[$eventId])) {
                $ticketsByEvent[$eventId] = [
                    'event' => $event,
                    'items' => [],
                ];
            }
            
            $ticketsByEvent[$eventId]['items'][] = [
                'type_name' => $ticketType->name ?? 'Standard',
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ];
        }

        // Build HTML for each event
        foreach ($ticketsByEvent as $eventData) {
            $event = $eventData['event'];
            $eventDate = $event->start_date 
                ? date('l, F j, Y \a\t g:i A', strtotime($event->start_date)) 
                : 'TBA';
            
            $html .= "
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin-bottom: 16px; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;'>
                <tr>
                    <td style='padding: 16px; background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: #ffffff;'>
                        <p style='margin: 0; font-size: 16px; font-weight: 600;'>" . htmlspecialchars($event->title) . "</p>
                        <p style='margin: 4px 0 0 0; font-size: 13px; opacity: 0.9;'>📅 {$eventDate}</p>
                        <p style='margin: 4px 0 0 0; font-size: 13px; opacity: 0.9;'>📍 " . htmlspecialchars($event->venue ?? 'Venue TBA') . "</p>
                    </td>
                </tr>
                <tr>
                    <td style='padding: 16px;'>
                        <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>";
            
            foreach ($eventData['items'] as $item) {
                $currency = $_ENV['CURRENCY'] ?? 'GHS';
                $html .= "
                            <tr>
                                <td style='padding: 8px 0;'>
                                    <span style='font-size: 14px; color: #374151;'>🎫 " . htmlspecialchars($item['type_name']) . "</span>
                                </td>
                                <td style='padding: 8px 0; text-align: center;'>
                                    <span style='font-size: 14px; color: #6b7280;'>x " . $item['quantity'] . "</span>
                                </td>
                                <td style='padding: 8px 0; text-align: right;'>
                                    <span style='font-size: 14px; font-weight: 600; color: #111827;'>{$currency} " . number_format($item['unit_price'] * $item['quantity'], 2) . "</span>
                                </td>
                            </tr>";
            }
            
            $html .= "
                        </table>
                    </td>
                </tr>
            </table>";
        }

        return $html;
    }
}


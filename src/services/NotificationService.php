<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\EmailService;
use App\Services\SMSService;
use App\Services\NotificationQueue;
use App\Services\TemplateEngine;
use Exception;

/**
 * NotificationService - Unified notification system
 * 
 * Simple, secure, and scalable notification handler
 * Supports Email and SMS with async queue processing
 */
class NotificationService
{
    private EmailService $emailService;
    private SMSService $smsService;
    private NotificationQueue $queue;
    private TemplateEngine $templateEngine;
    private bool $useQueue;
    
    public function __construct(
        EmailService $emailService,
        SMSService $smsService,
        NotificationQueue $queue,
        TemplateEngine $templateEngine
    ) {
        $this->emailService = $emailService;
        $this->smsService = $smsService;
        $this->queue = $queue;
        $this->templateEngine = $templateEngine;
        $this->useQueue = ($_ENV['USE_NOTIFICATION_QUEUE'] ?? 'true') === 'true';
    }

    // ==================== ORDER NOTIFICATIONS ====================

    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation(array $orderData): bool
    {
        return $this->send([
            'type' => 'order_confirmation',
            'to_email' => $orderData['customer_email'],
            'to_phone' => $orderData['customer_phone'] ?? null,
            'data' => $orderData,
            'channels' => ['email', 'sms'],
            'priority' => 'high'
        ]);
    }

    /**
     * Send payment receipt
     */
    public function sendPaymentReceipt(array $orderData): bool
    {
        return $this->send([
            'type' => 'payment_receipt',
            'to_email' => $orderData['customer_email'],
            'to_phone' => $orderData['customer_phone'] ?? null,
            'data' => $orderData,
            'channels' => ['email', 'sms'],
            'priority' => 'critical'
        ]);
    }

    /**
     * Send tickets with QR codes
     */
    public function sendTickets(array $orderData): bool
    {
        return $this->send([
            'type' => 'ticket_delivery',
            'to_email' => $orderData['customer_email'],
            'to_phone' => $orderData['customer_phone'] ?? null,
            'data' => $orderData,
            'channels' => ['email', 'sms'],
            'priority' => 'critical'
        ]);
    }

    /**
     * Send payment failed notification
     */
    public function sendPaymentFailed(array $orderData): bool
    {
        return $this->send([
            'type' => 'payment_failed',
            'to_email' => $orderData['customer_email'],
            'data' => $orderData,
            'channels' => ['email'],
            'priority' => 'high'
        ]);
    }

    /**
     * Send order cancelled notification
     */
    public function sendOrderCancelled(array $orderData): bool
    {
        return $this->send([
            'type' => 'order_cancelled',
            'to_email' => $orderData['customer_email'],
            'data' => $orderData,
            'channels' => ['email'],
            'priority' => 'medium'
        ]);
    }

    /**
     * Send new sale notification to organizer
     */
    public function sendNewSaleNotification(array $orderData, string $organizerEmail): bool
    {
        return $this->send([
            'type' => 'new_sale',
            'to_email' => $organizerEmail,
            'data' => $orderData,
            'channels' => ['email'],
            'priority' => 'medium'
        ]);
    }

    // ==================== TICKET NOTIFICATIONS ====================

    /**
     * Send ticket admitted/scanned confirmation
     */
    public function sendTicketAdmitted(array $ticketData): bool
    {
        return $this->send([
            'type' => 'ticket_admitted',
            'to_email' => $ticketData['attendee_email'] ?? null,
            'to_phone' => $ticketData['attendee_phone'] ?? null,
            'data' => $ticketData,
            'channels' => ['email', 'sms'],
            'priority' => 'high'
        ]);
    }

    // ==================== EVENT NOTIFICATIONS ====================

    /**
     * Send event created confirmation
     */
    public function sendEventCreated(array $eventData, string $organizerEmail): bool
    {
        return $this->send([
            'type' => 'event_created',
            'to_email' => $organizerEmail,
            'data' => $eventData,
            'channels' => ['email'],
            'priority' => 'medium'
        ]);
    }

    /**
     * Send event updated notification to attendees
     */
    public function sendEventUpdated(array $eventData, array $attendeeEmails): bool
    {
        foreach ($attendeeEmails as $email) {
            $this->send([
                'type' => 'event_updated',
                'to_email' => $email,
                'data' => $eventData,
                'channels' => ['email'],
                'priority' => 'high'
            ]);
        }
        return true;
    }

    /**
     * Send event cancelled notification
     */
    public function sendEventCancelled(array $eventData, array $attendees): bool
    {
        foreach ($attendees as $attendee) {
            $this->send([
                'type' => 'event_cancelled',
                'to_email' => $attendee['email'],
                'to_phone' => $attendee['phone'] ?? null,
                'data' => $eventData,
                'channels' => ['email', 'sms'],
                'priority' => 'critical'
            ]);
        }
        return true;
    }

    /**
     * Send event reminder (24 hours before)
     */
    public function sendEventReminder(array $eventData, array $attendees): bool
    {
        foreach ($attendees as $attendee) {
            $this->send([
                'type' => 'event_reminder',
                'to_email' => $attendee['email'],
                'to_phone' => $attendee['phone'] ?? null,
                'data' => $eventData,
                'channels' => ['email', 'sms'],
                'priority' => 'medium'
            ]);
        }
        return true;
    }

    // ==================== AUTH NOTIFICATIONS ====================

    /**
     * Send welcome email after registration
     */
    public function sendWelcomeEmail(array $userData, string $verificationLink): bool
    {
        $userData['verification_link'] = $verificationLink;
        
        return $this->send([
            'type' => 'welcome',
            'to_email' => $userData['email'],
            'to_phone' => $userData['phone'] ?? null,
            'data' => $userData,
            'channels' => ['email', 'sms'],
            'priority' => 'critical'
        ]);
    }

    /**
     * Send email verification
     */
    public function sendEmailVerification(string $email, string $verificationCode): bool
    {
        return $this->send([
            'type' => 'email_verification',
            'to_email' => $email,
            'data' => ['verification_code' => $verificationCode],
            'channels' => ['email'],
            'priority' => 'critical'
        ]);
    }

    /**
     * Send login alert for new device
     */
    public function sendLoginAlert(array $userData, array $loginDetails): bool
    {
        return $this->send([
            'type' => 'login_alert',
            'to_email' => $userData['email'],
            'to_phone' => $userData['phone'] ?? null,
            'data' => array_merge($userData, $loginDetails),
            'channels' => ['email', 'sms'],
            'priority' => 'critical'
        ]);
    }

    // ==================== PASSWORD RESET NOTIFICATIONS ====================

    /**
     * Send password reset OTP
     */
    public function sendPasswordResetOTP(string $email, string $phone, string $otp): bool
    {
        return $this->send([
            'type' => 'password_reset_otp',
            'to_email' => $email,
            'to_phone' => $phone,
            'data' => ['otp' => $otp, 'expires_in' => '15 minutes'],
            'channels' => ['email', 'sms'],
            'priority' => 'critical'
        ]);
    }

    /**
     * Send password changed confirmation
     */
    public function sendPasswordChanged(string $email, string $phone = null): bool
    {
        return $this->send([
            'type' => 'password_changed',
            'to_email' => $email,
            'to_phone' => $phone,
            'data' => ['timestamp' => date('Y-m-d H:i:s')],
            'channels' => ['email', 'sms'],
            'priority' => 'critical'
        ]);
    }

    // ==================== VOTING NOTIFICATIONS ====================

    /**
     * Send vote payment confirmation
     */
    public function sendVoteConfirmation(array $voteData): bool
    {
        return $this->send([
            'type' => 'vote_confirmed',
            'to_email' => $voteData['voter_email'],
            'data' => $voteData,
            'channels' => ['email'],
            'priority' => 'high'
        ]);
    }

    /**
     * Send vote initiated (pending payment)
     */
    public function sendVoteInitiated(array $voteData): bool
    {
        return $this->send([
            'type' => 'vote_initiated',
            'to_email' => $voteData['voter_email'],
            'data' => $voteData,
            'channels' => ['email'],
            'priority' => 'medium'
        ]);
    }

    /**
     * Send voting period started notification
     */
    public function sendVotingStarted(array $categoryData, array $recipients): bool
    {
        foreach ($recipients as $recipient) {
            $this->send([
                'type' => 'voting_started',
                'to_email' => $recipient['email'],
                'to_phone' => $recipient['phone'] ?? null,
                'data' => $categoryData,
                'channels' => ['email', 'sms'],
                'priority' => 'medium'
            ]);
        }
        return true;
    }

    /**
     * Send voting ending soon reminder
     */
    public function sendVotingEndingSoon(array $categoryData, array $recipients): bool
    {
        foreach ($recipients as $recipient) {
            $this->send([
                'type' => 'voting_ending',
                'to_email' => $recipient['email'],
                'data' => $categoryData,
                'channels' => ['email'],
                'priority' => 'medium'
            ]);
        }
        return true;
    }

    // ==================== ORGANIZER NOTIFICATIONS ====================

    /**
     * Send organizer account approved
     */
    public function sendOrganizerApproved(array $organizerData): bool
    {
        return $this->send([
            'type' => 'organizer_approved',
            'to_email' => $organizerData['email'],
            'data' => $organizerData,
            'channels' => ['email'],
            'priority' => 'high'
        ]);
    }

    /**
     * Send payout notification
     */
    public function sendPayoutProcessed(array $payoutData, string $organizerEmail): bool
    {
        return $this->send([
            'type' => 'payout_processed',
            'to_email' => $organizerEmail,
            'data' => $payoutData,
            'channels' => ['email'],
            'priority' => 'high'
        ]);
    }

    // ==================== CORE NOTIFICATION ENGINE ====================

    /**
     * Main send method - handles all notification routing
     * 
     * @param array $notification Notification configuration
     * @return bool Success status
     */
    private function send(array $notification): bool
    {
        try {
            // Validate notification data
            if (!$this->validateNotification($notification)) {
                error_log('Invalid notification data: ' . json_encode($notification));
                return false;
            }

            // Sanitize data
            $notification = $this->sanitizeNotification($notification);

            // Use queue for async processing or send immediately
            if ($this->useQueue && ($notification['priority'] ?? 'medium') !== 'critical') {
                return $this->queue->enqueue($notification);
            } else {
                return $this->sendNow($notification);
            }
        } catch (Exception $e) {
            error_log('Notification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification immediately (synchronous)
     */
    private function sendNow(array $notification): bool
    {
        $success = true;
        $channels = $notification['channels'] ?? ['email'];

        // Send email
        if (in_array('email', $channels) && !empty($notification['to_email'])) {
            $success = $this->sendEmail($notification) && $success;
        }

        // Send SMS
        if (in_array('sms', $channels) && !empty($notification['to_phone'])) {
            $success = $this->sendSMS($notification) && $success;
        }

        return $success;
    }

    /**
     * Send email notification
     */
    private function sendEmail(array $notification): bool
    {
        try {
            $template = $this->templateEngine->renderEmail(
                $notification['type'],
                $notification['data']
            );

            return $this->emailService->send(
                $notification['to_email'],
                $template['subject'],
                $template['body'],
                $template['from_name'] ?? 'Eventic'
            );
        } catch (Exception $e) {
            error_log('Email send error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSMS(array $notification): bool
    {
        try {
            $message = $this->templateEngine->renderSMS(
                $notification['type'],
                $notification['data']
            );

            return $this->smsService->send(
                $notification['to_phone'],
                $message
            );
        } catch (Exception $e) {
            error_log('SMS send error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate notification data
     */
    private function validateNotification(array $notification): bool
    {
        // Must have type
        if (empty($notification['type'])) {
            return false;
        }

        // Must have at least one recipient
        if (empty($notification['to_email']) && empty($notification['to_phone'])) {
            return false;
        }

        // Must have data
        if (!isset($notification['data']) || !is_array($notification['data'])) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize notification data for security
     */
    private function sanitizeNotification(array $notification): array
    {
        // Sanitize email
        if (!empty($notification['to_email'])) {
            $notification['to_email'] = filter_var(
                $notification['to_email'],
                FILTER_SANITIZE_EMAIL
            );
        }

        // Sanitize phone (remove non-numeric except +)
        if (!empty($notification['to_phone'])) {
            $notification['to_phone'] = preg_replace('/[^0-9+]/', '', $notification['to_phone']);
        }

        // Sanitize data recursively
        if (isset($notification['data']) && is_array($notification['data'])) {
            $notification['data'] = $this->sanitizeData($notification['data']);
        }

        return $notification;
    }

    /**
     * Recursively sanitize data array
     */
    private function sanitizeData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }
        return $data;
    }
}

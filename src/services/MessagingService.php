<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\MessagingCampaign;
use App\Models\MessagingCampaignRecipient;
use App\Models\MessagingTemplate;
use Exception;

class MessagingService
{
    private EmailService $emailService;
    private SMSService $smsService;

    public function __construct(EmailService $emailService, SMSService $smsService)
    {
        $this->emailService = $emailService;
        $this->smsService = $smsService;
    }

    public function sendBulk(array $payload, ?int $createdBy = null): array
    {
        $recipientIds = array_values(array_unique(array_map('intval', $payload['recipientIds'] ?? [])));
        $recipientIds = array_filter($recipientIds, static fn (int $id): bool => $id > 0);

        if (empty($recipientIds)) {
            throw new Exception('At least one valid recipientId is required.');
        }

        $template = null;
        if (!empty($payload['templateId'])) {
            $template = MessagingTemplate::where('id', (int)$payload['templateId'])
                ->where('isActive', true)
                ->first();
            if (!$template) {
                throw new Exception('Template not found or inactive.');
            }
        }

        $channels = $this->normalizeChannels($payload['channels'] ?? null);
        $subject = trim((string)($payload['subject'] ?? ($template->subject ?? '')));
        $body = trim((string)($payload['body'] ?? ($template->body ?? '')));

        if ($body === '') {
            throw new Exception('Message body is required.');
        }

        $customers = Customer::whereIn('id', $recipientIds)->get();
        if ($customers->isEmpty()) {
            throw new Exception('No valid customers found for recipientIds.');
        }

        $campaign = MessagingCampaign::create([
            'createdBy' => $createdBy,
            'templateId' => $template ? $template->id : null,
            'subject' => $subject !== '' ? $subject : null,
            'body' => $body,
            'channels' => $channels,
            'status' => 'queued',
            'recipientCount' => 0,
            'deliveredCount' => 0,
            'failedCount' => 0,
            'metadata' => [
                'requestedRecipients' => count($recipientIds),
                'channels' => $channels,
            ],
        ]);

        $delivered = 0;
        $failed = 0;
        $attempts = 0;

        foreach ($customers as $customer) {
            foreach ($channels as $channel) {
                $attempts++;

                $recipient = MessagingCampaignRecipient::create([
                    'campaignId' => $campaign->id,
                    'customerId' => $customer->id,
                    'channel' => $channel,
                    'status' => 'pending',
                ]);

                $sent = false;
                $error = null;

                try {
                    $renderedBody = $this->renderTemplate($body, $customer->toArray());

                    if ($channel === 'email') {
                        if (!empty($customer->email)) {
                            $renderedSubject = $subject !== ''
                                ? $this->renderTemplate($subject, $customer->toArray())
                                : 'Message from Stringventory';
                            $sent = $this->emailService->send((string)$customer->email, $renderedSubject, nl2br($renderedBody));
                        } else {
                            $error = 'Customer has no email address.';
                        }
                    }

                    if ($channel === 'sms') {
                        if (!empty($customer->phone)) {
                            $smsBody = $this->stripHtml($renderedBody);
                            $sent = $this->smsService->send((string)$customer->phone, $smsBody);
                        } else {
                            $error = 'Customer has no phone number.';
                        }
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }

                if ($sent) {
                    $delivered++;
                    $recipient->update([
                        'status' => 'delivered',
                        'sentAt' => date('Y-m-d H:i:s'),
                        'error' => null,
                    ]);
                } else {
                    $failed++;
                    $recipient->update([
                        'status' => 'failed',
                        'error' => $error ?? 'Failed to send message.',
                    ]);
                }
            }
        }

        $status = 'completed';
        if ($delivered === 0 && $failed > 0) {
            $status = 'failed';
        } elseif ($delivered > 0 && $failed > 0) {
            $status = 'partial';
        }

        $campaign->update([
            'status' => $status,
            'recipientCount' => $attempts,
            'deliveredCount' => $delivered,
            'failedCount' => $failed,
        ]);

        return [
            'campaignId' => $campaign->id,
            'messageId' => $campaign->id,
            'templateId' => 'CAMP-' . $campaign->id,
            'status' => $campaign->status,
            'recipientCount' => $campaign->recipientCount,
            'deliveredCount' => $campaign->deliveredCount,
            'failedCount' => $campaign->failedCount,
            'channels' => $channels,
        ];
    }

    public function sendSingle(array $payload, ?int $createdBy = null): array
    {
        $recipientId = (int)($payload['recipientId'] ?? 0);
        if ($recipientId <= 0) {
            throw new Exception('A valid recipientId is required.');
        }

        return $this->sendBulk([
            'recipientIds' => [$recipientId],
            'body' => $payload['body'] ?? null,
            'subject' => $payload['subject'] ?? null,
            'templateId' => $payload['templateId'] ?? null,
            'channels' => $payload['channels'] ?? null,
        ], $createdBy);
    }

    public function getHistory(int $page = 1, int $limit = 10, ?int $recipientId = null): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $query = MessagingCampaign::query();

        if ($recipientId !== null) {
            $query->whereHas('recipients', function ($q) use ($recipientId) {
                $q->where('customerId', $recipientId);
            });
        }

        $total = (clone $query)->count();
        $campaigns = $query
            ->orderBy('createdAt', 'desc')
            ->forPage($page, $limit)
            ->get();

        return [
            'messages' => $campaigns->map(function (MessagingCampaign $campaign): array {
                return [
                    'id' => $campaign->id,
                    'body' => $campaign->body,
                    'subject' => $campaign->subject,
                    'recipientCount' => $campaign->recipientCount,
                    'status' => $campaign->status,
                    'channels' => $campaign->channels,
                    'createdAt' => $campaign->createdAt ? $campaign->createdAt->toIso8601String() : null,
                ];
            })->toArray(),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit),
            ],
        ];
    }

    public function getMessageDetails(int $campaignId): ?array
    {
        $campaign = MessagingCampaign::with(['recipients.customer'])->find($campaignId);
        if (!$campaign) {
            return null;
        }

        $recipients = $campaign->recipients->map(function (MessagingCampaignRecipient $recipient): array {
            $customer = $recipient->customer;
            $name = $customer ? (($customer->businessName ?: trim(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? ''))) ?: 'Unknown') : 'Unknown';

            return [
                'id' => $customer ? $customer->id : null,
                'name' => $name,
                'status' => $recipient->status,
                'channel' => $recipient->channel,
                'error' => $recipient->error,
                'sentAt' => $recipient->sentAt ? $recipient->sentAt->toIso8601String() : null,
            ];
        })->toArray();

        return [
            'id' => $campaign->id,
            'subject' => $campaign->subject,
            'body' => $campaign->body,
            'status' => $campaign->status,
            'channels' => $campaign->channels,
            'recipients' => $recipients,
            'stats' => [
                'failed' => $campaign->failedCount,
                'delivered' => $campaign->deliveredCount,
                'total' => $campaign->recipientCount,
            ],
            'createdAt' => $campaign->createdAt ? $campaign->createdAt->toIso8601String() : null,
        ];
    }

    public function getTemplates(): array
    {
        return MessagingTemplate::where('isActive', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'channel', 'subject', 'body'])
            ->toArray();
    }

    public function createTemplate(array $payload): array
    {
        $name = trim((string)($payload['name'] ?? ''));
        $body = trim((string)($payload['body'] ?? ''));

        if ($name === '') {
            throw new Exception('Template name is required.');
        }

        if ($body === '') {
            throw new Exception('Template body is required.');
        }

        $channel = strtolower(trim((string)($payload['channel'] ?? 'multi')));
        if (!in_array($channel, ['email', 'sms', 'multi'], true)) {
            $channel = 'multi';
        }

        $template = MessagingTemplate::create([
            'name' => $name,
            'channel' => $channel,
            'subject' => isset($payload['subject']) ? trim((string)$payload['subject']) : null,
            'body' => $body,
            'isActive' => isset($payload['isActive']) ? (bool)$payload['isActive'] : true,
        ]);

        return $template->toArray();
    }

    public function updateTemplate(int $templateId, array $payload): ?array
    {
        $template = MessagingTemplate::find($templateId);
        if (!$template) {
            return null;
        }

        $updates = [];

        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                throw new Exception('Template name cannot be empty.');
            }
            $updates['name'] = $name;
        }

        if (array_key_exists('body', $payload)) {
            $body = trim((string)$payload['body']);
            if ($body === '') {
                throw new Exception('Template body cannot be empty.');
            }
            $updates['body'] = $body;
        }

        if (array_key_exists('subject', $payload)) {
            $updates['subject'] = $payload['subject'] !== null ? trim((string)$payload['subject']) : null;
        }

        if (array_key_exists('channel', $payload)) {
            $channel = strtolower(trim((string)$payload['channel']));
            if (!in_array($channel, ['email', 'sms', 'multi'], true)) {
                throw new Exception('Invalid channel. Allowed values: email, sms, multi.');
            }
            $updates['channel'] = $channel;
        }

        if (array_key_exists('isActive', $payload)) {
            $updates['isActive'] = (bool)$payload['isActive'];
        }

        if (!empty($updates)) {
            $template->update($updates);
        }

        return $template->fresh()->toArray();
    }

    public function deleteTemplate(int $templateId): bool
    {
        $template = MessagingTemplate::find($templateId);
        if (!$template) {
            return false;
        }

        $template->delete();
        return true;
    }

    private function normalizeChannels($channels): array
    {
        if (!is_array($channels) || empty($channels)) {
            return ['email', 'sms'];
        }

        $allowed = ['email', 'sms'];
        $normalized = [];

        foreach ($channels as $channel) {
            $value = strtolower(trim((string)$channel));
            if (in_array($value, $allowed, true) && !in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return !empty($normalized) ? $normalized : ['email', 'sms'];
    }

    private function renderTemplate(string $text, array $customer): string
    {
        $name = trim((string)(($customer['businessName'] ?? '') ?: (($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? ''))));

        $replacements = [
            '{{name}}' => $name,
            '{{firstName}}' => (string)($customer['firstName'] ?? ''),
            '{{lastName}}' => (string)($customer['lastName'] ?? ''),
            '{{businessName}}' => (string)($customer['businessName'] ?? ''),
            '{{email}}' => (string)($customer['email'] ?? ''),
            '{{phone}}' => (string)($customer['phone'] ?? ''),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function stripHtml(string $content): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($content)) ?? '');
    }
}

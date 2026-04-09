<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helper\ResponseHelper;
use App\Services\MessagingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class MessagingController
{
    private MessagingService $messagingService;

    public function __construct(MessagingService $messagingService)
    {
        $this->messagingService = $messagingService;
    }

    public function bulkMessages(Request $request, Response $response): Response
    {
        try {
            $payload = (array)($request->getParsedBody() ?? []);
            $recipientIds = $payload['recipientIds'] ?? [];

            if (!is_array($recipientIds) || empty($recipientIds)) {
                return ResponseHelper::error($response, 'recipientIds is required and must be a non-empty array.', 422);
            }

            if (empty($payload['body']) && empty($payload['templateId'])) {
                return ResponseHelper::error($response, 'Either body or templateId is required.', 422);
            }

            $user = $request->getAttribute('user');
            $result = $this->messagingService->sendBulk($payload, $user ? (int)$user->id : null);

            return ResponseHelper::success(
                $response,
                'Successfully sent to ' . count($recipientIds) . ' customers',
                $result,
                201
            );
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to send bulk messages', 500, $e->getMessage());
        }
    }

    public function sendMessage(Request $request, Response $response): Response
    {
        try {
            $payload = (array)($request->getParsedBody() ?? []);

            if (empty($payload['recipientId'])) {
                return ResponseHelper::error($response, 'recipientId is required.', 422);
            }

            if (empty($payload['body']) && empty($payload['templateId'])) {
                return ResponseHelper::error($response, 'Either body or templateId is required.', 422);
            }

            $user = $request->getAttribute('user');
            $result = $this->messagingService->sendSingle($payload, $user ? (int)$user->id : null);

            return ResponseHelper::success($response, 'Message sent successfully', $result, 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to send message', 500, $e->getMessage());
        }
    }

    public function messages(Request $request, Response $response): Response
    {
        try {
            $query = $request->getQueryParams();
            $page = (int)($query['page'] ?? 1);
            $limit = (int)($query['limit'] ?? 10);
            $recipientId = isset($query['recipientId']) ? (int)$query['recipientId'] : null;

            $history = $this->messagingService->getHistory($page, $limit, $recipientId);

            return ResponseHelper::success($response, 'Messages fetched successfully', $history);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch messages', 500, $e->getMessage());
        }
    }

    public function messageDetails(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                return ResponseHelper::error($response, 'Invalid message ID', 422);
            }

            $details = $this->messagingService->getMessageDetails($id);
            if (!$details) {
                return ResponseHelper::error($response, 'Message not found', 404);
            }

            return ResponseHelper::success($response, 'Message details fetched successfully', $details);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch message details', 500, $e->getMessage());
        }
    }

    public function templates(Request $request, Response $response): Response
    {
        try {
            $templates = $this->messagingService->getTemplates();
            return ResponseHelper::success($response, 'Templates fetched successfully', ['templates' => $templates]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch templates', 500, $e->getMessage());
        }
    }

    public function createTemplate(Request $request, Response $response): Response
    {
        try {
            $payload = (array)($request->getParsedBody() ?? []);
            $template = $this->messagingService->createTemplate($payload);

            return ResponseHelper::success($response, 'Template created successfully', ['template' => $template], 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create template', 422, $e->getMessage());
        }
    }

    public function updateTemplate(Request $request, Response $response, array $args): Response
    {
        try {
            $templateId = (int)($args['id'] ?? 0);
            if ($templateId <= 0) {
                return ResponseHelper::error($response, 'Invalid template ID', 422);
            }

            $payload = (array)($request->getParsedBody() ?? []);
            $template = $this->messagingService->updateTemplate($templateId, $payload);

            if (!$template) {
                return ResponseHelper::error($response, 'Template not found', 404);
            }

            return ResponseHelper::success($response, 'Template updated successfully', ['template' => $template]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update template', 422, $e->getMessage());
        }
    }

    public function deleteTemplate(Request $request, Response $response, array $args): Response
    {
        try {
            $templateId = (int)($args['id'] ?? 0);
            if ($templateId <= 0) {
                return ResponseHelper::error($response, 'Invalid template ID', 422);
            }

            $deleted = $this->messagingService->deleteTemplate($templateId);
            if (!$deleted) {
                return ResponseHelper::error($response, 'Template not found', 404);
            }

            return ResponseHelper::success($response, 'Template deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete template', 500, $e->getMessage());
        }
    }
}

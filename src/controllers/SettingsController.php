<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Setting;
use App\Models\PaymentMethod;
use App\Models\UserSetting;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\NotificationService;
use Exception;

class SettingsController
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * Get Business Settings
     */
    public function getBusinessSettings(Request $request, Response $response): Response
    {
        try {
            $settings = Setting::getByCategory('business');
            if (!$settings) {
                return ResponseHelper::error($response, 'Business settings not found', 404);
            }
            
            return ResponseHelper::jsonResponse($response, [
                'status' => 'success',
                'message' => 'Business settings retrieved successfully',
                'data' => $settings
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve business settings', 500, $e->getMessage());
        }
    }

    /**
     * Update Business Settings
     */
    public function updateBusinessSettings(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Get existing to merge or just overwrite with provided fields
            $current = Setting::getByCategory('business') ?: [];
            $updated = array_merge($current, $data);
            $updated['updatedAt'] = date('c'); // ISO 8601
            
            Setting::updateCategory('business', $updated);
            
            // Notify admins about business settings change
            $this->notificationService->notifyAdmins(
                'settings_update',
                'Business Settings Updated',
                "Business configuration settings have been updated.",
                ['category' => 'business']
            );

            return ResponseHelper::jsonResponse($response, [
                'status' => 'success',
                'message' => 'Business settings updated successfully',
                'data' => [
                    'businessId' => $updated['businessId'] ?? null,
                    'businessName' => $updated['businessName'] ?? null,
                    'email' => $updated['email'] ?? null,
                    'updatedAt' => $updated['updatedAt']
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update business settings', 500, $e->getMessage());
        }
    }

    /**
     * Get Notification Settings
     */
    public function getNotificationSettings(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');
            $userId = (int) ($user->id ?? 0);
            
            $settings = UserSetting::getByUserAndCategory($userId, 'notifications');
            
            if (!$settings) {
                // Return defaults as requested
                $settings = [
                    'userId' => (string)$userId,
                    'emailNotifications' => [
                        'orderCreated' => true,
                        'orderShipped' => true,
                        'orderDelivered' => true,
                        'lowStock' => true,
                        'newCustomer' => true,
                        'expenseApproved' => false
                    ],
                    'smsNotifications' => [
                        'orderCreated' => true,
                        'lowStock' => true,
                        'urgentAlerts' => true
                    ],
                    'pushNotifications' => [
                        'orderCreated' => true,
                        'dashboardAlerts' => true
                    ],
                    'quietHours' => [
                        'enabled' => true,
                        'startTime' => '20:00',
                        'endTime' => '08:00'
                    ]
                ];
            } else {
                $settings['userId'] = (string)$userId;
            }
            
            return ResponseHelper::jsonResponse($response, [
                'status' => 'success',
                'message' => 'Notification settings retrieved successfully',
                'data' => $settings
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve notification settings', 500, $e->getMessage());
        }
    }

    /**
     * Update Notification Settings
     */
    public function updateNotificationSettings(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');
            $userId = (int) ($user->id ?? 0);
            $data = $request->getParsedBody();
            
            UserSetting::updateByUserAndCategory($userId, 'notifications', $data);
            
            return ResponseHelper::jsonResponse($response, [
                'status' => 'success',
                'message' => 'Notification settings updated successfully',
                'data' => [
                    'userId' => (string)$userId,
                    'updatedAt' => date('c')
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update notification settings', 500, $e->getMessage());
        }
    }

    /**
     * Get Payment Settings
     */
    public function getPaymentSettings(Request $request, Response $response): Response
    {
        try {
            $globalConfig = Setting::getByCategory('payment') ?: [
                'businessId' => 'business_88234',
                'defaultPaymentMethod' => 'pm_001',
                'autoReconciliation' => true,
                'receiptEmail' => 'accounting@johnsstore.com'
            ];
            
            $methods = PaymentMethod::all();
            
            $data = array_merge($globalConfig, [
                'paymentMethods' => $methods->toArray()
            ]);
            
            return ResponseHelper::jsonResponse($response, [
                'status' => 'success',
                'message' => 'Payment settings retrieved successfully',
                'data' => $data
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve payment settings', 500, $e->getMessage());
        }
    }

    /**
     * Update Payment Settings
     */
    public function updatePaymentSettings(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Extract enabled methods if provided
            if (isset($data['enabledMethods'])) {
                PaymentMethod::whereIn('id', $data['enabledMethods'])->update(['enabled' => true]);
                PaymentMethod::whereNotIn('id', $data['enabledMethods'])->update(['enabled' => false]);
            }
            
            // Update global payment config (limit to non-methods fields)
            $configKeys = ['defaultPaymentMethod', 'autoReconciliation', 'receiptEmail', 'businessId'];
            $newConfig = array_intersect_key($data, array_flip($configKeys));
            
            $current = Setting::getByCategory('payment') ?: [];
            Setting::updateCategory('payment', array_merge($current, $newConfig));
            
            // Notify admins about payment settings change
            $this->notificationService->notifyAdmins(
                'settings_update',
                'Payment Settings Updated',
                "Financial/Payment configuration settings have been updated.",
                ['category' => 'payment']
            );

            return ResponseHelper::jsonResponse($response, [
                'status' => 'success',
                'message' => 'Payment settings updated successfully',
                'data' => [
                    'businessId' => $data['businessId'] ?? $current['businessId'] ?? null,
                    'updatedAt' => date('c')
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update payment settings', 500, $e->getMessage());
        }
    }

    /**
     * Get API Settings
     */
    public function getApiSettings(Request $request, Response $response): Response
    {
        try {
            $settings = Setting::getByCategory('api');
            if (!$settings) {
                $settings = [
                    'apiKeyPublic' => 'pk_live_' . bin2hex(random_bytes(16)),
                    'webhookUrl' => '',
                    'rateLimit' => 1000
                ];
            }
            return ResponseHelper::success($response, 'API settings retrieved successfully', $settings);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve API settings', 500, $e->getMessage());
        }
    }

    /**
     * Regenerate API Key
     */
    public function regenerateApiKey(Request $request, Response $response): Response
    {
        try {
            $settings = Setting::getByCategory('api') ?: [];
            $settings['apiKey'] = 'sk_live_' . bin2hex(random_bytes(32));
            Setting::updateCategory('api', $settings);

            // Notify admins about API key regeneration
            $this->notificationService->notifyAdmins(
                'security_update',
                'API Key Regenerated',
                "The system API key has been regenerated.",
                ['category' => 'api']
            );

            return ResponseHelper::success($response, 'API key regenerated successfully', ['apiKey' => $settings['apiKey']]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to regenerate API key', 500, $e->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Setting;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class SettingsController
{
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
            return ResponseHelper::success($response, 'Business settings retrieved successfully', $settings);
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
            Setting::updateCategory('business', $data);
            $settings = Setting::getByCategory('business');
            return ResponseHelper::success($response, 'Business settings updated successfully', $settings);
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
            $settings = Setting::getByCategory('notifications');
            if (!$settings) {
                // Return default if not set
                $settings = [
                    'emailNotifications' => ['orderCreated' => true, 'lowStock' => true],
                    'smsNotifications' => ['orderCreated' => false, 'lowStock' => true],
                    'pushNotifications' => ['orderCreated' => true]
                ];
            }
            return ResponseHelper::success($response, 'Notification settings retrieved successfully', $settings);
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
            $data = $request->getParsedBody();
            Setting::updateCategory('notifications', $data);
            $settings = Setting::getByCategory('notifications');
            return ResponseHelper::success($response, 'Notification settings updated successfully', $settings);
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
            $settings = Setting::getByCategory('payment');
            if (!$settings) {
                $settings = [
                    'paymentMethods' => [
                        ['id' => 'pm_001', 'name' => 'Cash', 'type' => 'cash', 'enabled' => true],
                        ['id' => 'pm_002', 'name' => 'Bank Transfer', 'type' => 'bank', 'enabled' => true]
                    ],
                    'defaultPaymentMethod' => 'pm_001'
                ];
            }
            return ResponseHelper::success($response, 'Payment settings retrieved successfully', $settings);
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
            Setting::updateCategory('payment', $data);
            $settings = Setting::getByCategory('payment');
            return ResponseHelper::success($response, 'Payment settings updated successfully', $settings);
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
            return ResponseHelper::success($response, 'API key regenerated successfully', ['apiKey' => $settings['apiKey']]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to regenerate API key', 500, $e->getMessage());
        }
    }
}

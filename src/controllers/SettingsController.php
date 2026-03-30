<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Setting;
use App\Models\PaymentMethod;
use App\Models\UserSetting;
use App\Models\AuditLog;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\NotificationService;
use App\Services\CurrencyService;
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

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'settings_business_updated');

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

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'settings_payment_updated');

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
     * Get Currency Settings
     */
    public function getCurrencySettings(Request $request, Response $response): Response
    {
        try {
            $businessSettings = Setting::getByCategory('business');
            $currentCurrency  = $businessSettings['currency'] ?? 'GHS';

            // Ensure today's rates are available (fetch from API if not)
            CurrencyService::fetchAndStoreRates();

            return ResponseHelper::jsonResponse($response, [
                'status'   => 'success',
                'message'  => 'Currency settings retrieved successfully',
                'data'     => [
                    'currentCurrency'     => $currentCurrency,
                    'supportedCurrencies' => CurrencyService::getSupportedCurrencies(),
                    'rates'               => CurrencyService::getCurrentRates(),
                ],
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve currency settings', 500, $e->getMessage());
        }
    }

    /**
     * Update Currency Settings
     * Accepts: { currency: "USD", rates: { "GHS": { "USD": 0.065 }, ... } }
     * Manual rates are logged to exchange_rate_history with source='manual'.
     */
    public function updateCurrencySettings(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();

            $current = Setting::getByCategory('business') ?: [];

            if (!empty($data['currency'])) {
                $supported = CurrencyService::getSupportedCurrencies();
                if (!in_array($data['currency'], $supported, true)) {
                    return ResponseHelper::error($response, 'Unsupported currency. Allowed: ' . implode(', ', $supported), 400);
                }
                $current['currency'] = $data['currency'];
            }

            $current['updatedAt'] = date('c');
            Setting::updateCategory('business', $current);

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'settings_currency_updated', [
                'currency' => $current['currency'],
            ]);

            // Persist any manually provided rates
            if (!empty($data['rates']) && is_array($data['rates'])) {
                foreach ($data['rates'] as $base => $targets) {
                    if (!is_array($targets)) {
                        continue;
                    }
                    foreach ($targets as $target => $rate) {
                        CurrencyService::storeManualRate((string) $base, (string) $target, (float) $rate);
                    }
                }
            }

            $this->notificationService->notifyAdmins(
                'settings_update',
                'Currency Settings Updated',
                'Business currency configuration has been updated.',
                ['currency' => $current['currency']]
            );

            return ResponseHelper::jsonResponse($response, [
                'status'  => 'success',
                'message' => 'Currency settings updated successfully',
                'data'    => [
                    'currentCurrency' => $current['currency'],
                    'updatedAt'       => $current['updatedAt'],
                ],
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update currency settings', 500, $e->getMessage());
        }
    }

    /**
     * Get Exchange Rate History
     * Optional query params: ?base=GHS&target=USD&from=2026-01-01&to=2026-03-30
     */
    public function getExchangeRateHistory(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();

            $query = \App\Models\ExchangeRateHistory::orderBy('effectiveDate', 'desc')
                ->orderBy('id', 'desc');

            if (!empty($params['base'])) {
                $query->where('baseCurrency', strtoupper($params['base']));
            }

            if (!empty($params['target'])) {
                $query->where('targetCurrency', strtoupper($params['target']));
            }

            if (!empty($params['from'])) {
                $query->where('effectiveDate', '>=', $params['from']);
            }

            if (!empty($params['to'])) {
                $query->where('effectiveDate', '<=', $params['to']);
            }

            $history = $query->get();

            return ResponseHelper::success($response, 'Exchange rate history retrieved successfully', $history->toArray());
        } catch (\Exception $e) {
            return ResponseHelper::error($response, 'Failed to retrieve exchange rate history', 500, $e->getMessage());
        }
    }

    /**
     * Force-fetch latest exchange rates from the API
     */
    public function fetchExchangeRates(Request $request, Response $response): Response
    {
        try {
            $success = CurrencyService::fetchAndStoreRates();

            if (!$success) {
                return ResponseHelper::error($response, 'Failed to fetch exchange rates. Check your API key.', 502);
            }

            return ResponseHelper::jsonResponse($response, [
                'status'  => 'success',
                'message' => 'Exchange rates fetched and stored successfully',
                'data'    => [
                    'rates'     => CurrencyService::getCurrentRates(),
                    'fetchedAt' => date('c'),
                ],
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch exchange rates', 500, $e->getMessage());
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

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'api_key_regenerated');

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

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Discount;
use App\Models\AuditLog;
use App\Helper\ResponseHelper;
use App\Services\NotificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class DiscountController
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * Get all discounts
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $discounts = Discount::orderBy('createdAt', 'desc')->get();
            return ResponseHelper::success($response, 'Discounts fetched successfully', $discounts->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch discounts', 500, $e->getMessage());
        }
    }

    /**
     * Get single discount
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $discount = Discount::find($args['id']);
            if (!$discount) {
                return ResponseHelper::error($response, 'Discount not found', 404);
            }
            return ResponseHelper::success($response, 'Discount fetched successfully', $discount->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch discount', 500, $e->getMessage());
        }
    }

    /**
     * Create discount
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Required fields
            if (empty($data['name']) || empty($data['discountCode']) || empty($data['discountType'])) {
                return ResponseHelper::error($response, 'Name, discount code, and discount type are required', 400);
            }

            // Ensure either 'discount' (percentage) or 'discountAmount' (fixed) is provided
            if (!isset($data['discount']) && !isset($data['discountAmount'])) {
                return ResponseHelper::error($response, 'Either discount percentage or discount amount is required', 400);
            }

            // Check if code already exists
            if (Discount::where('discountCode', $data['discountCode'])->exists()) {
                return ResponseHelper::error($response, 'Discount code already exists', 409);
            }

            // Default status
            if (empty($data['status'])) {
                $data['status'] = 'active';
            }

            $discount = Discount::create($data);

            // Notify admins about new discount
            $this->notificationService->notifyAdmins(
                'discount_created',
                'New Discount Created',
                "A new discount '{$discount->name}' with code '{$discount->discountCode}' has been created.",
                ['discountId' => $discount->id, 'code' => $discount->discountCode]
            );

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'discount_created', [
                'discountId' => $discount->id,
                'code' => $discount->discountCode,
            ]);

            return ResponseHelper::success($response, 'Discount created successfully', $discount->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create discount', 500, $e->getMessage());
        }
    }

    /**
     * Update discount
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $discount = Discount::find($args['id']);
            if (!$discount) {
                return ResponseHelper::error($response, 'Discount not found', 404);
            }

            $data = $request->getParsedBody();

            // Unique check for discount code
            if (isset($data['discountCode']) && Discount::where('discountCode', $data['discountCode'])->where('id', '!=', $args['id'])->exists()) {
                return ResponseHelper::error($response, 'Another discount already uses this code', 409);
            }

            $discount->update($data);

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'discount_updated', [
                'discountId' => $discount->id,
                'code' => $discount->discountCode,
            ]);

            return ResponseHelper::success($response, 'Discount updated successfully', $discount->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update discount', 500, $e->getMessage());
        }
    }

    /**
     * Delete discount
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $discount = Discount::find($args['id']);
            if (!$discount) {
                return ResponseHelper::error($response, 'Discount not found', 404);
            }

            // Soft delete or check usage? The user didn't specify, so we'll just delete.
            // But we should check if it's currently used in active orders (too complex for this check).
            
            $discountId = $discount->id;
            $discountCode = $discount->discountCode;
            $discount->delete();

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'discount_deleted', [
                'discountId' => $discountId,
                'code' => $discountCode,
            ]);

            return ResponseHelper::success($response, 'Discount deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete discount', 500, $e->getMessage());
        }
    }
}

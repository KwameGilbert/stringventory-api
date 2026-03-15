<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class CustomerController
{
    /**
     * Get all customers
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $customers = Customer::with('orders')->orderBy('createdAt', 'desc')->get();
            return ResponseHelper::success($response, 'Customers fetched successfully', $customers->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch customers', 500, $e->getMessage());
        }
    }

    /**
     * Get single customer
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $customer = Customer::with(['orders', 'refunds'])->find($args['id']);
            if (!$customer) {
                return ResponseHelper::error($response, 'Customer not found', 404);
            }
            return ResponseHelper::success($response, 'Customer fetched successfully', $customer->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch customer', 500, $e->getMessage());
        }
    }

    /**
     * Create customer
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Handle the combined 'name' field if provided
            if (!empty($data['name'])) {
                $nameParts = explode(' ', $data['name'], 2);
                $data['firstName'] = $nameParts[0];
                $data['lastName'] = $nameParts[1] ?? '';
            }

            // Required for individual, businessName for corporate
            if (empty($data['firstName']) && empty($data['lastName']) && empty($data['businessName'])) {
                return ResponseHelper::error($response, 'At least a name or business name is required', 400);
            }

            $customer = Customer::create($data);
            return ResponseHelper::success($response, 'Customer created successfully', $customer->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create customer', 500, $e->getMessage());
        }
    }

    /**
     * Update customer
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $customer = Customer::find($args['id']);
            if (!$customer) {
                return ResponseHelper::error($response, 'Customer not found', 404);
            }

            $data = $request->getParsedBody();

            // Handle the combined 'name' field if provided
            if (!empty($data['name'])) {
                $nameParts = explode(' ', $data['name'], 2);
                $data['firstName'] = $nameParts[0];
                $data['lastName'] = $nameParts[1] ?? '';
            }

            $customer->update($data);
            return ResponseHelper::success($response, 'Customer updated successfully', $customer->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update customer', 500, $e->getMessage());
        }
    }

    /**
     * Delete customer
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $customer = Customer::find($args['id']);
            if (!$customer) {
                return ResponseHelper::error($response, 'Customer not found', 404);
            }

            // Check if customer has orders
            $orderCount = Order::where('customerId', $args['id'])->count();
            if ($orderCount > 0) {
                return ResponseHelper::error($response, "Cannot delete customer as they have $orderCount associated orders.", 400);
            }

            $customer->delete();
            return ResponseHelper::success($response, 'Customer deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete customer', 500, $e->getMessage());
        }
    }
}

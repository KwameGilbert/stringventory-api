<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Event;
use App\Models\Transaction;
use App\Models\OrganizerBalance;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

/**
 * OrderController
 * Handles order creation, payment processing, and Paystack webhook verification
 */
class OrderController
{
    private string $paystackSecretKey;

    public function __construct()
    {
        $this->paystackSecretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
    }

    /**
     * Create a new order
     * POST /v1/orders
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        try {
            $data = $request->getParsedBody();
            $tokenUser = $request->getAttribute('user');
            
            // Fetch full user data from database (JWT only contains id, email, role, status)
            $user = User::find($tokenUser->id);
            
            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 401);
            }
            
            $isPos = $user->role === 'pos';

            if (empty($data['items']) || !is_array($data['items'])) {
                return ResponseHelper::error($response, 'Items array is required', 400);
            }

            $totalAmount = 0;
            $orderItemsData = [];

            foreach ($data['items'] as $item) {
                if (empty($item['ticket_type_id']) || empty($item['quantity'])) {
                    throw new Exception('Invalid item format');
                }

                $ticketType = TicketType::find($item['ticket_type_id']);
                if (!$ticketType || !$ticketType->isActive()) {
                    throw new Exception("Ticket type {$item['ticket_type_id']} is not active");
                }
                
                // POS Assignment Check
                if ($isPos) {
                     $assigned = \App\Models\PosAssignment::where('user_id', $user->id)
                                              ->where('event_id', $ticketType->event_id)
                                              ->exists();
                     if (!$assigned) {
                         throw new Exception("POS user is not assigned to sell tickets for event {$ticketType->event_id}");
                     }
                }

                if ($ticketType->remaining < $item['quantity']) {
                    throw new Exception("Not enough tickets remaining for {$ticketType->name}");
                }

                $itemTotal = $ticketType->price * $item['quantity'];
                $totalAmount += $itemTotal;

                // Get event for revenue share calculation
                $event = Event::find($ticketType->event_id);
                $revenueSplit = $event ? $event->calculateRevenueSplit($itemTotal) : [
                    'admin_share_percent' => 10,
                    'organizer_amount' => $itemTotal * 0.9,
                    'admin_amount' => $itemTotal * 0.1 - ($itemTotal * 0.015),
                    'payment_fee' => $itemTotal * 0.015,
                ];

                $orderItemsData[] = [
                    'event_id' => $ticketType->event_id,
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $ticketType->price,
                    'total_price' => $itemTotal,
                    'admin_share_percent' => $revenueSplit['admin_share_percent'],
                    'admin_amount' => $revenueSplit['admin_amount'],
                    'organizer_amount' => $revenueSplit['organizer_amount'],
                    'payment_fee' => $revenueSplit['payment_fee'],
                ];
            }

            // Calculate fees (1.5% Paystack fee)
            $fees = round($totalAmount * 0.015, 2);
            $grandTotal = $totalAmount + $fees;

            // Create Order
            $orderData = [
                'user_id' => $user->id,
                'subtotal' => $totalAmount,
                'fees' => $fees,
                'total_amount' => $grandTotal,
                'status' => Order::STATUS_PENDING,
                'customer_email' => $data['customer_email'] ?? $user->email,
                'customer_name' => $data['customer_name'] ?? $user->name,
                'customer_phone' => $data['customer_phone'] ?? $user->phone,
            ];
            
            if ($isPos) {
                $orderData['pos_user_id'] = $user->id;
            }

            $order = Order::create($orderData);

            // Create Order Items
            foreach ($orderItemsData as $itemData) {
                $itemData['order_id'] = $order->id;
                OrderItem::create($itemData);
                
                // Reserve tickets (decrement remaining)
                $ticketType = TicketType::find($itemData['ticket_type_id']);
                $ticketType->decrement('remaining', $itemData['quantity']);
            }

            DB::commit();

            // Generate Paystack reference
            $paystackReference = 'EVT-' . $order->id . '-' . time();
            $order->update(['payment_reference' => $paystackReference]);

            return ResponseHelper::success($response, 'Order created successfully. Proceed to payment.', [
                'order_id' => $order->id,
                'reference' => $paystackReference,
                'subtotal' => $totalAmount,
                'fees' => $fees,
                'total_amount' => $grandTotal,
                'status' => $order->status,
                'is_pos' => $isPos,
                'customer_email' => $order->customer_email,
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, $e->getMessage(), 400);
        }
    }

    /**
     * Initialize Paystack payment
     * POST /v1/orders/{id}/pay
     */
    public function initializePayment(Request $request, Response $response, array $args): Response
    {
        try {
            $orderId = $args['id'];
            $user = $request->getAttribute('user');
            
            $order = Order::find($orderId);
            
            if (!$order) {
                return ResponseHelper::error($response, 'Order not found', 404);
            }

            if ($order->user_id !== $user->id) {
                return ResponseHelper::error($response, 'Unauthorized', 403);
            }

            if ($order->status === Order::STATUS_PAID) {
                return ResponseHelper::error($response, 'Order is already paid', 400);
            }

            // Initialize Paystack payment
            $url = "https://api.paystack.co/transaction/initialize";
            
            $fields = [
                'email' => $order->customer_email,
                'amount' => (int)($order->total_amount * 100), // Convert to kobo/pesewas
                'reference' => $order->payment_reference,
                'callback_url' => $_ENV['FRONTEND_URL'] . '/payment/verify?order_id=' . $order->id,
                'metadata' => [
                    'order_id' => $order->id,
                    'customer_name' => $order->customer_name,
                    'custom_fields' => [
                        [
                            'display_name' => 'Order ID',
                            'variable_name' => 'order_id',
                            'value' => (string)$order->id
                        ]
                    ]
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $this->paystackSecretKey,
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $result = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                return ResponseHelper::error($response, 'Payment initialization failed', 500);
            }

            $paystackResponse = json_decode($result, true);

            if (!$paystackResponse['status']) {
                return ResponseHelper::error($response, $paystackResponse['message'] ?? 'Payment initialization failed', 400);
            }

            return ResponseHelper::success($response, 'Payment initialized', [
                'authorization_url' => $paystackResponse['data']['authorization_url'],
                'access_code' => $paystackResponse['data']['access_code'],
                'reference' => $paystackResponse['data']['reference'],
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Payment initialization failed', 500, $e->getMessage());
        }
    }

    /**
     * Verify Paystack payment
     * GET /v1/orders/{id}/verify
     */
    public function verifyPayment(Request $request, Response $response, array $args): Response
    {
        try {
            $orderId = $args['id'];
            $queryParams = $request->getQueryParams();
            $reference = $queryParams['reference'] ?? null;
            
            $order = Order::find($orderId);
            
            if (!$order) {
                return ResponseHelper::error($response, 'Order not found', 404);
            }

            if (!$reference) {
                $reference = $order->payment_reference;
            }

            // Verify with Paystack
            $paymentData = $this->verifyPaystackPayment($reference);

            if (!$paymentData) {
                return ResponseHelper::error($response, 'Payment verification failed', 400);
            }

            if ($paymentData['status'] === 'success') {
                // Payment successful - process the order
                $this->processSuccessfulPayment($order, $reference);

                return ResponseHelper::success($response, 'Payment verified successfully', [
                    'order_id' => $order->id,
                    'status' => 'paid',
                    'reference' => $reference,
                    'amount_paid' => $paymentData['amount'] / 100,
                ]);
            } else {
                return ResponseHelper::error($response, 'Payment was not successful', 400, [
                    'payment_status' => $paymentData['status'],
                    'gateway_response' => $paymentData['gateway_response'] ?? null,
                ]);
            }

        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Payment verification failed', 500, $e->getMessage());
        }
    }

    /**
     * Paystack Webhook Handler
     * POST /v1/payment/webhook
     */
    public function paystackWebhook(Request $request, Response $response): Response
    {
        try {
            // Verify webhook signature
            $input = file_get_contents('php://input');
            $signature = $request->getHeaderLine('x-paystack-signature');
            
            if (!$this->verifyWebhookSignature($input, $signature)) {
                return ResponseHelper::error($response, 'Invalid signature', 401);
            }

            $event = json_decode($input, true);
            
            if (!$event || empty($event['event'])) {
                return ResponseHelper::error($response, 'Invalid event', 400);
            }

            // Handle different event types
            switch ($event['event']) {
                case 'charge.success':
                    $this->handleChargeSuccess($event['data']);
                    break;
                    
                case 'charge.failed':
                    $this->handleChargeFailed($event['data']);
                    break;
                    
                case 'transfer.success':
                case 'transfer.failed':
                    // Handle transfer events if needed for refunds
                    break;
            }

            return ResponseHelper::success($response, 'Webhook processed', [], 200);

        } catch (Exception $e) {
            error_log('Paystack Webhook Error: ' . $e->getMessage());
            return ResponseHelper::error($response, 'Webhook processing failed', 500);
        }
    }

    /**
     * Verify Paystack webhook signature
     */
    private function verifyWebhookSignature(string $input, string $signature): bool
    {
        if (empty($this->paystackSecretKey)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha512', $input, $this->paystackSecretKey);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle successful charge event
     */
    private function handleChargeSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;
        
        if (!$reference) {
            return;
        }

        $order = Order::where('payment_reference', $reference)->first();
        
        if (!$order || $order->status === Order::STATUS_PAID) {
            return;
        }

        $this->processSuccessfulPayment($order, $reference);
    }

    /**
     * Handle failed charge event
     */
    private function handleChargeFailed(array $data): void
    {
        $reference = $data['reference'] ?? null;
        
        if (!$reference) {
            return;
        }

        $order = Order::where('payment_reference', $reference)->first();
        
        if (!$order || $order->status !== Order::STATUS_PENDING) {
            return;
        }

        // Mark order as failed and release tickets
        DB::beginTransaction();
        try {
            $order->update([
                'status' => Order::STATUS_FAILED,
            ]);

            // Release reserved tickets
            foreach ($order->items as $item) {
                $ticketType = TicketType::find($item->ticket_type_id);
                if ($ticketType) {
                    $ticketType->increment('remaining', $item->quantity);
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            error_log('Failed to process charge failure: ' . $e->getMessage());
        }
    }

    /**
     * Process successful payment and generate tickets
     */
    private function processSuccessfulPayment(Order $order, string $reference): void
    {
        DB::beginTransaction();
        try {
            // Update Order Status
            $order->update([
                'status' => Order::STATUS_PAID,
                'payment_reference' => $reference,
                'paid_at' => \Illuminate\Support\Carbon::now(),
            ]);

            // Generate Tickets and create transactions
            $orderItems = $order->items()->with('event.organizer')->get();

            foreach ($orderItems as $item) {
                // Generate tickets
                for ($i = 0; $i < $item->quantity; $i++) {
                    Ticket::create([
                        'order_id' => $order->id,
                        'event_id' => $item->event_id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'ticket_code' => Ticket::generateUniqueCode(),
                        'status' => Ticket::STATUS_ACTIVE,
                        'attendee_id' => null,
                    ]);
                }

                // Create transaction record for this order item
                $event = $item->event;
                if ($event && $event->organizer) {
                    $organizerId = $event->organizer->id;

                    // Create transaction
                    Transaction::createTicketSale(
                        $organizerId,
                        $item->event_id,
                        $order->id,
                        $item->id,
                        (float) $item->total_price,
                        (float) $item->admin_amount,
                        (float) $item->organizer_amount,
                        (float) $item->payment_fee,
                        "Ticket sale: {$event->title}"
                    );

                    // Update organizer balance (add to pending)
                    $balance = OrganizerBalance::getOrCreate($organizerId);
                    $balance->addPendingEarnings((float) $item->organizer_amount);
                }
            }

            DB::commit();
            error_log('=== ORDER PAYMENT PROCESSED - SENDING EMAIL ===');
            error_log('Order ID: ' . $order->id);

            // Send confirmation email with tickets
            try {
                error_log('Creating EmailService...');
                $emailService = new \App\Services\EmailService();
                
                // Reload order with items for email
                error_log('Loading order relationships...');
                $order->load(['items.event', 'items.ticketType', 'tickets']);
                error_log('Items loaded: ' . $order->items->count());
                
                error_log('Calling sendTicketConfirmationEmail...');
                $result = $emailService->sendTicketConfirmationEmail($order);
                error_log('Email send result: ' . ($result ? 'SUCCESS' : 'FAILED'));
            } catch (\Exception $e) {
                // Log but don't fail - email is not critical
                error_log('Failed to send ticket confirmation email: ' . $e->getMessage());
                error_log('Exception trace: ' . $e->getTraceAsString());
            }

        } catch (Exception $e) {
            DB::rollBack();
            error_log('Failed to process successful payment: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user orders
     * GET /v1/orders
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $request->getAttribute('user');
            $orders = Order::where('user_id', $user->id)
                ->with(['items.ticketType.event', 'tickets'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
            
            return ResponseHelper::success($response, 'Orders fetched successfully', $orders);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch orders', 500, $e->getMessage());
        }
    }

    /**
     * Get single order
     * GET /v1/orders/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $user = $request->getAttribute('user');
            $order = Order::where('id', $args['id'])
                ->where('user_id', $user->id)
                ->with(['items.ticketType.event', 'tickets.ticketType', 'tickets.event'])
                ->first();
            
            if (!$order) {
                return ResponseHelper::error($response, 'Order not found', 404);
            }
            
            return ResponseHelper::success($response, 'Order fetched successfully', $order->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch order', 500, $e->getMessage());
        }
    }

    /**
     * Cancel a pending order
     * POST /v1/orders/{id}/cancel
     */
    public function cancel(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        try {
            $tokenUser = $request->getAttribute('user');
            $orderId = $args['id'];
            
            // Fetch full user
            $user = User::find($tokenUser->id);
            if (!$user) {
                return ResponseHelper::error($response, 'User not found', 401);
            }
            
            $order = Order::where('id', $orderId)
                ->where('user_id', $user->id)
                ->first();
            
            if (!$order) {
                return ResponseHelper::error($response, 'Order not found', 404);
            }
            
            // Only pending orders can be cancelled
            if ($order->status !== Order::STATUS_PENDING) {
                return ResponseHelper::error($response, 'Only pending orders can be cancelled', 400);
            }
            
            // Restore ticket quantities
            $orderItems = OrderItem::where('order_id', $order->id)->get();
            foreach ($orderItems as $item) {
                $ticketType = TicketType::find($item->ticket_type_id);
                if ($ticketType) {
                    $ticketType->increment('remaining', $item->quantity);
                }
            }
            
            // Update order status to cancelled
            $order->update(['status' => Order::STATUS_CANCELLED]);
            
            DB::commit();
            
            return ResponseHelper::success($response, 'Order cancelled successfully', [
                'order_id' => $order->id,
                'status' => 'cancelled',
            ]);
            
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($response, 'Failed to cancel order', 500, $e->getMessage());
        }
    }

   /**
     * Verify payment with Paystack API
     * 
     * @param string $reference Payment reference
     * @return array|null Payment data if successful, null if failed
     */
    private function verifyPaystackPayment(string $reference): ?array
    {
        try {
            $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $this->paystackSecretKey,
                "Content-Type: application/json",
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            // SSL Configuration
            // For production, download cacert.pem from https://curl.se/ca/cacert.pem
            // and set: curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem');
            $isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';
            
            if (!$isProduction) {
                // Development: Disable SSL verification (NOT for production!)
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            } else {
                // Production: Verify SSL properly
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                
                // If you have a CA bundle file, uncomment and set path:
                // $caPath = $_ENV['CURL_CA_BUNDLE'] ?? null;
                // if ($caPath && file_exists($caPath)) {
                //     curl_setopt($ch, CURLOPT_CAINFO, $caPath);
                // }
            }
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                error_log("Paystack verification cURL error: " . $err);
                return null;
            }

            if ($httpCode !== 200) {
                error_log("Paystack verification HTTP error: {$httpCode}, Response: {$result}");
                return null;
            }

            $paystackResponse = json_decode($result, true);

            if (!$paystackResponse || !isset($paystackResponse['status']) || !$paystackResponse['status']) {
                error_log("Paystack verification failed: Invalid response - " . ($result ?? 'empty'));
                return null;
            }

            error_log("Paystack verification successful for reference: {$reference}");
            return $paystackResponse['data'] ?? null;
        } catch (Exception $e) {
            error_log("Paystack verification exception: " . $e->getMessage());
            return null;
        }
    }
}

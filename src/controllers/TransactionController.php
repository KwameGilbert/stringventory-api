<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\OrderItem;
use App\Models\Transaction;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class TransactionController
{
    public function index(Request $request, Response $response): Response
    {
        try {
            $query = Transaction::with(['order', 'expense', 'refund', 'purchase']);

            $transactions = (clone $query)->orderBy('createdAt', 'desc')->get();

            // Only completed money movements count: cancelling an order cancels its transactions,
            // and cancelled, pending or failed ones must not show up in the totals.
            $totalInflow   = (float) Transaction::where('status', 'completed')->where('amount', '>', 0)->sum('amount');
            $totalOutflow  = (float) Transaction::where('status', 'completed')->where('amount', '<', 0)->sum('amount');
            $netProfitLoss = $totalInflow + $totalOutflow;

            $result = [
                'summary' => [
                    'totalInflow'   => round($totalInflow, 2),
                    'totalOutflow'  => round($totalOutflow, 2),
                    'netProfitLoss' => round($netProfitLoss, 2),
                ],
                'transactions' => $transactions->toArray(),
            ];

            return ResponseHelper::success($response, 'Transactions fetched successfully', $result);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch transactions', 500, $e->getMessage());
        }
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $transaction = Transaction::with([
                'order.customer',
                'order.items.product',
                'order.creator:id,firstName,lastName,role',
                'expense.category',
                'expense.creator:id,firstName,lastName,role',
                'purchase.supplier',
                'purchase.items.product',
                'purchase.creator:id,firstName,lastName,role',
                'refund.order',
                'refund.creator:id,firstName,lastName,role',
                'adjustment.product',
            ])->find($args['id']);

            if (!$transaction) {
                return ResponseHelper::error($response, 'Transaction not found', 404);
            }

            $data = $transaction->toArray();
            $data['refundItems'] = $this->refundItems($transaction);
            $data['relatedTransactions'] = $this->relatedTransactions($transaction);

            return ResponseHelper::success($response, 'Transaction fetched successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch transaction', 500, $e->getMessage());
        }
    }
}

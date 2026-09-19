<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Inventory;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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
            $data['adjustment'] = $this->adjustment($transaction);
            $data['refundItems'] = $this->refundItems($transaction);
            $data['relatedTransactions'] = $this->relatedTransactions($transaction);

            return ResponseHelper::success($response, 'Transaction fetched successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch transaction', 500, $e->getMessage());
        }
    }

    /**
     * The stock record a manual adjustment changed (adjustmentId holds the inventory id), with
     * its product. Null for every other kind of entry.
     */
    private function adjustment(Transaction $transaction): ?array
    {
        if ($transaction->adjustmentId === null) {
            return null;
        }

        return Inventory::with('product')->find($transaction->adjustmentId)?->toArray();
    }

    /**
     * The items a linked refund covers, with product names. A unit counts as restocked only
     * when the refund put it back on the shelf (restock true and the product still exists);
     * anything else was lost stock.
     */
    private function refundItems(Transaction $transaction): array
    {
        $items = $transaction->refund?->items;
        if (empty($items) || !is_array($items)) {
            return [];
        }

        $orderItems = OrderItem::with('product:id,name,sku')
            ->whereIn('id', array_column($items, 'orderItemId'))
            ->get()
            ->keyBy('id');

        $details = [];
        foreach ($items as $item) {
            $orderItem = $orderItems->get($item['orderItemId'] ?? 0);
            if (!$orderItem) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            $details[] = [
                'orderItemId' => $orderItem->id,
                'productId'   => $orderItem->productId,
                'productName' => $orderItem->product?->name ?? 'Deleted product',
                'sku'         => $orderItem->product?->sku,
                'quantity'    => $quantity,
                'restocked'   => (bool) ($item['restock'] ?? true) && $orderItem->productId !== null,
                'unitPrice'   => (float) $orderItem->sellingPrice,
                'amount'      => round($quantity * (float) $orderItem->sellingPrice, 2),
            ];
        }

        return $details;
    }

    /**
     * Other ledger entries tied to the same order, refund, purchase, expense or stock record,
     * newest first. Empty when the transaction links to nothing, so it never returns the
     * whole ledger.
     */
    private function relatedTransactions(Transaction $transaction): array
    {
        $links = array_filter([
            'orderId'      => $transaction->orderId,
            'refundId'     => $transaction->refundId,
            'purchaseId'   => $transaction->purchaseId,
            'expenseId'    => $transaction->expenseId,
            'adjustmentId' => $transaction->adjustmentId,
        ], fn ($id) => $id !== null);

        if (!$links) {
            return [];
        }

        return Transaction::where('id', '!=', $transaction->id)
            ->where(function ($query) use ($links) {
                foreach ($links as $column => $id) {
                    $query->orWhere($column, $id);
                }
            })
            ->orderBy('createdAt', 'desc')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get([
                'id', 'orderId', 'refundId', 'purchaseId', 'expenseId', 'adjustmentId',
                'transactionType', 'paymentMethod', 'amount', 'status', 'currency', 'createdAt',
            ])
            ->toArray();
    }
}

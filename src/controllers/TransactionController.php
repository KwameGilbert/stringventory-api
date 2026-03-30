<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Transaction;
use App\Helper\ResponseHelper;
use App\Services\CurrencyService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class TransactionController
{
    public function index(Request $request, Response $response): Response
    {
        try {
            $transactions = Transaction::with(['order', 'expense', 'refund', 'purchase'])
                ->orderBy('createdAt', 'desc')
                ->get();

            // Convert each transaction to the current business currency
            $converted = CurrencyService::convertCollection($transactions->toArray(), ['amount']);

            // Recompute summary from converted amounts so totals reflect the current currency
            $totalInflow  = 0.0;
            $totalOutflow = 0.0;
            foreach ($converted as $t) {
                $amt = (float) $t['amount'];
                if ($amt > 0) {
                    $totalInflow += $amt;
                } else {
                    $totalOutflow += $amt;
                }
            }
            $netProfitLoss = $totalInflow + $totalOutflow;

            $result = [
                'summary' => [
                    'totalInflow'   => round($totalInflow, 2),
                    'totalOutflow'  => round($totalOutflow, 2),
                    'netProfitLoss' => round($netProfitLoss, 2),
                ],
                'transactions' => $converted,
            ];

            return ResponseHelper::success($response, 'Transactions fetched successfully', $result);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch transactions', 500, $e->getMessage());
        }
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $transaction = Transaction::find($args['id']);
            if (!$transaction) {
                return ResponseHelper::error($response, 'Transaction not found', 404);
            }
            $data = CurrencyService::convertRecord($transaction->toArray(), ['amount']);
            return ResponseHelper::success($response, 'Transaction fetched successfully', $data);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch transaction', 500, $e->getMessage());
        }
    }
}

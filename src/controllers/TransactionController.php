<?php

declare(strict_types=1);

namespace App\Controllers;

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
            $transactions = Transaction::orderBy('createdAt', 'desc')->get();
            return ResponseHelper::success($response, 'Transactions fetched successfully', $transactions->toArray());
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
            return ResponseHelper::success($response, 'Transaction fetched successfully', $transaction->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch transaction', 500, $e->getMessage());
        }
    }
}

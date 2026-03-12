<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class AuditLogController
{
    public function index(Request $request, Response $response): Response
    {
        try {
            $logs = AuditLog::with('user')->orderBy('createdAt', 'desc')->get();
            return ResponseHelper::success($response, 'Audit logs fetched successfully', $logs->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch audit logs', 500, $e->getMessage());
        }
    }
}

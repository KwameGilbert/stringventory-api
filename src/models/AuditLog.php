<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * AuditLog Model
 */
class AuditLog extends Model
{
    protected $table = 'auditLogs';
    public $timestamps = false;
    const CREATED_AT = 'createdAt';

    protected $fillable = [
        'userId',
        'action',
        'ipAddress',
        'userAgent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'createdAt' => 'datetime',
        'userId' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Get the module associated with the action
     */
    public function getModule(): string
    {
        $action = $this->action;
        
        if (str_starts_with($action, 'inventory') || str_starts_with($action, 'product') || str_starts_with($action, 'uom') || str_starts_with($action, 'category')) {
            return 'Inventory';
        }
        
        if (str_starts_with($action, 'order') || str_starts_with($action, 'customer') || str_starts_with($action, 'refund')) {
            return 'Sales';
        }
        
        if (str_starts_with($action, 'expense')) {
            return 'Expenses';
        }
        
        if (str_starts_with($action, 'purchase') || str_starts_with($action, 'supplier')) {
            return 'Procurement';
        }
        
        if (str_contains($action, 'password') || str_contains($action, 'user') || str_contains($action, 'login') || str_contains($action, 'api_key')) {
            return 'Security';
        }
        
        if (str_starts_with($action, 'settings')) {
            return 'Settings';
        }

        return 'System';
    }

    /**
     * Get the severity level for the action
     */
    public function getSeverity(): string
    {
        $action = $this->action;
        
        $criticalActions = [
            'user_deleted', 'product_deleted', 'order_cancelled', 
            'settings_payment_updated', 'api_key_regenerated',
            'failed_login'
        ];
        
        $warningActions = [
            'inventory_adjusted', 'refund_requested', 'password_reset_requested',
            'expense_deleted', 'customer_deleted'
        ];

        if (in_array($action, $criticalActions)) {
            return 'critical';
        }

        if (in_array($action, $warningActions) || str_contains($action, 'deleted')) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * Get a human-readable action name
     */
    public function getFormattedAction(): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $this->action));
    }

    /**
     * Get human-readable details from metadata
     */
    public function getDetails(): string
    {
        $meta = $this->metadata;
        if (empty($meta)) {
            return $this->getFormattedAction();
        }

        switch ($this->action) {
            case 'inventory_adjusted':
                $adj = (int)($meta['adjustment'] ?? 0);
                $sign = $adj >= 0 ? '+' : '';
                return ($meta['productName'] ?? 'Product') . " adjusted by {$sign}{$adj} units";
            
            case 'order_created':
                return "New order placed: " . ($meta['orderNumber'] ?? 'ORD-' . ($meta['orderId'] ?? 'N/A'));
            
            case 'order_cancelled':
                return "Order " . ($meta['orderNumber'] ?? 'N/A') . " was cancelled";
            
            case 'refund_requested':
                return "Refund of " . ($meta['amount'] ?? '0.00') . " requested for order " . ($meta['orderNumber'] ?? 'N/A');
            
            case 'user_created':
                return "New user account created: " . ($meta['email'] ?? 'N/A');
                
            case 'failed_login':
                return "Failed login attempt for: " . ($meta['email'] ?? 'N/A') . " from IP: " . ($this->ipAddress);

            default:
                // Generic formatter for other actions
                if (isset($meta['name'])) return $this->getFormattedAction() . ": " . $meta['name'];
                if (isset($meta['email'])) return $this->getFormattedAction() . ": " . $meta['email'];
                if (isset($meta['id'])) return $this->getFormattedAction() . " ID: " . $meta['id'];
                
                return $this->getFormattedAction();
        }
    }

    /**
     * Log an activity. Automatically extracts IP and user agent from the request.
     * Call this AFTER a successful save/commit — never inside a transaction block.
     */
    public static function log(
        Request $request,
        ?int $userId,
        string $action,
        array $extra = []
    ): void {
        $serverParams = $request->getServerParams();
        self::create([
            'userId'    => $userId,
            'action'    => $action,
            'ipAddress' => $serverParams['REMOTE_ADDR'] ?? '0.0.0.0',
            'userAgent' => $request->getHeaderLine('User-Agent'),
            'metadata'  => !empty($extra) ? $extra : null,
        ]);
    }
}

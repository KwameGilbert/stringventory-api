<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Helper\ResponseHelper;
use Slim\Psr7\Response as SlimResponse;

/**
 * RoleMiddleware
 * 
 * Enforces role-based access control.
 */
class RoleMiddleware
{
    private array $allowedRoles;

    /**
     * @param array $allowedRoles List of roles allowed to access the route
     */
    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            $response = new SlimResponse();
            return ResponseHelper::error($response, 'Unauthorized: User not found', 401);
        }

        if (!in_array($user->role, $this->allowedRoles)) {
            $response = new SlimResponse();
            return ResponseHelper::error($response, 'Forbidden: You do not have permission to perform this action', 403);
        }

        return $handler->handle($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helper\ResponseHelper;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Roles are fixed constants on the User model, not database rows.
 * `id` and `name` both hold the value stored on users.role.
 */
class RoleController
{
    private function roles(): array
    {
        return [
            ['id' => User::ROLE_CEO, 'name' => User::ROLE_CEO, 'displayName' => 'CEO', 'isSystemRole' => true],
            ['id' => User::ROLE_MANAGER, 'name' => User::ROLE_MANAGER, 'displayName' => 'Manager', 'isSystemRole' => true],
            ['id' => User::ROLE_SALESPERSON, 'name' => User::ROLE_SALESPERSON, 'displayName' => 'Salesperson', 'isSystemRole' => true],
        ];
    }

    public function index(Request $request, Response $response): Response
    {
        return ResponseHelper::success($response, 'Roles fetched successfully', $this->roles());
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        foreach ($this->roles() as $role) {
            if ($role['id'] === $args['id']) {
                return ResponseHelper::success($response, 'Role fetched successfully', $role);
            }
        }
        return ResponseHelper::error($response, 'Role not found', 404);
    }
}

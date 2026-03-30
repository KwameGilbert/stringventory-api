<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UnitOfMeasure;
use App\Models\AuditLog;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class UnitOfMeasureController
{
    /**
     * Get all units of measure
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $units = UnitOfMeasure::orderBy('name', 'asc')->get();
            return ResponseHelper::success($response, 'Units of measure fetched successfully', $units->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch units of measure', 500, $e->getMessage());
        }
    }

    /**
     * Create a unit of measure
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            if (empty($data['name'])) {
                return ResponseHelper::error($response, 'Name is required', 400);
            }

            $unit = UnitOfMeasure::create($data);

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'uom_created', [
                'uomId' => $unit->id,
                'name' => $unit->name,
            ]);

            return ResponseHelper::success($response, 'Unit of measure created successfully', $unit->toArray(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create unit of measure', 500, $e->getMessage());
        }
    }

    /**
     * Update a unit of measure
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $unit = UnitOfMeasure::find($args['id']);
            if (!$unit) {
                return ResponseHelper::error($response, 'Unit of measure not found', 404);
            }

            $data = $request->getParsedBody();
            $unit->update($data);

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'uom_updated', [
                'uomId' => $unit->id,
                'name' => $unit->name,
            ]);

            return ResponseHelper::success($response, 'Unit of measure updated successfully', $unit->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update unit of measure', 500, $e->getMessage());
        }
    }

    /**
     * Delete a unit of measure
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $unit = UnitOfMeasure::withCount('products')->find($args['id']);
            if (!$unit) {
                return ResponseHelper::error($response, 'Unit of measure not found', 404);
            }

            if ($unit->products_count > 0) {
                return ResponseHelper::error($response, 'Cannot delete unit of measure that is currently in use by products', 400);
            }

            $unitId = $unit->id;
            $unitName = $unit->name;
            $unit->delete();

            $user = $request->getAttribute('user');
            AuditLog::log($request, $user ? $user->id : null, 'uom_deleted', [
                'uomId' => $unitId,
                'name' => $unitName,
            ]);

            return ResponseHelper::success($response, 'Unit of measure deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete unit of measure', 500, $e->getMessage());
        }
    }
}

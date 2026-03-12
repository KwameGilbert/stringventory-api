<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helper\ResponseHelper;
use App\Models\AwardCategory;
use App\Models\Event;
use App\Models\Award;
use App\Models\Organizer;
use App\Services\UploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class AwardCategoryController
{
    private UploadService $uploadService;

    public function __construct()
    {
        $this->uploadService = new UploadService();
    }
    /**
     * Get all categories for an award
     * GET /v1/awards/{awardId}/award-categories
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $awardId = $args['awardId'] ?? $args['eventId']; // Support both for backward compatibility
            $queryParams = $request->getQueryParams();
            $includeResults = isset($queryParams['include_results']) && $queryParams['include_results'] === 'true';

            // Verify award exists
            $award = Award::find($awardId);
            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Get categories ordered by display_order
            $categories = AwardCategory::where('award_id', $awardId)
                ->ordered()
                ->get();

            if ($includeResults) {
                $categoriesData = $categories->map(function ($category) {
                    return $category->getDetailsWithResults();
                });
            } else {
                $categoriesData = $categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'award_id' => $category->award_id,
                        'name' => $category->name,
                        'image' => $category->image,
                        'description' => $category->description,
                        'cost_per_vote' => (float) $category->cost_per_vote,
                        'voting_start' => $category->voting_start?->toIso8601String(),
                        'voting_end' => $category->voting_end?->toIso8601String(),
                        'status' => $category->status,
                        'display_order' => $category->display_order,
                        'is_voting_active' => $category->isVotingActive(),
                    ];
                });
            }

            return ResponseHelper::success($response, 'Award categories fetched successfully', $categoriesData->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch award categories', 500, $e->getMessage());
        }
    }

    /**
     * Get single category details
     * GET /v1/award-categories/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $queryParams = $request->getQueryParams();
            $includeResults = isset($queryParams['include_results']) && $queryParams['include_results'] === 'true';

            $category = AwardCategory::with(['award', 'nominees'])->find($id);

            if (!$category) {
                return ResponseHelper::error($response, 'Award category not found', 404);
            }

            $categoryData = $includeResults 
                ? $category->getDetailsWithResults()
                : [
                    'id' => $category->id,
                    'award_id' => $category->award_id,
                    'name' => $category->name,
                    'image' => $category->image,
                    'description' => $category->description,
                    'cost_per_vote' => (float) $category->cost_per_vote,
                    'voting_start' => $category->voting_start?->toIso8601String(),
                    'voting_end' => $category->voting_end?->toIso8601String(),
                    'status' => $category->status,
                    'display_order' => $category->display_order,
                    'is_voting_active' => $category->isVotingActive(),
                    'created_at' => $category->created_at?->toIso8601String(),
                    'updated_at' => $category->updated_at?->toIso8601String(),
                ];

            return ResponseHelper::success($response, 'Award category fetched successfully', $categoryData);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch award category', 500, $e->getMessage());
        }
    }

    /**
 * Create new award category
 * POST /v1/awards/{awardId}/award-categories
 */
public function create(Request $request, Response $response, array $args): Response
{
    try {
        $awardId = $args['awardId'] ?? $args['eventId']; // Support both for backward compatibility
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');

        // Verify award exists
        $award = Award::find($awardId);
        if (!$award) {
            return ResponseHelper::error($response, 'Award not found', 404);
        }

        // Authorization: Check if user owns the award
        if ($user->role !== 'admin') {
            $organizer = Organizer::where('user_id', $user->id)->first();
            if (!$organizer || $organizer->id !== $award->organizer_id) {
                return ResponseHelper::error($response, 'Unauthorized: You do not own this award', 403);
            }
        }

        // Validate required fields
        if (empty($data['name'])) {
            return ResponseHelper::error($response, 'Category name is required', 400);
        }

        // Set award_id
        $data['award_id'] = $awardId;

        // Set defaults
        if (!isset($data['status'])) {
            $data['status'] = 'active';
        }

        if (!isset($data['cost_per_vote'])) {
            $data['cost_per_vote'] = 1.00;
        }

        if (!isset($data['display_order'])) {
            // Get max display order and increment
            $maxOrder = AwardCategory::where('award_id', $awardId)->max('display_order') ?? 0;
            $data['display_order'] = $maxOrder + 1;
        }

        // Validate status
        if (isset($data['status']) && !in_array($data['status'], ['active', 'deactivated'])) {
            return ResponseHelper::error($response, 'Invalid status. Must be active or deactivated', 400);
        }

        // Handle image upload using UploadService
        $uploadedFiles = $request->getUploadedFiles();
        if (isset($uploadedFiles['image'])) {
            $image = $uploadedFiles['image'];
            if ($image->getError() === UPLOAD_ERR_OK) {
                try {
                    $data['image'] = $this->uploadService->uploadFile($image, 'image', 'categories');
                } catch (Exception $e) {
                    return ResponseHelper::error($response, $e->getMessage(), 400);
                }
            }
        }

        $category = AwardCategory::create($data);

        return ResponseHelper::success($response, 'Award category created successfully', $category->toArray(), 201);
    } catch (Exception $e) {
        return ResponseHelper::error($response, 'Failed to create award category', 500, $e->getMessage());
    }
}


    /**
     * Update award category
     * PUT /v1/award-categories/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $data = $request->getParsedBody();
            $user = $request->getAttribute('user');

            $category = AwardCategory::find($id);

            if (!$category) {
                return ResponseHelper::error($response, 'Award category not found', 404);
            }

            // Authorization: Check if user owns the event
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                $award = Award::find($category->award_id);
                if (!$organizer || !$award || $organizer->id !== $award->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this category', 403);
                }
            }

            // Validate status if provided
            if (isset($data['status']) && !in_array($data['status'], ['active', 'deactivated'])) {
                return ResponseHelper::error($response, 'Invalid status. Must be active or deactivated', 400);
            }

            // Don't allow changing award_id
            unset($data['award_id']);

            // Handle image upload using UploadService
            $uploadedFiles = $request->getUploadedFiles();
            if (isset($uploadedFiles['image'])) {
                $image = $uploadedFiles['image'];
                if ($image->getError() === UPLOAD_ERR_OK) {
                    try {
                        $data['image'] = $this->uploadService->replaceFile(
                            $image,
                            $category->image,
                            'image',
                            'categories'
                        );
                    } catch (Exception $e) {
                        return ResponseHelper::error($response, $e->getMessage(), 400);
                    }
                }
            }

            $category->update($data);

            return ResponseHelper::success($response, 'Award category updated successfully', $category->fresh()->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update award category', 500, $e->getMessage());
        }
    }

    /**
     * Delete award category
     * DELETE /v1/award-categories/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $user = $request->getAttribute('user');

            $category = AwardCategory::with('award')->find($id);

            if (!$category) {
                return ResponseHelper::error($response, 'Award category not found', 404);
            }

            // Authorization: Check if user owns the event
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                $award = Award::find($category->award_id);
                if (!$organizer || !$award || $organizer->id !== $award->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this category', 403);
                }
            }

            // Check if category has votes
            $voteCount = $category->votes()->where('status', 'paid')->count();
            if ($voteCount > 0) {
                return ResponseHelper::error($response, 'Cannot delete category with paid votes. Deactivate it instead.', 400);
            }

            $category->delete();

            return ResponseHelper::success($response, 'Award category deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete award category', 500, $e->getMessage());
        }
    }

    /**
     * Get category statistics
     * GET /v1/award-categories/{id}/stats
     */
    public function getStats(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];

            $category = AwardCategory::with(['nominees', 'votes'])->find($id);

            if (!$category) {
                return ResponseHelper::error($response, 'Award category not found', 404);
            }

            $stats = [
                'total_nominees' => $category->nominees()->count(),
                'total_votes' => $category->getTotalVotes(),
                'total_revenue' => $category->getCategoryTotalRevenue(),
                'paid_votes' => $category->votes()->where('status', 'paid')->count(),
                'pending_votes' => $category->votes()->where('status', 'pending')->count(),
                'is_voting_active' => $category->isVotingActive(),
                'cost_per_vote' => (float) $category->cost_per_vote,
            ];

            return ResponseHelper::success($response, 'Category statistics fetched successfully', $stats);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch category statistics', 500, $e->getMessage());
        }
    }

    /**
     * Reorder categories
     * POST /v1/awards/{awardId}/award-categories/reorder
     */
    public function reorder(Request $request, Response $response, array $args): Response
    {
        try {
            $awardId = $args['awardId'] ?? $args['eventId']; // Support both for backward compatibility
            $data = $request->getParsedBody();
            $user = $request->getAttribute('user');

            // Verify award exists
            $award = Award::find($awardId);
            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Authorization
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                if (!$organizer || $organizer->id !== $award->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized', 403);
                }
            }

            // Validate request
            if (!isset($data['order']) || !is_array($data['order'])) {
                return ResponseHelper::error($response, 'Order array is required', 400);
            }

            // Update display_order for each category
            foreach ($data['order'] as $index => $categoryId) {
                AwardCategory::where('id', $categoryId)
                    ->where('award_id', $awardId)
                    ->update(['display_order' => $index]);
            }

            $categories = AwardCategory::where('award_id', $awardId)
                ->ordered()
                ->get();

            return ResponseHelper::success($response, 'Categories reordered successfully', $categories->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to reorder categories', 500, $e->getMessage());
        }
    }
}

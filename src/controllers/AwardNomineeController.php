<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helper\ResponseHelper;
use App\Models\AwardNominee;
use App\Models\AwardCategory;
use App\Models\Award;
use App\Models\Organizer;
use App\Services\UploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class AwardNomineeController
{
    private UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }
    /**
     * Get all nominees for a category
     * GET /v1/award-categories/{categoryId}/nominees
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $categoryId = $args['categoryId'];
            $queryParams = $request->getQueryParams();
            $includeStats = isset($queryParams['include_stats']) && $queryParams['include_stats'] === 'true';

            // Verify category exists
            $category = AwardCategory::find($categoryId);
            if (!$category) {
                return ResponseHelper::error($response, 'Award category not found', 404);
            }

            // Get nominees ordered by display_order
            $nominees = AwardNominee::where('category_id', $categoryId)
                ->ordered()
                ->get();

            if ($includeStats) {
                $nomineesData = $nominees->map(function ($nominee) {
                    return $nominee->getDetailsWithStats();
                });
            } else {
                $nomineesData = $nominees->map(function ($nominee) {
                    return [
                        'id' => $nominee->id,
                        'category_id' => $nominee->category_id,
                        'award_id' => $nominee->award_id,
                        'name' => $nominee->name,
                        'description' => $nominee->description,
                        'image' => $nominee->image,
                        'display_order' => $nominee->display_order,
                        'created_at' => $nominee->created_at?->toIso8601String(),
                        'updated_at' => $nominee->updated_at?->toIso8601String(),
                    ];
                });
            }

            return ResponseHelper::success($response, 'Nominees fetched successfully', $nomineesData->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch nominees', 500, $e->getMessage());
        }
    }

    /**
     * Get all nominees for an award
     * GET /v1/awards/{awardId}/nominees
     */
    public function getByAward(Request $request, Response $response, array $args): Response
    {
        try {
            $awardId = $args['awardId'] ?? $args['eventId']; // Support both for backward compatibility
            $queryParams = $request->getQueryParams();
            $includeStats = isset($queryParams['include_stats']) && $queryParams['include_stats'] === 'true';

            // Verify award exists
            $award = Award::find($awardId);
            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Get nominees with category info
            $nominees = AwardNominee::with('category')
                ->where('award_id', $awardId)
                ->ordered()
                ->get();

            if ($includeStats) {
                $nomineesData = $nominees->map(function ($nominee) {
                    $stats = $nominee->getDetailsWithStats();
                    $stats['category_name'] = $nominee->category ? $nominee->category->name : null;
                    return $stats;
                });
            } else {
                $nomineesData = $nominees->map(function ($nominee) {
                    return [
                        'id' => $nominee->id,
                        'category_id' => $nominee->category_id,
                        'category_name' => $nominee->category ? $nominee->category->name : null,
                        'award_id' => $nominee->award_id,
                        'name' => $nominee->name,
                        'description' => $nominee->description,
                        'image' => $nominee->image,
                        'display_order' => $nominee->display_order,
                    ];
                });
            }

            return ResponseHelper::success($response, 'Award nominees fetched successfully', $nomineesData->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch award nominees', 500, $e->getMessage());
        }
    }

    /**
     * Get single nominee details
     * GET /v1/nominees/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $queryParams = $request->getQueryParams();
            $includeStats = isset($queryParams['include_stats']) && $queryParams['include_stats'] === 'true';

            $nominee = AwardNominee::with(['category', 'award'])->find($id);

            if (!$nominee) {
                return ResponseHelper::error($response, 'Nominee not found', 404);
            }

            $nomineeData = $includeStats 
                ? $nominee->getDetailsWithStats()
                : [
                    'id' => $nominee->id,
                    'category_id' => $nominee->category_id,
                    'category_name' => $nominee->category ? $nominee->category->name : null,
                    'award_id' => $nominee->award_id,
                    'award_name' => $nominee->award ? $nominee->award->title : null,
                    'name' => $nominee->name,
                    'description' => $nominee->description,
                    'image' => $nominee->image,
                    'display_order' => $nominee->display_order,
                    'created_at' => $nominee->created_at?->toIso8601String(),
                    'updated_at' => $nominee->updated_at?->toIso8601String(),
                ];

            return ResponseHelper::success($response, 'Nominee fetched successfully', $nomineeData);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch nominee', 500, $e->getMessage());
        }
    }

    /**
 * Create new nominee
 * POST /v1/award-categories/{categoryId}/nominees
 */
public function create(Request $request, Response $response, array $args): Response
{
    try {
        $categoryId = $args['categoryId'];
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $uploadedFiles = $request->getUploadedFiles();

        // Verify category exists
        $category = AwardCategory::with('award')->find($categoryId);
        if (!$category) {
            return ResponseHelper::error($response, 'Award category not found', 404);
        }

        // Authorization: Check if user owns the award
        if ($user->role !== 'admin') {
            $organizer = Organizer::where('user_id', $user->id)->first();
            if (!$organizer || !$category->award || $organizer->id !== $category->award->organizer_id) {
                return ResponseHelper::error($response, 'Unauthorized: You do not own this award', 403);
            }
        }

        // Validate required fields
        if (empty($data['name'])) {
            return ResponseHelper::error($response, 'Nominee name is required', 400);
        }

        // Set category_id and award_id
        $data['category_id'] = $categoryId;
        $data['award_id'] = $category->award_id;

        // Set default display_order
        if (!isset($data['display_order'])) {
            $maxOrder = AwardNominee::where('category_id', $categoryId)->max('display_order') ?? 0;
            $data['display_order'] = $maxOrder + 1;
        }

        // Handle image upload using UploadService
        if (isset($uploadedFiles['image'])) {
            $image = $uploadedFiles['image'];
            if ($image->getError() === UPLOAD_ERR_OK) {
                try {
                    $data['image'] = $this->uploadService->uploadFile($image, 'image', 'nominees');
                } catch (Exception $e) {
                    return ResponseHelper::error($response, $e->getMessage(), 400);
                }
            }
        }

        $nominee = AwardNominee::create($data);

        return ResponseHelper::success($response, 'Nominee created successfully', $nominee->toArray(), 201);
    } catch (Exception $e) {
        return ResponseHelper::error($response, 'Failed to create nominee', 500, $e->getMessage());
    }
}
    /**
     * Update nominee
     * PUT /v1/nominees/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $data = $request->getParsedBody();
            $user = $request->getAttribute('user');
            $uploadedFiles = $request->getUploadedFiles();

            $nominee = AwardNominee::with(['category.award'])->find($id);

            if (!$nominee) {
                return ResponseHelper::error($response, 'Nominee not found', 404);
            }

            // Authorization: Check if user owns the award
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                $award = $nominee->category ? $nominee->category->award : null;
                if (!$organizer || !$award || $organizer->id !== $award->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this nominee', 403);
                }
            }

            // Don't allow changing category_id or award_id
            unset($data['category_id'], $data['award_id']);

            // Handle image upload using UploadService
            if (isset($uploadedFiles['image'])) {
                $image = $uploadedFiles['image'];
                if ($image->getError() === UPLOAD_ERR_OK) {
                    try {
                        $data['image'] = $this->uploadService->replaceFile(
                            $image,
                            $nominee->image,
                            'image',
                            'nominees'
                        );
                    } catch (Exception $e) {
                        return ResponseHelper::error($response, $e->getMessage(), 400);
                    }
                }
            }

            $nominee->update($data);

            return ResponseHelper::success($response, 'Nominee updated successfully', $nominee->fresh()->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update nominee', 500, $e->getMessage());
        }
    }

    /**
     * Delete nominee
     * DELETE /v1/nominees/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $user = $request->getAttribute('user');

            $nominee = AwardNominee::with(['category.award'])->find($id);

            if (!$nominee) {
                return ResponseHelper::error($response, 'Nominee not found', 404);
            }

            // Authorization
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                $award = $nominee->category ? $nominee->category->award : null;
                if (!$organizer || !$award || $organizer->id !== $award->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized', 403);
                }
            }

            // Check if nominee has votes
            $voteCount = $nominee->votes()->where('status', 'paid')->count();
            if ($voteCount > 0) {
                return ResponseHelper::error($response, 'Cannot delete nominee with paid votes', 400);
            }

            // Delete image if exists using UploadService
            if ($nominee->image) {
                $this->uploadService->deleteFile($nominee->image);
            }

            $nominee->delete();

            return ResponseHelper::success($response, 'Nominee deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete nominee', 500, $e->getMessage());
        }
    }

    /**
     * Get nominee vote statistics
     * GET /v1/nominees/{id}/stats
     */
    public function getStats(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];

            $nominee = AwardNominee::with('category')->find($id);

            if (!$nominee) {
                return ResponseHelper::error($response, 'Nominee not found', 404);
            }

            $stats = [
                'total_votes' => $nominee->getTotalVotes(),
                'total_revenue' => $nominee->getTotalRevenue(),
                'paid_votes_count' => $nominee->votes()->where('status', 'paid')->count(),
                'pending_votes_count' => $nominee->votes()->where('status', 'pending')->count(),
            ];

            return ResponseHelper::success($response, 'Nominee statistics fetched successfully', $stats);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch nominee statistics', 500, $e->getMessage());
        }
    }

    /**
     * Reorder nominees in a category
     * POST /v1/award-categories/{categoryId}/nominees/reorder
     */
    public function reorder(Request $request, Response $response, array $args): Response
    {
        try {
            $categoryId = $args['categoryId'];
            $data = $request->getParsedBody();
            $user = $request->getAttribute('user');

            // Verify category exists
            $category = AwardCategory::with('award')->find($categoryId);
            if (!$category) {
                return ResponseHelper::error($response, 'Category not found', 404);
            }

            // Authorization
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                if (!$organizer || !$category->award || $organizer->id !== $category->award->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized', 403);
                }
            }

            // Validate request
            if (!isset($data['order']) || !is_array($data['order'])) {
                return ResponseHelper::error($response, 'Order array is required', 400);
            }

            // Update display_order for each nominee
            foreach ($data['order'] as $index => $nomineeId) {
                AwardNominee::where('id', $nomineeId)
                    ->where('category_id', $categoryId)
                    ->update(['display_order' => $index]);
            }

            $nominees = AwardNominee::where('category_id', $categoryId)
                ->ordered()
                ->get();

            return ResponseHelper::success($response, 'Nominees reordered successfully', $nominees->toArray());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to reorder nominees', 500, $e->getMessage());
        }
    }
}

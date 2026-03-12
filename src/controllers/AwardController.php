<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Award;
use App\Models\AwardImage;
use App\Models\Organizer;
use App\Services\UploadService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

/**
 * AwardController
 * Handles awards show operations (completely separate from Events/Ticketing)
 */
class AwardController
{
    private UploadService $uploadService;

    public function __construct()
    {
        $this->uploadService = new UploadService();
        Award::autoUpdateCompletedStatuses();
    }

    /**
     * Generate a unique slug by appending random alphanumeric string if duplicate exists
     * @param string $baseSlug The base slug to make unique
     * @return string Unique slug
     */
    private function generateUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        
        // Keep generating until we find a unique slug
        while (Award::where('slug', $slug)->exists()) {
            // Generate random 10-character alphanumeric string
            $randomSuffix = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 10);
            $slug = $baseSlug . '-' . $randomSuffix;
        }
        
        return $slug;
    }

    /**
     * Get all awards (with optional filtering)
     * GET /v1/awards
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $query = Award::with(['categories.nominees', 'organizer.user', 'images']);

            // Filter by status (default to published for public list)
            if (isset($queryParams['status'])) {
                $query->where('status', $queryParams['status']);
            } else {
                // Default to published and completed awards for public endpoint
                $query->whereIn('status', [Award::STATUS_PUBLISHED, Award::STATUS_COMPLETED]);
            }

            // Filter by organizer
            if (isset($queryParams['organizer_id'])) {
                $query->where('organizer_id', $queryParams['organizer_id']);
            }

            // Filter upcoming only
            if (isset($queryParams['upcoming']) && $queryParams['upcoming'] === 'true') {
                $query->where('ceremony_date', '>', \Illuminate\Support\Carbon::now());
            }

            // Filter voting open
            if (isset($queryParams['voting_open']) && $queryParams['voting_open'] === 'true') {
                $query->votingOpen();
            }

            // Search by title or description
            if (isset($queryParams['search']) && !empty($queryParams['search'])) {
                $search = $queryParams['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Pagination
            $page = (int) ($queryParams['page'] ?? 1);
            $perPage = (int) ($queryParams['per_page'] ?? 20);
            $offset = ($page - 1) * $perPage;

            $totalCount = $query->count();
            $awards = $query->orderBy('ceremony_date', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            // Format awards for frontend compatibility
            $formattedAwards = $awards->map(function ($award) {
                return $award->getFullDetails();
            });

            return ResponseHelper::success($response, 'Awards fetched successfully', [
                'awards' => $formattedAwards->toArray(),
                'count' => $awards->count(),
                'total' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalCount / $perPage),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch awards', 500, $e->getMessage());
        }
    }

    /**
     * Get featured awards
     * GET /v1/awards/featured
     */
    public function featured(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            
            // Pagination parameters
            $page = (int) ($queryParams['page'] ?? 1);
            $perPage = (int) ($queryParams['per_page'] ?? 20);
            $offset = ($page - 1) * $perPage;

            // Build query for featured awards
            $query = Award::with(['categories.nominees', 'organizer.user', 'images'])
                ->whereIn('status', [Award::STATUS_PUBLISHED, Award::STATUS_COMPLETED])
                ->where('is_featured', true);

            // Filter upcoming only (optional)
            if (isset($queryParams['upcoming']) && $queryParams['upcoming'] === 'true') {
                $query->where('ceremony_date', '>', \Illuminate\Support\Carbon::now());
            }

            // Filter voting open (optional)
            if (isset($queryParams['voting_open']) && $queryParams['voting_open'] === 'true') {
                $query->votingOpen();
            }

            // Get total count before pagination
            $totalCount = $query->count();

            // Get paginated results
            $awards = $query->orderBy('ceremony_date', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            // Format awards for frontend compatibility (same as index)
            $formattedAwards = $awards->map(function ($award) {
                return $award->getFullDetails();
            });

            return ResponseHelper::success($response, 'Featured awards fetched successfully', [
                'awards' => $formattedAwards->toArray(),
                'count' => $awards->count(),
                'total' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalCount / $perPage),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch featured awards', 500, $e->getMessage());
        }
    }

    /**
     * Get single award by ID or slug
     * GET /v1/awards/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $identifier = $args['id'];
            
            // Get user info if authenticated (optional for public endpoint)
            $userRole = $request->getAttribute('user_role');
            $userId = $request->getAttribute('user_id');

            // Try to find by ID first, then by slug
            if (is_numeric($identifier)) {
                $award = Award::with(['organizer.user', 'categories.nominees', 'images'])->find($identifier);
            } else {
                $award = Award::with(['organizer.user', 'categories.nominees', 'images'])
                    ->where('slug', $identifier)
                    ->first();
            }

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Increment views
            $award->increment('views');

            return ResponseHelper::success($response, 'Award fetched successfully', $award->getFullDetails($userRole, $userId));
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch award', 500, $e->getMessage());
        }
    }

    /**
     * Create new award
     * POST /v1/awards
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            // Get data from either JSON body or form data (for multipart/form-data requests)
            $data = $request->getParsedBody();
            if ($data === null) {
                // For multipart/form-data, getParsedBody() returns null
                // In this case, use $_POST which contains the form data
                $data = $_POST;
            }
            if (!is_array($data)) {
                $data = [];
            }
            
            $user = $request->getAttribute('user');
            $uploadedFiles = $request->getUploadedFiles();

            // Get organizer for the user
            $organizer = Organizer::where('user_id', $user->id)->first();
            if (!$organizer && $user->role !== 'admin') {
                return ResponseHelper::error($response, 'Only organizers can create awards', 403);
            }

            // Set organizer_id from authenticated user's organizer profile
            if ($organizer) {
                $data['organizer_id'] = $organizer->id;
            }

            // Validate required fields
            $requiredFields = ['title', 'ceremony_date', 'voting_start', 'voting_end'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return ResponseHelper::error($response, "Field '$field' is required", 400);
                }
            }

            // Validate date order: voting_start < voting_end < ceremony_date
            try {
                $votingStart = $data['voting_start'];
                $votingEnd = $data['voting_end'];
                $ceremonyDate = $data['ceremony_date'];
            } catch (Exception $e) {
                return ResponseHelper::error($response, 'Invalid date format provided: ' . $e->getMessage(), 400);
            }

            // Compare dates - ensure proper chronological order
            if ($votingStart >= $votingEnd) {
                return ResponseHelper::error($response, 
                    'Voting start date must be before voting end date. ' .
                    'Start: ' . $votingStart . ', ' .
                    'End: ' . $votingEnd, 
                    400);
            }

            if ($votingEnd >= $ceremonyDate) {
                return ResponseHelper::error($response, 
                    'Voting must end before the ceremony date. ' .
                    'Voting End: ' . $votingEnd . ', ' .
                    'Ceremony: ' . $ceremonyDate, 
                    400);
            }

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
                $data['slug'] = $this->generateUniqueSlug($baseSlug);
            } else {
                // Even if slug is provided, ensure it's unique
                $data['slug'] = $this->generateUniqueSlug($data['slug']);
            }


            // Set default status if not provided
            if (!isset($data['status'])) {
                $data['status'] = Award::STATUS_DRAFT;
            }

            // Validate status value
            $validStatuses = [Award::STATUS_DRAFT, Award::STATUS_PENDING, Award::STATUS_PUBLISHED, Award::STATUS_CLOSED, Award::STATUS_COMPLETED];
            if (isset($data['status']) && !in_array($data['status'], $validStatuses)) {
                return ResponseHelper::error($response, "Invalid status value. Allowed values: draft, pending, published, closed, completed", 400);
            }

            // Permission check: Organizers can only set status to draft or pending
            if ($user->role !== 'admin' && isset($data['status'])) {
                $allowedOrganizerStatuses = ['draft', 'pending'];
                if (!in_array($data['status'], $allowedOrganizerStatuses)) {
                    return ResponseHelper::error($response, "Organizers can only set status to 'draft' or 'pending'. Admins must approve and publish awards.", 403);
                }
            }

            // Permission check: Only admins can mark awards as featured
            if (isset($data['is_featured']) && $data['is_featured'] && $user->role !== 'admin') {
                return ResponseHelper::error($response, "Only admins can mark awards as featured", 403);
            }
            // Force is_featured to false for non-admins
            if ($user->role !== 'admin') {
                $data['is_featured'] = false;
            }

            // Set default location values if not provided
            if (!isset($data['country'])) {
                $data['country'] = 'Ghana';
            }
            if (!isset($data['region'])) {
                $data['region'] = 'Greater Accra';
            }
            if (!isset($data['city'])) {
                $data['city'] = 'Accra';
            }

            // Handle banner image upload using UploadService
            if (isset($uploadedFiles['banner_image'])) {
                $bannerImage = $uploadedFiles['banner_image'];
                if ($bannerImage->getError() === UPLOAD_ERR_OK) {
                    try {
                        $data['banner_image'] = $this->uploadService->uploadFile($bannerImage, 'banner', 'awards');
                    } catch (Exception $e) {
                        return ResponseHelper::error($response, $e->getMessage(), 400);
                    }
                }
            }

            $award = Award::create($data);

            // Handle award photos upload (multiple) using UploadService
            if (isset($uploadedFiles['award_photos']) && is_array($uploadedFiles['award_photos'])) {
                foreach ($uploadedFiles['award_photos'] as $photo) {
                    if ($photo->getError() === UPLOAD_ERR_OK) {
                        try {
                            $imagePath = $this->uploadService->uploadFile($photo, 'image', 'awards');
                            AwardImage::create([
                                'award_id' => $award->id,
                                'image_path' => $imagePath,
                            ]);
                        } catch (Exception $e) {
                            // Log error but continue with other files
                            error_log("Failed to upload award photo: " . $e->getMessage());
                        }
                    }
                }
            }

            return ResponseHelper::success($response, 'Award created successfully', $award->getFullDetails(), 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to create award', 500, $e->getMessage());
        }
    }

    /**
     * Update award
     * PUT /v1/awards/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            
            // Get data from request - handle both JSON and multipart/form-data
            $data = $request->getParsedBody();
            
            // For multipart/form-data (file uploads), getParsedBody() returns null
            // In this case, use $_POST which contains the form data
            if ($data === null || empty($data)) {
                $data = $_POST;
            }
            
            if (!is_array($data)) {
                $data = [];
            }
            
            $uploadedFiles = $request->getUploadedFiles();
            
            $award = Award::find($id);

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Authorization: Check if user is admin or the award organizer
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                if (!$organizer || $organizer->id !== $award->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this award', 403);
                }
            }

            // Update slug if title changes and slug isn't manually provided
            if (isset($data['title']) && !isset($data['slug'])) {
                $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
                // Only generate unique slug if it's different from current slug
                if ($baseSlug !== $award->slug) {
                    $data['slug'] = $this->generateUniqueSlug($baseSlug);
                }
            } elseif (isset($data['slug']) && $data['slug'] !== $award->slug) {
                $data['slug'] = $this->generateUniqueSlug($data['slug']);
            }

            // // Validate date order if any dates are being updated
            // // Only validate if dates are provided in the request
            $hasVotingStart = isset($data['voting_start']);
            $hasVotingEnd = isset($data['voting_end']);
            $hasCeremonyDate = isset($data['ceremony_date']);
            
            // Log what dates we received for debugging
            error_log('Award Update ID: ' . $id . 'Title: ' . ($data['title'] ?? 'NOT SET') .' - voting_start: ' . ($data['voting_start'] ?? 'NOT SET') . 
                      ', voting_end: ' . ($data['voting_end'] ?? 'NOT SET') . 
                      ', ceremony_date: ' . ($data['ceremony_date'] ?? 'NOT SET'));
            
            if ($hasVotingStart || $hasVotingEnd || $hasCeremonyDate) {
                try {
                    $votingStart = $hasVotingStart ? ($data['voting_start']) : null;
                    $votingEnd = $hasVotingEnd ? ($data['voting_end']) : null;
                    $ceremonyDate = $hasCeremonyDate ? ($data['ceremony_date']) : null;
                } catch (Exception $e) {
                    return ResponseHelper::error($response, 'Invalid date format provided: ' . $e->getMessage(), 400);
                }

                // Compare dates - ensure proper chronological order
                if ($votingStart >= $votingEnd) {
                    return ResponseHelper::error($response, 
                        'Voting start date must be before voting end date. ' .
                        'Start: ' . $votingStart . ', ' .
                        'End: ' . $votingEnd, 
                        400);
                }

                if ($votingEnd >= $ceremonyDate) {
                    return ResponseHelper::error($response, 
                        'Voting must end before the ceremony date. ' .
                        'Voting End: ' . $votingEnd . ', ' .
                        'Ceremony: ' . $ceremonyDate, 
                        400);
                }
            }

            // Validate status value if provided
            if (isset($data['status'])) {
                $validStatuses = [Award::STATUS_DRAFT, Award::STATUS_PENDING, Award::STATUS_PUBLISHED, Award::STATUS_CLOSED, Award::STATUS_COMPLETED];
                if (!in_array($data['status'], $validStatuses)) {
                    return ResponseHelper::error($response, "Invalid status value. Allowed values: draft, pending, published, closed, completed", 400);
                }

                // Permission check: Organizers can only set status to draft or pending
                // They can also move from pending back to draft
                if ($user->role !== 'admin') {
                    $allowedOrganizerStatuses = [Award::STATUS_DRAFT, Award::STATUS_PENDING];
                    if (!in_array($data['status'], $allowedOrganizerStatuses)) {
                        return ResponseHelper::error($response, "Organizers can only set status to 'draft' or 'pending'. Admins must approve and publish awards.", 403);
                    }
                }
            }

            // Permission check: Only admins can mark awards as featured
            if (isset($data['is_featured']) && $data['is_featured'] && $user->role !== 'admin') {
                return ResponseHelper::error($response, "Only admins can mark awards as featured", 403);
            }

            // Prevent non-admins from changing is_featured value
            if ($user->role !== 'admin' && isset($data['is_featured'])) {
                unset($data['is_featured']); // Remove from update data
            }

            // Handle banner image upload using UploadService
            if (isset($uploadedFiles['banner_image'])) {
                $bannerImage = $uploadedFiles['banner_image'];
                if ($bannerImage->getError() === UPLOAD_ERR_OK) {
                    try {
                        $data['banner_image'] = $this->uploadService->replaceFile(
                            $bannerImage,
                            $award->banner_image,
                            'banner',
                            'awards'
                        );
                    } catch (Exception $e) {
                        return ResponseHelper::error($response, $e->getMessage(), 400);
                    }
                }
            }

            $award->update($data);

            // Handle award photos upload (multiple) using UploadService - these are added to existing photos
            if (isset($uploadedFiles['award_photos']) && is_array($uploadedFiles['award_photos'])) {
                foreach ($uploadedFiles['award_photos'] as $photo) {
                    if ($photo->getError() === UPLOAD_ERR_OK) {
                        try {
                            $imagePath = $this->uploadService->uploadFile($photo, 'image', 'awards');
                            AwardImage::create([
                                'award_id' => $award->id,
                                'image_path' => $imagePath,
                            ]);
                        } catch (Exception $e) {
                            // Log error but continue with other files
                            error_log("Failed to upload award photo: " . $e->getMessage());
                        }
                    }
                }
            }

            return ResponseHelper::success($response, 'Award updated successfully', $award->getFullDetails());
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to update award', 500, $e->getMessage());
        }
    }

    /**
     * Delete award
     * DELETE /v1/awards/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $args['id'];
            $award = Award::find($id);

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Authorization: Check if user is admin or the award organizer
            $user = $request->getAttribute('user');
            if ($user->role !== 'admin') {
                $organizer = Organizer::where('user_id', $user->id)->first();
                if (!$organizer || $organizer->id !== $award->organizer_id) {
                    return ResponseHelper::error($response, 'Unauthorized: You do not own this award', 403);
                }
            }

            // Validation: Check if award has any votes
            if ($award->votes()->where('status', 'paid')->exists()) {
                return ResponseHelper::error($response, 'Cannot delete award with existing votes', 400);
            }

            $award->delete();

            return ResponseHelper::success($response, 'Award deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to delete award', 500, $e->getMessage());
        }
    }

    /**
     * Search awards
     * GET /v1/awards/search
     */
    public function search(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $query = $queryParams['query'] ?? '';

            if (empty($query)) {
                return ResponseHelper::error($response, 'Search query is required', 400);
            }

            $awards = Award::with(['categories.nominees', 'organizer.user'])
                ->whereIn('status', [Award::STATUS_PUBLISHED, Award::STATUS_COMPLETED])
                ->where(function ($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                        ->orWhere('description', 'LIKE', "%{$query}%");
                })
                ->get();

            $formattedAwards = $awards->map(function ($award) {
                return $award->getFullDetails();
            });

            return ResponseHelper::success($response, 'Awards found', [
                'awards' => $formattedAwards->toArray(),
                'count' => $awards->count()
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to search awards', 500, $e->getMessage());
        }
    }

    /**
     * Get overall leaderboard for an award
     * GET /v1/awards/{id}/leaderboard
     */
    public function leaderboard(Request $request, Response $response, array $args): Response
    {
        try {
            $awardId = $args['id'];
            $award = Award::with(['categories.nominees'])->find($awardId);

            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            $leaderboard = [];

            foreach ($award->categories as $category) {
                $categoryLeaderboard = $category->nominees->map(function ($nominee) {
                    return [
                        'nominee_id' => $nominee->id,
                        'nominee_name' => $nominee->name,
                        'nominee_image' => $nominee->image,
                        'total_votes' => $nominee->getTotalVotes(),
                    ];
                })->sortByDesc('total_votes')->values()->toArray();

                $leaderboard[] = [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'nominees' => $categoryLeaderboard,
                ];
            }

            return ResponseHelper::success($response, 'Leaderboard fetched successfully', [
                'award' => [
                    'id' => $award->id,
                    'title' => $award->title,
                    'total_votes' => $award->getTotalVotes(),
                ],
                'leaderboard' => $leaderboard,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch leaderboard', 500, $e->getMessage());
        }
    }

    /**
     * Toggle show_results flag for an award
     * PUT /v1/awards/{id}/toggle-results
     */
    public function toggleShowResults(Request $request, Response $response, array $args): Response
    {
        try {
            $awardId = (int) $args['id'];
            $userId = $request->getAttribute('user_id');
            $userRole = $request->getAttribute('user_role');

            // Find the award
            $award = Award::find($awardId);
            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Verify organizer ownership
            if ($userRole !== 'organizer') {
                return ResponseHelper::error($response, 'Only organizers can modify award settings', 403);
            }

            $organizer = Organizer::where('user_id', $userId)->first();
            if (!$organizer || $award->organizer_id !== $organizer->id) {
                return ResponseHelper::error($response, 'You do not have permission to modify this award', 403);
            }

            // Toggle the show_results flag
            $newValue = $award->toggleShowResults();

            return ResponseHelper::success($response, 'Results visibility updated successfully', [
                'show_results' => $newValue,
                'message' => $newValue ? 'Voting results are now visible to the public' : 'Voting results are now hidden from the public'
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to toggle results visibility', 500, $e->getMessage());
        }
    }

    /**
     * Submit award for approval (draft -> pending)
     * PUT /v1/awards/{id}/submit-for-approval
     */
    public function submitForApproval(Request $request, Response $response, array $args): Response
    {
        try {
            $awardId = (int) $args['id'];
            $user = $request->getAttribute('user');

            // Find the award
            $award = Award::find($awardId);
            if (!$award) {
                return ResponseHelper::error($response, 'Award not found', 404);
            }

            // Verify organizer ownership
            if ($user->role !== 'organizer' && $user->role !== 'admin') {
                return ResponseHelper::error($response, 'Only organizers can submit awards for approval', 403);
            }

            $organizer = Organizer::where('user_id', $user->id)->first();
            if ($user->role === 'organizer' && (!$organizer || $award->organizer_id !== $organizer->id)) {
                return ResponseHelper::error($response, 'You do not have permission to submit this award', 403);
            }

            // Check if award is in draft status
            if ($award->status !== Award::STATUS_DRAFT) {
                return ResponseHelper::error(
                    $response,
                    'Only draft awards can be submitted for approval. Current status: ' . $award->status,
                    400
                );
            }

            // Validate that award has required data for submission
            // At minimum, should have title, description, dates, and at least one category
            if (empty($award->title) || empty($award->description)) {
                return ResponseHelper::error($response, 'Award must have a title and description before submission', 400);
            }

            if (empty($award->ceremony_date) || empty($award->voting_start) || empty($award->voting_end)) {
                return ResponseHelper::error($response, 'Award must have ceremony date and voting dates before submission', 400);
            }

            $categoriesCount = $award->categories()->count();
            if ($categoriesCount === 0) {
                return ResponseHelper::error($response, 'Award must have at least one category before submission', 400);
            }

            // Update status to pending
            $award->status = Award::STATUS_PENDING;
            $award->save();

            return ResponseHelper::success($response, 'Award submitted for admin approval successfully', [
                'award_id' => $award->id,
                'status' => $award->status,
                'message' => 'Your award has been submitted and is now pending admin approval'
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to submit award for approval', 500, $e->getMessage());
        }
    }
}


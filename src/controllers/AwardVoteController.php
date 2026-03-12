<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helper\ResponseHelper;
use App\Models\AwardVote;
use App\Models\AwardNominee;
use App\Models\AwardCategory;
use App\Models\Award;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Transaction;
use App\Models\OrganizerBalance;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class AwardVoteController
{
    private string $paystackSecretKey;

    public function __construct()
    {
        $this->paystackSecretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
    }
    /**
     * Initiate a vote (create pending vote and return payment details)
     * POST /v1/nominees/{nomineeId}/vote
     */
    public function initiate(Request $request, Response $response, array $args): Response
    {
        try {
            $nomineeId = $args['nomineeId'];
            $data = $request->getParsedBody();

            // Verify nominee exists
            $nominee = AwardNominee::with(['category', 'award'])->find($nomineeId);
            if (!$nominee) {
                return ResponseHelper::error($response, 'Nominee not found', 404);
            }

            $category = $nominee->category;
            if (!$category) {
                return ResponseHelper::error($response, 'Category not found', 404);
            }

            // Check if voting is active for this category
            if (!$category->isVotingActive()) {
                return ResponseHelper::error($response, 'Voting is not currently active for this category', 400);
            }

            // Validate required fields
            if (empty($data['number_of_votes']) || $data['number_of_votes'] < 1) {
                return ResponseHelper::error($response, 'Invalid number of votes. Must be at least 1', 400);
            }

            if (empty($data['voter_email'])) {
                return ResponseHelper::error($response, 'Voter email is required', 400);
            }

            // Calculate total amount and revenue split
            $numberOfVotes = (int) $data['number_of_votes'];
            $costPerVote = (float) $category->cost_per_vote;
            $totalAmount = $numberOfVotes * $costPerVote;

            // Get award for revenue share calculation
            $award = Award::find($category->award_id);
            $revenueSplit = $award ? $award->calculateRevenueSplit($totalAmount) : [
                'admin_share_percent' => 15,
                'organizer_amount' => $totalAmount * 0.85,
                'admin_amount' => $totalAmount * 0.15 - ($totalAmount * 0.015),
                'payment_fee' => $totalAmount * 0.015,
            ];

            // Generate payment reference
            $reference = 'VOTE-' . $nomineeId . '-' . time() . '-' . uniqid();

            // Create pending vote with financial data
            $vote = AwardVote::create([
                'nominee_id' => $nomineeId,
                'category_id' => $category->id,
                'award_id' => $category->award_id,
                'number_of_votes' => $numberOfVotes,
                'cost_per_vote' => $costPerVote,
                'gross_amount' => $totalAmount,
                'admin_share_percent' => $revenueSplit['admin_share_percent'],
                'admin_amount' => $revenueSplit['admin_amount'],
                'organizer_amount' => $revenueSplit['organizer_amount'],
                'payment_fee' => $revenueSplit['payment_fee'],
                'status' => 'pending',
                'reference' => $reference,
                'voter_name' => $data['voter_name'] ?? null,
                'voter_email' => $data['voter_email'],
                'voter_phone' => $data['voter_phone'] ?? null,
            ]);

            // Return payment information
            $voteDetails = [
                'vote_id' => $vote->id,
                'reference' => $reference,
                'nominee' => [
                    'id' => $nominee->id,
                    'name' => $nominee->name,
                    'image' => $nominee->image,
                ],
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
                'award' => [
                    'id' => $nominee->award->id,
                    'title' => $nominee->award->title,
                ],
                'number_of_votes' => $numberOfVotes,
                'cost_per_vote' => $costPerVote,
                'total_amount' => $totalAmount,
                'voter_email' => $data['voter_email'],
                'voter_name' => $data['voter_name'] ?? null,
                'status' => 'pending',
            ];

            return ResponseHelper::success($response, 'Vote initiated successfully. Proceed to payment.', $voteDetails, 201);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to initiate vote', 500, $e->getMessage());
        }
    }

    /**
     * Confirm vote payment (webhook/callback)
     * POST /v1/votes/confirm
     */
    public function confirmPayment(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();

            // Validate required fields
            if (empty($data['reference'])) {
                return ResponseHelper::error($response, 'Payment reference is required', 400);
            }

            $reference = $data['reference'];

            // Find vote by reference with award relationship
            $vote = AwardVote::with(['category', 'award.organizer'])->where('reference', $reference)->first();

            if (!$vote) {
                error_log("Vote confirmation failed: Vote not found for reference: {$reference}");
                return ResponseHelper::error($response, 'Vote not found', 404);
            }

            // Check if already paid
            if ($vote->isPaid()) {
                return ResponseHelper::success($response, 'Vote already confirmed', $vote->getDetails());
            }

            // Verify payment with Paystack
            $paymentData = $this->verifyPaystackPayment($reference);

            if (!$paymentData) {
                error_log("Vote confirmation failed: Paystack verification failed for reference: {$reference}");
                return ResponseHelper::error($response, 'Payment verification failed: ', 400);
            }

            // Check if payment was successful
            if ($paymentData['status'] !== 'success') {
                error_log("Vote confirmation failed: Payment status not success. Status: {$paymentData['status']}");
                return ResponseHelper::error($response, 'Payment was not successful', 400, [
                    'payment_status' => $paymentData['status'],
                    'gateway_response' => $paymentData['gateway_response'] ?? null,
                ]);
            }

            // Verify amount matches (convert from kobo/pesewas to main currency)
            $expectedAmount = $vote->getTotalAmount();
            $paidAmount = $paymentData['amount'] / 100;

            // Allow for small floating point differences (1 pesewa tolerance)
            if (abs($paidAmount - $expectedAmount) > 0.02) {
                error_log("Vote confirmation failed: Amount mismatch. Expected: {$expectedAmount}, Paid: {$paidAmount}");
                return ResponseHelper::error($response, 'Payment amount mismatch', 400, [
                    'expected' => $expectedAmount,
                    'paid' => $paidAmount,
                ]);
            }

            // Mark vote as paid - use direct update for reliability
            $vote->status = 'paid';
            $vote->save();
            
            error_log("Vote confirmed successfully: Vote ID {$vote->id}, Reference: {$reference}");

            // Create transaction and update organizer balance
            $award = $vote->award;
            if ($award && $award->organizer) {
                $organizerId = $award->organizer->id;

                // Create transaction
                Transaction::createVotePurchase(
                    $organizerId,
                    $vote->award_id,
                    $vote->id,
                    (float) $vote->gross_amount,
                    (float) $vote->admin_amount,
                    (float) $vote->organizer_amount,
                    (float) $vote->payment_fee,
                    "Vote purchase: {$award->title}"
                );

                // Update organizer balance (add to pending)
                $balance = OrganizerBalance::getOrCreate($organizerId);
                $balance->addPendingEarnings((float) $vote->organizer_amount);
            }

            return ResponseHelper::success($response, 'Vote payment confirmed successfully', $vote->fresh()->getDetails());
        } catch (Exception $e) {
            error_log("Vote confirmation exception: " . $e->getMessage());
            return ResponseHelper::error($response, 'Failed to confirm vote payment', 500, $e->getMessage());
        }
    }

    /**
     * Get vote details by reference
     * GET /v1/votes/reference/{reference}
     */
    public function getByReference(Request $request, Response $response, array $args): Response
    {
        try {
            $reference = $args['reference'];

            $vote = AwardVote::with(['nominee', 'category', 'award'])
                ->where('reference', $reference)
                ->first();

            if (!$vote) {
                return ResponseHelper::error($response, 'Vote not found', 404);
            }

            $voteDetails = $vote->getDetails();
            
            // Add related information
            $voteDetails['nominee'] = $vote->nominee ? [
                'id' => $vote->nominee->id,
                'name' => $vote->nominee->name,
                'image' => $vote->nominee->image,
            ] : null;

            $voteDetails['category'] = $vote->category ? [
                'id' => $vote->category->id,
                'name' => $vote->category->name,
            ] : null;

            $voteDetails['award'] = $vote->award ? [
                'id' => $vote->award->id,
                'title' => $vote->award->title,
            ] : null;

            return ResponseHelper::success($response, 'Vote details fetched successfully', $voteDetails);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch vote details', 500, $e->getMessage());
        }
    }

    /**
     * Get all votes for a nominee
     * GET /v1/nominees/{nomineeId}/votes
     */
    public function getByNominee(Request $request, Response $response, array $args): Response
    {
        try {
            $nomineeId = $args['nomineeId'];
            $queryParams = $request->getQueryParams();
            $statusFilter = $queryParams['status'] ?? null;

            // Verify nominee exists
            $nominee = AwardNominee::find($nomineeId);
            if (!$nominee) {
                return ResponseHelper::error($response, 'Nominee not found', 404);
            }

            // Build query
            $query = AwardVote::where('nominee_id', $nomineeId);

            if ($statusFilter && in_array($statusFilter, ['pending', 'paid'])) {
                $query->where('status', $statusFilter);
            }

            $votes = $query->orderBy('created_at', 'desc')->get();

            $votesData = $votes->map(function ($vote) {
                return $vote->getDetails();
            });

            $summary = [
                'total_votes' => $votes->where('status', 'paid')->sum('number_of_votes'),
                'total_amount' => $votes->where('status', 'paid')->sum(function ($vote) {
                    return $vote->getTotalAmount();
                }),
                'paid_count' => $votes->where('status', 'paid')->count(),
                'pending_count' => $votes->where('status', 'pending')->count(),
            ];

            return ResponseHelper::success($response, 'Nominee votes fetched successfully', [
                'votes' => $votesData->toArray(),
                'summary' => $summary,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch nominee votes', 500, $e->getMessage());
        }
    }

    /**
     * Get all votes for a category
     * GET /v1/award-categories/{categoryId}/votes
     */
    public function getByCategory(Request $request, Response $response, array $args): Response
    {
        try {
            $categoryId = $args['categoryId'];
            $queryParams = $request->getQueryParams();
            $statusFilter = $queryParams['status'] ?? null;

            // Verify category exists
            $category = AwardCategory::find($categoryId);
            if (!$category) {
                return ResponseHelper::error($response, 'Category not found', 404);
            }

            // Build query
            $query = AwardVote::with('nominee')
                ->where('category_id', $categoryId);

            if ($statusFilter && in_array($statusFilter, ['pending', 'paid'])) {
                $query->where('status', $statusFilter);
            }

            $votes = $query->orderBy('created_at', 'desc')->get();

            $votesData = $votes->map(function ($vote) {
                $details = $vote->getDetails();
                $details['nominee_name'] = $vote->nominee ? $vote->nominee->name : null;
                return $details;
            });

            $summary = [
                'total_votes' => $votes->where('status', 'paid')->sum('number_of_votes'),
                'total_amount' => $votes->where('status', 'paid')->sum(function ($vote) {
                    return $vote->getTotalAmount();
                }),
                'paid_count' => $votes->where('status', 'paid')->count(),
                'pending_count' => $votes->where('status', 'pending')->count(),
            ];

            return ResponseHelper::success($response, 'Category votes fetched successfully', [
                'votes' => $votesData->toArray(),
                'summary' => $summary,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch category votes', 500, $e->getMessage());
        }
    }

    /**
     * Get all votes for an award (organizer only)
     * GET /v1/awards/{awardId}/votes
     */
    public function getByAward(Request $request, Response $response, array $args): Response
    {
        try {
            $awardId = $args['awardId'];
            $user = $request->getAttribute('user');
            $queryParams = $request->getQueryParams();
            $statusFilter = $queryParams['status'] ?? null;

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

            // Build query
            $query = AwardVote::with(['nominee', 'category'])
                ->where('award_id', $awardId);

            if ($statusFilter && in_array($statusFilter, ['pending', 'paid'])) {
                $query->where('status', $statusFilter);
            }

            $votes = $query->orderBy('created_at', 'desc')->get();

            $votesData = $votes->map(function ($vote) {
                $details = $vote->getDetails();
                $details['nominee_name'] = $vote->nominee ? $vote->nominee->name : null;
                $details['category_name'] = $vote->category ? $vote->category->name : null;
                return $details;
            });

            $summary = [
                'total_votes' => $votes->where('status', 'paid')->sum('number_of_votes'),
                'total_revenue' => $votes->where('status', 'paid')->sum(function ($vote) {
                    return $vote->getTotalAmount();
                }),
                'paid_transactions' => $votes->where('status', 'paid')->count(),
                'pending_transactions' => $votes->where('status', 'pending')->count(),
            ];

            return ResponseHelper::success($response, 'Award votes fetched successfully', [
                'votes' => $votesData->toArray(),
                'summary' => $summary,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch award votes', 500, $e->getMessage());
        }
    }

    /**
     * Get voting leaderboard for a category
     * GET /v1/award-categories/{categoryId}/leaderboard
     */
    public function getLeaderboard(Request $request, Response $response, array $args): Response
    {
        try {
            $categoryId = $args['categoryId'];

            // Verify category exists
            $category = AwardCategory::with('nominees')->find($categoryId);
            if (!$category) {
                return ResponseHelper::error($response, 'Category not found', 404);
            }

            // Get nominees with vote counts
            $nominees = $category->nominees->map(function ($nominee) use ($category) {
                return [
                    'id' => $nominee->id,
                    'name' => $nominee->name,
                    'image' => $nominee->image,
                    'description' => $nominee->description,
                    'total_votes' => $nominee->getTotalVotes(),
                    'total_revenue' => $nominee->getTotalRevenue($category->cost_per_vote),
                    'percentage' => 0, // Will be calculated below
                ];
            });

            // Calculate total votes for percentage
            $totalVotes = $nominees->sum('total_votes');
            
            // Calculate percentages and sort
            $leaderboard = $nominees->map(function ($nominee) use ($totalVotes) {
                $nominee['percentage'] = $totalVotes > 0 
                    ? round(($nominee['total_votes'] / $totalVotes) * 100, 2) 
                    : 0;
                return $nominee;
            })->sortByDesc('total_votes')->values();

            return ResponseHelper::success($response, 'Leaderboard fetched successfully', [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'total_votes' => $totalVotes,
                ],
                'leaderboard' => $leaderboard->toArray(),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch leaderboard', 500, $e->getMessage());
        }
    }

    /**
     * Get award-wide voting statistics (organizer only)
     * GET /v1/awards/{awardId}/vote-stats
     */
    public function getAwardStats(Request $request, Response $response, array $args): Response
    {
        try {
            $awardId = $args['awardId'];
            $user = $request->getAttribute('user');

            // Verify award exists
            $award = Award::with(['categories.nominees'])->find($awardId);
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

            // Calculate overall stats
            $totalVotes = AwardVote::where('award_id', $awardId)
                ->where('status', 'paid')
                ->sum('number_of_votes');

            $totalRevenue = 0;
            $categoryStats = [];

            foreach ($award->categories as $category) {
                $categoryVotes = $category->getTotalVotes();
                $categoryRevenue = $category->getCategoryTotalRevenue();
                $totalRevenue += $categoryRevenue;

                $categoryStats[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'total_votes' => $categoryVotes,
                    'total_revenue' => $categoryRevenue,
                    'nominees_count' => $category->nominees()->count(),
                ];
            }

            $stats = [
                'total_categories' => $award->categories->count(),
                'total_nominees' => AwardNominee::where('award_id', $awardId)->count(),
                'total_votes' => $totalVotes,
                'total_revenue' => $totalRevenue,
                'paid_transactions' => AwardVote::where('award_id', $awardId)->where('status', 'paid')->count(),
                'pending_transactions' => AwardVote::where('award_id', $awardId)->where('status', 'pending')->count(),
                'category_breakdown' => $categoryStats,
            ];

            return ResponseHelper::success($response, 'Award vote statistics fetched successfully', $stats);
        } catch (Exception $e) {
            return ResponseHelper::error($response, 'Failed to fetch award statistics', 500, $e->getMessage());
        }
    }

    /**
     * Verify payment with Paystack API
     * 
     * @param string $reference Payment reference
     * @return array|null Payment data if successful, null if failed
     */
    private function verifyPaystackPayment(string $reference): ?array
    {
        try {
            $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $this->paystackSecretKey,
                "Content-Type: application/json",
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            // SSL Configuration
            // For production, download cacert.pem from https://curl.se/ca/cacert.pem
            // and set: curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem');
            $isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';
            
            if (!$isProduction) {
                // Development: Disable SSL verification (NOT for production!)
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            } else {
                // Production: Verify SSL properly
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                
                // If you have a CA bundle file, uncomment and set path:
                // $caPath = $_ENV['CURL_CA_BUNDLE'] ?? null;
                // if ($caPath && file_exists($caPath)) {
                //     curl_setopt($ch, CURLOPT_CAINFO, $caPath);
                // }
            }
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                error_log("Paystack verification cURL error: " . $err);
                return null;
            }

            if ($httpCode !== 200) {
                error_log("Paystack verification HTTP error: {$httpCode}, Response: {$result}");
                return null;
            }

            $paystackResponse = json_decode($result, true);

            if (!$paystackResponse || !isset($paystackResponse['status']) || !$paystackResponse['status']) {
                error_log("Paystack verification failed: Invalid response - " . ($result ?? 'empty'));
                return null;
            }

            error_log("Paystack verification successful for reference: {$reference}");
            return $paystackResponse['data'] ?? null;
        } catch (Exception $e) {
            error_log("Paystack verification exception: " . $e->getMessage());
            return null;
        }
    }
}

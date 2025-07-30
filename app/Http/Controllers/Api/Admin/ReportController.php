<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get paginated list of reports with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $status = $request->get('status');
            $reason = $request->get('reason');
            $severity = $request->get('severity');
            $assignedTo = $request->get('assigned_to');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $query = Report::with([
                'reporter:id,first_name,last_name,email',
                'reportedUser:id,first_name,last_name,email,status',
                'assignedAdmin:id,first_name,last_name'
            ]);

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($reason) {
                $query->where('type', $reason);
            }

            if ($severity) {
                $query->where('severity', $severity);
            }

            if ($assignedTo) {
                $query->where('assigned_to', $assignedTo);
            }

            // Apply sorting
            $allowedSortFields = ['created_at', 'severity', 'status', 'reason'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $reports = $query->paginate($perPage);

            // Format response
            $reports->getCollection()->transform(function ($report) {
                return [
                    'id' => $report->id,
                    'reporter' => [
                        'id' => $report->reporter->id,
                        'name' => $report->reporter->first_name . ' ' . $report->reporter->last_name,
                        'email' => $report->reporter->email,
                    ],
                    'reported_user' => [
                        'id' => $report->reportedUser->id,
                        'name' => $report->reportedUser->first_name . ' ' . $report->reportedUser->last_name,
                        'email' => $report->reportedUser->email,
                        'status' => $report->reportedUser->status,
                    ],
                    'type' => $report->type,
                    'description' => $report->description,
                    'severity' => $report->severity,
                    'status' => $report->status,
                    'priority' => $report->priority,
                    'assigned_moderator' => $report->assignedAdmin ? [
                        'id' => $report->assignedAdmin->id,
                        'name' => $report->assignedAdmin->first_name . ' ' . $report->assignedAdmin->last_name,
                    ] : null,
                    'evidence_count' => $report->evidence_data ? count(json_decode($report->evidence_data, true)['photos'] ?? []) : 0,
                    'created_at' => $report->created_at,
                    'updated_at' => $report->updated_at,
                    'days_open' => $report->created_at->diffInDays(now()),
                ];
            });

            // Get summary statistics
            $summary = [
                'total_reports' => Report::count(),
                'pending_reports' => Report::where('status', 'pending')->count(),
                'in_progress_reports' => Report::where('status', 'in_progress')->count(),
                'resolved_reports' => Report::where('status', 'resolved')->count(),
                'by_severity' => Report::selectRaw('severity, count(*) as count')
                    ->groupBy('severity')
                    ->pluck('count', 'severity')
                    ->toArray(),
                'by_reason' => Report::selectRaw('type, count(*) as count')
                    ->groupBy('type')
                    ->orderBy('count', 'desc')
                    ->limit(5)
                    ->pluck('count', 'type')
                    ->toArray(),
            ];

            return response()->json([
                'success' => true,
                'data' => $reports->getCollection(),
                'summary' => $summary,
                'filters' => [
                    'statuses' => ['pending', 'in_progress', 'resolved', 'dismissed'],
                    'severities' => ['low', 'medium', 'high', 'critical'],
                    'reasons' => ['inappropriate_photos', 'fake_profile', 'harassment', 'spam', 'scam', 'inappropriate_behavior', 'other'],
                    'moderators' => User::role(['admin', 'moderator'])->get(['id', 'first_name', 'last_name']),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin reports list error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed report information
     */
    public function show(Request $request, Report $report): JsonResponse
    {
        try {
            $report->load([
                'reporter:id,first_name,last_name,email,phone,country_code,registration_ip',
                'reportedUser:id,first_name,last_name,email,phone,country_code,status,last_active_at,created_at',
                'reportedUser.profile',
                'reportedUser.photos',
                'assignedAdmin:id,first_name,last_name,email'
            ]);

            // Get related reports about the same user
            $relatedReports = Report::where('reported_user_id', $report->reported_user_id)
                ->where('id', '!=', $report->id)
                ->with('reporter:id,first_name,last_name')
                ->latest()
                ->limit(10)
                ->get();

            // Get previous reports by the same reporter
            $reporterHistory = Report::where('reporter_id', $report->reporter_id)
                ->where('id', '!=', $report->id)
                ->with('reportedUser:id,first_name,last_name')
                ->latest()
                ->limit(5)
                ->get();

            $evidenceData = $report->evidence_data ? json_decode($report->evidence_data, true) : null;

            $data = [
                'id' => $report->id,
                'type' => $report->type,
                'reason' => $report->reason,
                'description' => $report->description,
                'severity' => $report->severity,
                'status' => $report->status,
                'priority' => $report->priority,
                'context' => $report->context,
                'moderator_notes' => $report->moderator_notes,
                'resolution' => $report->resolution,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at,
                'resolved_at' => $report->resolved_at,
                'reporter' => [
                    'id' => $report->reporter->id,
                    'name' => $report->reporter->first_name . ' ' . $report->reporter->last_name,
                    'email' => $report->reporter->email,
                    'phone' => $report->reporter->phone,
                    'country_code' => $report->reporter->country_code,
                    'registration_ip' => $report->reporter->registration_ip,
                    'reports_filed_count' => Report::where('reporter_id', $report->reporter_id)->count(),
                ],
                'reported_user' => [
                    'id' => $report->reportedUser->id,
                    'name' => $report->reportedUser->first_name . ' ' . $report->reportedUser->last_name,
                    'email' => $report->reportedUser->email,
                    'phone' => $report->reportedUser->phone,
                    'country_code' => $report->reportedUser->country_code,
                    'status' => $report->reportedUser->status,
                    'last_active_at' => $report->reportedUser->last_active_at,
                    'account_age_days' => $report->reportedUser->created_at->diffInDays(now()),
                    'reports_received_count' => Report::where('reported_user_id', $report->reported_user_id)->count(),
                    'profile' => $report->reportedUser->profile,
                    'photos_count' => $report->reportedUser->photos->count(),
                ],
                'assigned_moderator' => $report->assignedModerator ? [
                    'id' => $report->assignedModerator->id,
                    'name' => $report->assignedModerator->first_name . ' ' . $report->assignedModerator->last_name,
                    'email' => $report->assignedModerator->email,
                ] : null,
                'evidence' => $evidenceData,
                'related_reports' => $relatedReports->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'reason' => $r->reason,
                        'status' => $r->status,
                        'severity' => $r->severity,
                        'reporter_name' => $r->reporter->first_name . ' ' . $r->reporter->last_name,
                        'created_at' => $r->created_at,
                    ];
                }),
                'reporter_history' => $reporterHistory->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'reason' => $r->reason,
                        'status' => $r->status,
                        'reported_user_name' => $r->reportedUser->first_name . ' ' . $r->reportedUser->last_name,
                        'created_at' => $r->created_at,
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Admin report show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get report details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update report status
     */
    public function updateStatus(Request $request, Report $report): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,under_review,resolved,dismissed',
            'moderator_notes' => 'sometimes|string|max:1000',
            'assigned_to' => 'sometimes|integer|exists:users,id',
            'priority' => 'sometimes|in:low,medium,high,urgent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $oldStatus = $report->status;
            $newStatus = $request->status;

            $updateData = [
                'status' => $newStatus,
                'updated_at' => now(),
            ];

            if ($request->has('moderator_notes')) {
                $updateData['moderator_notes'] = $request->moderator_notes;
            }

            if ($request->has('assigned_to')) {
                $updateData['assigned_to'] = $request->assigned_to;
            }

            if ($request->has('priority')) {
                $updateData['priority'] = $request->priority;
            }

            if ($newStatus === 'resolved' || $newStatus === 'dismissed') {
                $updateData['resolved_at'] = now();
                $updateData['resolved_by'] = $request->user()->id;
            }

            $report->update($updateData);

            // Log the status change
            Log::info('Report status updated', [
                'report_id' => $report->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Report status updated to {$newStatus}",
                'data' => [
                    'report_id' => $report->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'updated_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin update report status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update report status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Take action on a report (ban, suspend, warn user, etc.)
     */
    public function takeAction(Request $request, Report $report): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:warn,suspend,ban,dismiss,require_profile_update',
            'duration_days' => 'required_if:action,suspend|integer|min:1|max:365',
            'reason' => 'required|string|max:500',
            'admin_notes' => 'sometimes|string|max:1000',
            'notify_user' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $action = $request->action;
            $reportedUser = $report->reportedUser;

            DB::transaction(function () use ($report, $request, $action, $reportedUser) {
                switch ($action) {
                    case 'warn':
                        $this->warnUser($reportedUser, $request->reason, $request->user()->id);
                        break;

                    case 'suspend':
                        $this->suspendUser($reportedUser, $request->duration_days, $request->reason, $request->user()->id);
                        break;

                    case 'ban':
                        $this->banUser($reportedUser, $request->reason, $request->user()->id);
                        break;

                    case 'require_profile_update':
                        $this->requireProfileUpdate($reportedUser, $request->reason, $request->user()->id);
                        break;

                    case 'dismiss':
                        // No action taken on user, just resolve report
                        break;
                }

                // Update report
                $report->update([
                    'status' => in_array($action, ['dismiss']) ? 'dismissed' : 'resolved',
                    'resolution' => $request->reason,
                    'moderator_notes' => $request->get('admin_notes'),
                    'resolved_at' => now(),
                    'resolved_by' => $request->user()->id,
                    'action_taken' => $action,
                ]);

                // Send notification to reporter
                if ($request->get('notify_user', true)) {
                    Notification::create([
                        'user_id' => $report->reporter_id,
                        'type' => 'report_resolved',
                        'title' => 'Report Resolved',
                        'message' => 'Your report has been reviewed and action has been taken. Thank you for helping keep our community safe.',
                        'data' => json_encode([
                            'report_id' => $report->id,
                            'action_taken' => $action,
                        ]),
                    ]);
                }

                Log::info('Report action taken', [
                    'report_id' => $report->id,
                    'action' => $action,
                    'reported_user_id' => $reportedUser->id,
                    'taken_by' => $request->user()->id,
                    'reason' => $request->reason,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => "Action '{$action}' taken successfully",
                'data' => [
                    'report_id' => $report->id,
                    'action_taken' => $action,
                    'resolved_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin take report action error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to take action on report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update report status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_ids' => 'required|array|min:1',
            'report_ids.*' => 'required|integer|exists:reports,id',
            'status' => 'required|in:pending,in_progress,resolved,dismissed',
            'moderator_notes' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $reportIds = $request->report_ids;
            $status = $request->status;
            $moderatorNotes = $request->get('moderator_notes');

            $updateData = [
                'status' => $status,
                'updated_at' => now(),
            ];

            if ($moderatorNotes) {
                $updateData['moderator_notes'] = $moderatorNotes;
            }

            if (in_array($status, ['resolved', 'dismissed'])) {
                $updateData['resolved_at'] = now();
                $updateData['resolved_by'] = $request->user()->id;
            }

            $updatedCount = Report::whereIn('id', $reportIds)->update($updateData);

            Log::info('Bulk report status updated', [
                'report_ids' => $reportIds,
                'status' => $status,
                'updated_count' => $updatedCount,
                'updated_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} reports updated to {$status}",
                'data' => [
                    'updated_count' => $updatedCount,
                    'status' => $status,
                    'updated_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin bulk update reports error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Warn a user
     */
    private function warnUser(User $user, string $reason, int $moderatorId): void
    {
        // Issue warning using the warning system
        $admin = User::find($moderatorId);
        \App\Models\UserWarning::issueWarning(
            $user,
            'inappropriate_content', // type
            3, // severity (moderate)
            $reason,
            'Account Warning',
            ['messaging_disabled'], // evidence
            $admin, // issuedBy
            null, // reportId
            null, // templateId
            null // expiresAt
        );
    }

    /**
     * Suspend a user
     */
    private function suspendUser(User $user, int $durationDays, string $reason, int $moderatorId): void
    {
        $suspensionEndDate = now()->addDays($durationDays);

        $user->update([
            'status' => 'suspended',
            'suspension_end_date' => $suspensionEndDate,
            'suspension_reason' => $reason,
            'suspended_by' => $moderatorId,
            'suspended_at' => now(),
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        // Send notification
        Notification::create([
            'user_id' => $user->id,
            'type' => 'account_suspended',
            'title' => 'Account Suspended',
            'message' => "Your account has been suspended for {$durationDays} days. Reason: {$reason}",
            'data' => json_encode([
                'duration_days' => $durationDays,
                'reason' => $reason,
                'suspension_end_date' => $suspensionEndDate,
            ]),
        ]);
    }

    /**
     * Ban a user
     */
    private function banUser(User $user, string $reason, int $moderatorId): void
    {
        $user->update([
            'status' => 'banned',
            'ban_reason' => $reason,
            'banned_by' => $moderatorId,
            'banned_at' => now(),
            'is_permanent_ban' => true,
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        // Cancel subscriptions
        $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

        // Send notification
        Notification::create([
            'user_id' => $user->id,
            'type' => 'account_banned',
            'title' => 'Account Banned',
            'message' => "Your account has been permanently banned. Reason: {$reason}",
            'data' => json_encode([
                'reason' => $reason,
                'banned_at' => now(),
            ]),
        ]);
    }

    /**
     * Require profile update
     */
    private function requireProfileUpdate(User $user, string $reason, int $moderatorId): void
    {
        $user->update([
            'profile_status' => 'requires_update',
            'status_change_reason' => $reason,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'profile_update_required',
            'title' => 'Profile Update Required',
            'message' => "Please update your profile to comply with our guidelines. Reason: {$reason}",
            'data' => json_encode([
                'reason' => $reason,
                'required_at' => now(),
            ]),
        ]);
    }
} 
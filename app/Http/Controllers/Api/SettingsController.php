<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    /**
     * Get user settings
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'preferences']);

        try {
            $settings = [
                'account' => [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'date_of_birth' => $user->date_of_birth,
                    'gender' => $user->gender,
                    'language' => $user->language,
                    'country_code' => $user->country_code,
                ],
                'privacy' => [
                    'show_profile_to_premium_only' => $user->profile?->show_profile_to_premium_only ?? false,
                    'show_contact_info' => $user->profile?->show_contact_info ?? true,
                    'show_horoscope' => $user->profile?->show_horoscope ?? true,
                    'show_income' => $user->profile?->show_income ?? false,
                    'show_last_seen' => $user->profile?->show_last_seen ?? true,
                    'allow_photo_requests' => $user->profile?->allow_photo_requests ?? true,
                ],
                'notifications' => json_decode($user->notification_preferences ?? '{}', true) ?: [
                    'email_notifications' => true,
                    'push_notifications' => true,
                    'notification_types' => ['match', 'message', 'like', 'super_like', 'subscription'],
                    'quiet_hours_start' => null,
                    'quiet_hours_end' => null,
                    'frequency' => 'immediate',
                ],
                'matching' => [
                    'auto_accept_matches' => $user->preferences?->auto_accept_matches ?? false,
                    'show_me_on_search' => $user->preferences?->show_me_on_search ?? true,
                    'preferred_distance_km' => $user->preferences?->preferred_distance_km ?? 50,
                ],
                'subscription' => [
                    'is_premium' => $user->is_premium_active,
                    'premium_expires_at' => $user->premium_expires_at,
                    'current_plan' => $user->activeSubscription?->plan_type ?? 'free',
                ],
                'security' => [
                    'two_factor_enabled' => $user->twoFactorAuth?->enabled ?? false,
                    'two_factor_method' => $user->twoFactorAuth?->method ?? 'totp',
                    'login_notifications' => true,
                    'last_password_change' => $user->last_password_change?->toDateString(),
                    'password_expired' => $user->password_expired ?? false,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update general settings
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
            'language' => 'sometimes|string|size:2|in:en,si,ta',
            'country_code' => 'sometimes|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user->update($request->only([
                'first_name', 'last_name', 'phone', 'language', 'country_code'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => [
                    'user' => $user->only([
                        'first_name', 'last_name', 'phone', 'language', 'country_code'
                    ])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update privacy settings
     */
    public function updatePrivacy(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'show_profile_to_premium_only' => 'sometimes|boolean',
            'show_contact_info' => 'sometimes|boolean',
            'show_horoscope' => 'sometimes|boolean',
            'show_income' => 'sometimes|boolean',
            'show_last_seen' => 'sometimes|boolean',
            'allow_photo_requests' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Ensure user has a profile
            if (!$user->profile) {
                $user->profile()->create(['user_id' => $user->id]);
                $user->refresh();
            }

            $user->profile->update($request->only([
                'show_profile_to_premium_only',
                'show_contact_info',
                'show_horoscope',
                'show_income',
                'show_last_seen',
                'allow_photo_requests',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Privacy settings updated successfully',
                'data' => [
                    'privacy' => $user->profile->only([
                        'show_profile_to_premium_only',
                        'show_contact_info',
                        'show_horoscope',
                        'show_income',
                        'show_last_seen',
                        'allow_photo_requests',
                    ])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update privacy settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update notification settings
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'notification_types' => 'sometimes|array',
            'notification_types.*' => 'in:match,message,like,super_like,profile_view,subscription,admin,system',
            'quiet_hours_start' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_end' => 'sometimes|nullable|date_format:H:i',
            'frequency' => 'sometimes|in:immediate,hourly,daily,weekly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $currentPreferences = json_decode($user->notification_preferences ?? '{}', true);
            
            $newPreferences = array_merge($currentPreferences, $request->only([
                'email_notifications',
                'push_notifications',
                'notification_types',
                'quiet_hours_start',
                'quiet_hours_end',
                'frequency',
            ]));

            $user->update(['notification_preferences' => json_encode($newPreferences)]);

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully',
                'data' => [
                    'notification_preferences' => $newPreferences
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Revoke all other tokens for security
            $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update email
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 400);
            }

            $user->update([
                'email' => $request->email,
                'email_verified_at' => null, // Require re-verification
            ]);

            // Send email verification
            $this->sendEmailVerification($user, $validatedData['email']);
            // $user->sendEmailVerificationNotification();

            return response()->json([
                'success' => true,
                'message' => 'Email updated successfully. Please verify your new email address.',
                'data' => [
                    'email' => $user->email,
                    'email_verified' => false,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate account (temporary)
     */
    public function deactivateAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'reason' => 'sometimes|string|max:500',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 400);
            }

            $user->update([
                'status' => 'deactivated',
                'deactivated_at' => now(),
                'deactivation_reason' => $request->get('reason'),
            ]);

            // Revoke all tokens
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Account deactivated successfully. You can reactivate by logging in again.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete account (permanent)
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'confirmation' => 'required|string|in:DELETE_MY_ACCOUNT',
            'reason' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect'
                ], 400);
            }

            // Store deletion info before deleting
            $deletionData = [
                'user_id' => $user->id,
                'email' => $user->email,
                'deleted_at' => now(),
                'reason' => $request->get('reason'),
                'ip_address' => $request->ip(),
            ];

            // Store in deleted_accounts table for GDPR compliance
            \App\Models\DeletedAccount::createFromUser($user, 'user', $request->get('reason'));

            // Soft delete or anonymize user data
            $user->update([
                'status' => 'deleted',
                'email' => 'deleted_' . $user->id . '@soulsync.com',
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'phone' => null,
                'deleted_at' => now(),
            ]);

            // Revoke all tokens
            $user->tokens()->delete();

            // Clean up related data while preserving integrity
            $this->cleanupUserData($user);

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully. We\'re sorry to see you go.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account statistics
     */
    public function getAccountStats(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $stats = [
                'profile_completion' => $this->calculateProfileCompletion($user),
                'total_matches' => $user->matches()->whereNotNull('matched_at')->count(),
                'total_likes_sent' => $user->matches()->where('action', 'like')->count(),
                'total_likes_received' => \App\Models\UserMatch::where('target_user_id', $user->id)
                    ->where('action', 'like')->count(),
                'total_conversations' => $user->conversations()->count(),
                'total_messages_sent' => $user->sentMessages()->count(),
                'profile_views' => $user->total_profile_views ?? 0,
                'photos_count' => $user->photos()->count(),
                'account_age_days' => $user->created_at->diffInDays(now()),
                'last_active' => $user->last_active_at,
                'subscription_status' => [
                    'is_premium' => $user->is_premium_active,
                    'plan' => $user->activeSubscription?->plan_type ?? 'free',
                    'expires_at' => $user->premium_expires_at,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get account statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export user data (GDPR compliance)
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $userData = [
                'personal_info' => $user->only([
                    'first_name', 'last_name', 'email', 'phone', 'date_of_birth',
                    'gender', 'language', 'country_code', 'created_at'
                ]),
                'profile' => $user->profile?->toArray(),
                'preferences' => $user->preferences?->toArray(),
                'photos' => $user->photos()->get(['file_path', 'created_at'])->toArray(),
                'matches' => $user->matches()->get(['target_user_id', 'action', 'created_at'])->toArray(),
                'messages' => $user->sentMessages()->get(['content', 'type', 'created_at'])->toArray(),
                'subscriptions' => $user->subscriptions()->get(['plan_type', 'amount_usd', 'created_at'])->toArray(),
                'notifications' => $user->notifications()->get(['type', 'title', 'created_at'])->toArray(),
            ];

            // Generate downloadable file based on format preference
            $format = $request->get('format', 'json');
            
            if ($format === 'csv') {
                return $this->generateCSVExport($userData, $user);
            } elseif ($format === 'pdf') {
                return $this->generatePDFExport($userData, $user);
            }

            // Default JSON response
            return response()->json([
                'success' => true,
                'message' => 'Data export generated successfully',
                'data' => $userData,
                'download_formats' => ['json', 'csv', 'pdf']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up user data while preserving integrity
     */
    private function cleanupUserData($user): void
    {
        try {
            // Anonymize photos but keep files for content integrity
            $user->photos()->update([
                'caption' => null,
                'is_private' => true,
                'is_verified' => false,
            ]);

            // Anonymize messages
            $user->sentMessages()->update(['content' => '[Message deleted]']);
            $user->receivedMessages()->update(['content' => '[Message deleted]']);

            // Keep matches but anonymize actions
            $user->matches()->update(['message' => null]);

            // Cancel active subscriptions
            $user->subscriptions()->where('status', 'active')->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_note' => 'Account deleted'
            ]);

            // Disable 2FA
            $user->twoFactorAuth?->disable();

        } catch (\Exception $e) {
            \Log::error('Error cleaning up user data', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send email verification
     */
    private function sendEmailVerification($user, $newEmail): void
    {
        try {
            // Generate verification code
            $code = \App\Models\TwoFactorCode::generateFor($user, 'email_verification');
            
            // Send email verification using CommunicationService
            $communicationService = app(\App\Services\CommunicationService::class);
            $communicationService->sendVerificationEmail($newEmail, $code->code, 'email_verification');
        } catch (\Exception $e) {
            \Log::error('Failed to send email verification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate CSV export
     */
    private function generateCSVExport($userData, $user)
    {
        $csv = "Name,Email,Registration Date,Total Matches,Total Messages,Total Photos\n";
        $csv .= sprintf(
            "%s,%s,%s,%d,%d,%d\n",
            $user->first_name . ' ' . $user->last_name,
            $user->email,
            $user->created_at->toDateString(),
            count($userData['matches']),
            count($userData['messages']),
            count($userData['photos'])
        );

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="soulsync_data_export.csv"');
    }

    /**
     * Generate comprehensive PDF export with advanced styling and data visualization
     */
    private function generatePDFExport($userData, $user)
    {
        try {
            // Create new PDF document with custom settings
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('SoulSync Matrimony');
            $pdf->SetAuthor('SoulSync System');
            $pdf->SetTitle('Personal Data Export - ' . $user->first_name . ' ' . $user->last_name);
            $pdf->SetSubject('Comprehensive Personal Data Export');
            $pdf->SetKeywords('matrimony, personal data, export, SoulSync');
            
            // Set default header and footer
            $pdf->SetHeaderData('', 0, 'SoulSync Matrimony', 'Personal Data Export', array(51, 51, 51), array(236, 72, 153));
            $pdf->setFooterData(array(51, 51, 51), array(236, 72, 153));
            
            // Set header and footer fonts
            $pdf->setHeaderFont(Array('helvetica', 'B', 14));
            $pdf->setFooterFont(Array('helvetica', '', 10));
            
            // Set default monospaced font
            $pdf->SetDefaultMonospacedFont('courier');
            
            // Set margins
            $pdf->SetMargins(15, 25, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            
            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Set image scale factor
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            
            // Set default font subsetting mode
            $pdf->setFontSubsetting(true);
            
            // Add a page
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('helvetica', '', 10);
            
            // Generate comprehensive HTML content
            $html = $this->generatePDFHTML($userData, $user);
            
            // Print text using writeHTMLCell()
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Add additional pages for detailed data if needed
            if (count($userData['matches']) > 10 || count($userData['messages']) > 10) {
                $this->addDetailedDataPages($pdf, $userData, $user);
            }
            
            // Close and output PDF document
            $pdfContent = $pdf->Output('soulsync_comprehensive_export.pdf', 'S');
            
            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="soulsync_comprehensive_export.pdf"');
                
        } catch (\Exception $e) {
            \Log::error('PDF export failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF export',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Generate comprehensive HTML content for PDF
     */
    private function generatePDFHTML($userData, $user): string
    {
        $html = '
        <style>
            .header { background-color: #ec4899; color: white; padding: 10px; text-align: center; font-size: 18px; font-weight: bold; }
            .section { margin: 15px 0; }
            .section-title { background-color: #f3f4f6; padding: 8px; font-size: 14px; font-weight: bold; color: #374151; }
            .info-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            .info-table th { background-color: #ec4899; color: white; padding: 8px; text-align: left; }
            .info-table td { padding: 8px; border: 1px solid #d1d5db; }
            .stats-grid { display: table; width: 100%; margin: 10px 0; }
            .stats-cell { display: table-cell; width: 25%; padding: 10px; text-align: center; background-color: #f9fafb; border: 1px solid #e5e7eb; }
            .stats-number { font-size: 24px; font-weight: bold; color: #ec4899; }
            .stats-label { font-size: 12px; color: #6b7280; }
            .match-table, .message-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 9px; }
            .match-table th, .message-table th { background-color: #f3f4f6; padding: 6px; text-align: left; font-weight: bold; }
            .match-table td, .message-table td { padding: 6px; border: 1px solid #d1d5db; }
            .photo-grid { display: table; width: 100%; margin: 10px 0; }
            .photo-cell { display: table-cell; width: 33.33%; padding: 5px; text-align: center; }
            .compatibility-high { color: #059669; font-weight: bold; }
            .compatibility-medium { color: #d97706; font-weight: bold; }
            .compatibility-low { color: #dc2626; font-weight: bold; }
        </style>
        ';
        
        // Header
        $html .= '<div class="header">Personal Data Export Report</div>';
        
        // User Information Section
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Personal Information</div>';
        $html .= '<table class="info-table">';
        $html .= '<tr><th colspan="2">Basic Information</th></tr>';
        $html .= '<tr><td><strong>Full Name:</strong></td><td>' . htmlspecialchars($user->first_name . ' ' . $user->last_name) . '</td></tr>';
        $html .= '<tr><td><strong>Email Address:</strong></td><td>' . htmlspecialchars($user->email) . '</td></tr>';
        $html .= '<tr><td><strong>Phone Number:</strong></td><td>' . htmlspecialchars($user->phone ?? 'Not provided') . '</td></tr>';
        $html .= '<tr><td><strong>Date of Birth:</strong></td><td>' . htmlspecialchars($user->date_of_birth) . '</td></tr>';
        $html .= '<tr><td><strong>Gender:</strong></td><td>' . htmlspecialchars(ucfirst($user->gender)) . '</td></tr>';
        $html .= '<tr><td><strong>Registration Date:</strong></td><td>' . $user->created_at->format('F j, Y \a\t g:i A') . '</td></tr>';
        $html .= '<tr><td><strong>Last Active:</strong></td><td>' . ($user->last_active_at ? $user->last_active_at->format('F j, Y \a\t g:i A') : 'Never') . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        
        // Profile Statistics Section
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Profile Statistics</div>';
        $html .= '<div class="stats-grid">';
        $html .= '<div class="stats-cell"><div class="stats-number">' . count($userData['matches']) . '</div><div class="stats-label">Total Matches</div></div>';
        $html .= '<div class="stats-cell"><div class="stats-number">' . count($userData['messages']) . '</div><div class="stats-label">Total Messages</div></div>';
        $html .= '<div class="stats-cell"><div class="stats-number">' . count($userData['photos']) . '</div><div class="stats-label">Profile Photos</div></div>';
        $html .= '<div class="stats-cell"><div class="stats-number">' . $this->calculateProfileCompletion($user) . '%</div><div class="stats-label">Profile Complete</div></div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Profile Details Section
        if ($user->profile) {
            $html .= '<div class="section">';
            $html .= '<div class="section-title">Profile Details</div>';
            $html .= '<table class="info-table">';
            $html .= '<tr><th colspan="2">Personal & Family Information</th></tr>';
            $html .= '<tr><td><strong>Religion:</strong></td><td>' . htmlspecialchars($user->profile->religion ?? 'Not specified') . '</td></tr>';
            $html .= '<tr><td><strong>Mother Tongue:</strong></td><td>' . htmlspecialchars($user->profile->mother_tongue ?? 'Not specified') . '</td></tr>';
            $html .= '<tr><td><strong>Education:</strong></td><td>' . htmlspecialchars($user->profile->education ?? 'Not specified') . '</td></tr>';
            $html .= '<tr><td><strong>Occupation:</strong></td><td>' . htmlspecialchars($user->profile->occupation ?? 'Not specified') . '</td></tr>';
            $html .= '<tr><td><strong>Annual Income:</strong></td><td>' . htmlspecialchars($user->profile->annual_income_usd ? '$' . number_format($user->profile->annual_income_usd) : 'Not specified') . '</td></tr>';
            $html .= '<tr><td><strong>Current City:</strong></td><td>' . htmlspecialchars($user->profile->current_city ?? 'Not specified') . '</td></tr>';
            $html .= '<tr><td><strong>Current State:</strong></td><td>' . htmlspecialchars($user->profile->current_state ?? 'Not specified') . '</td></tr>';
            $html .= '<tr><td><strong>Current Country:</strong></td><td>' . htmlspecialchars($user->profile->current_country ?? 'Not specified') . '</td></tr>';
            $html .= '</table>';
            $html .= '</div>';
        }
        
        // Recent Matches Section
        if (!empty($userData['matches'])) {
            $html .= '<div class="section">';
            $html .= '<div class="section-title">Recent Matches (Last 10)</div>';
            $html .= '<table class="match-table">';
            $html .= '<tr><th>Name</th><th>Age</th><th>Location</th><th>Match Date</th><th>Compatibility</th></tr>';
            
            foreach (array_slice($userData['matches'], 0, 10) as $match) {
                $compatibilityClass = $match['compatibility_score'] >= 80 ? 'compatibility-high' : 
                                    ($match['compatibility_score'] >= 60 ? 'compatibility-medium' : 'compatibility-low');
                
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($match['name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($match['age'] ?? 'N/A') . '</td>';
                $html .= '<td>' . htmlspecialchars($match['location'] ?? 'N/A') . '</td>';
                $html .= '<td>' . htmlspecialchars($match['created_at']) . '</td>';
                $html .= '<td class="' . $compatibilityClass . '">' . $match['compatibility_score'] . '%</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '</div>';
        }
        
        // Recent Messages Section
        if (!empty($userData['messages'])) {
            $html .= '<div class="section">';
            $html .= '<div class="section-title">Recent Messages (Last 10)</div>';
            $html .= '<table class="message-table">';
            $html .= '<tr><th>Conversation</th><th>Message Preview</th><th>Date</th><th>Type</th></tr>';
            
            foreach (array_slice($userData['messages'], 0, 10) as $message) {
                $messagePreview = strlen($message['content']) > 50 ? 
                    htmlspecialchars(substr($message['content'], 0, 50)) . '...' : 
                    htmlspecialchars($message['content']);
                
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($message['conversation_id']) . '</td>';
                $html .= '<td>' . $messagePreview . '</td>';
                $html .= '<td>' . htmlspecialchars($message['created_at']) . '</td>';
                $html .= '<td>' . htmlspecialchars(ucfirst($message['type'] ?? 'text')) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '</div>';
        }
        
        // Profile Photos Section
        if (!empty($userData['photos'])) {
            $html .= '<div class="section">';
            $html .= '<div class="section-title">Profile Photos</div>';
            $html .= '<div class="photo-grid">';
            
            foreach (array_slice($userData['photos'], 0, 6) as $photo) {
                $html .= '<div class="photo-cell">';
                $html .= '<strong>' . htmlspecialchars($photo['caption'] ?? 'Photo') . '</strong><br>';
                $html .= '<small>Uploaded: ' . htmlspecialchars($photo['created_at']) . '</small><br>';
                $html .= '<small>Status: ' . htmlspecialchars(ucfirst($photo['status'] ?? 'pending')) . '</small>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Footer
        $html .= '<div style="margin-top: 30px; padding: 10px; background-color: #f3f4f6; text-align: center; font-size: 10px; color: #6b7280;">';
        $html .= 'Generated on ' . now()->format('F j, Y \a\t g:i A') . ' | SoulSync Matrimony Platform';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Add detailed data pages for large datasets
     */
    private function addDetailedDataPages($pdf, $userData, $user): void
    {
        // Add detailed matches page if there are more than 10 matches
        if (count($userData['matches']) > 10) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Complete Match History', 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 10);
            
            $html = '<table border="1" cellpadding="5" style="font-size: 9px;">';
            $html .= '<tr style="background-color: #ec4899; color: white;"><th>Name</th><th>Age</th><th>Location</th><th>Match Date</th><th>Compatibility</th><th>Status</th></tr>';
            
            foreach ($userData['matches'] as $match) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($match['name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($match['age'] ?? 'N/A') . '</td>';
                $html .= '<td>' . htmlspecialchars($match['location'] ?? 'N/A') . '</td>';
                $html .= '<td>' . htmlspecialchars($match['created_at']) . '</td>';
                $html .= '<td>' . $match['compatibility_score'] . '%</td>';
                $html .= '<td>' . htmlspecialchars(ucfirst($match['status'] ?? 'active')) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            
            $pdf->writeHTML($html, true, false, true, false, '');
        }
        
        // Add detailed messages page if there are more than 10 messages
        if (count($userData['messages']) > 10) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Complete Message History', 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 10);
            
            $html = '<table border="1" cellpadding="5" style="font-size: 9px;">';
            $html .= '<tr style="background-color: #ec4899; color: white;"><th>Conversation</th><th>Message</th><th>Date</th><th>Type</th><th>Status</th></tr>';
            
            foreach ($userData['messages'] as $message) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($message['conversation_id']) . '</td>';
                $html .= '<td>' . htmlspecialchars(substr($message['content'], 0, 100)) . '</td>';
                $html .= '<td>' . htmlspecialchars($message['created_at']) . '</td>';
                $html .= '<td>' . htmlspecialchars(ucfirst($message['type'] ?? 'text')) . '</td>';
                $html .= '<td>' . htmlspecialchars(ucfirst($message['status'] ?? 'sent')) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            
            $pdf->writeHTML($html, true, false, true, false, '');
        }
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion($user): int
    {
        $requiredFields = [
            'first_name', 'last_name', 'date_of_birth', 'gender'
        ];

        $profileFields = [
            'bio', 'occupation', 'education', 'height', 'religion', 'caste',
            'city', 'state', 'country'
        ];

        $completed = 0;
        $total = count($requiredFields) + count($profileFields) + 2; // +2 for photo and preferences

        // Check required user fields
        foreach ($requiredFields as $field) {
            if (!empty($user->$field)) {
                $completed++;
            }
        }

        // Check profile fields
        if ($user->profile) {
            foreach ($profileFields as $field) {
                if (!empty($user->profile->$field)) {
                    $completed++;
                }
            }
        }

        // Check for profile photo
        if ($user->photos()->where('is_profile_picture', true)->exists()) {
            $completed++;
        }

        // Check for preferences
        if ($user->preferences) {
            $completed++;
        }

        return (int) (($completed / $total) * 100);
    }
}

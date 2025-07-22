<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole(['admin', 'moderator', 'super-admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return match ($action) {
            'updateStatus' => [
                'status' => ['required', 'string', 'in:active,inactive,suspended,banned'],
                'reason' => ['sometimes', 'string', 'max:500'],
                'admin_notes' => ['sometimes', 'string', 'max:1000'],
            ],
            'updateProfileStatus' => [
                'profile_status' => ['required', 'string', 'in:pending_approval,approved,rejected,incomplete'],
                'reason' => ['sometimes', 'string', 'max:500'],
                'admin_notes' => ['sometimes', 'string', 'max:1000'],
            ],
            'suspend' => [
                'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
                'reason' => ['required', 'string', 'max:500'],
                'admin_notes' => ['sometimes', 'string', 'max:1000'],
                'notify_user' => ['sometimes', 'boolean'],
            ],
            'ban' => [
                'reason' => ['required', 'string', 'max:500'],
                'admin_notes' => ['sometimes', 'string', 'max:1000'],
                'is_permanent' => ['sometimes', 'boolean'],
                'notify_user' => ['sometimes', 'boolean'],
            ],
            'unban' => [
                'reason' => ['required', 'string', 'max:500'],
                'admin_notes' => ['sometimes', 'string', 'max:1000'],
                'notify_user' => ['sometimes', 'boolean'],
            ],
            'destroy' => [
                'reason' => ['required', 'string', 'max:500'],
                'confirmation' => ['required', 'string', 'in:DELETE_USER'],
                'admin_notes' => ['sometimes', 'string', 'max:1000'],
                'backup_data' => ['sometimes', 'boolean'],
            ],
            default => [],
        };
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'User status is required.',
            'status.in' => 'Please select a valid user status.',
            'profile_status.required' => 'Profile status is required.',
            'profile_status.in' => 'Please select a valid profile status.',
            'duration_days.required' => 'Suspension duration is required.',
            'duration_days.min' => 'Suspension duration must be at least 1 day.',
            'duration_days.max' => 'Suspension duration cannot exceed 365 days.',
            'reason.required' => 'Reason for action is required.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
            'admin_notes.max' => 'Admin notes cannot exceed 1000 characters.',
            'confirmation.required' => 'Confirmation is required for user deletion.',
            'confirmation.in' => 'Please type "DELETE_USER" to confirm deletion.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'duration_days' => 'suspension duration',
            'admin_notes' => 'admin notes',
            'is_permanent' => 'permanent ban status',
            'notify_user' => 'notify user setting',
            'backup_data' => 'backup data setting',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set defaults
        $this->merge([
            'notify_user' => $this->notify_user ?? true,
            'is_permanent' => $this->is_permanent ?? true,
            'backup_data' => $this->backup_data ?? true,
        ]);

        // Clean text fields
        if ($this->filled('reason')) {
            $this->merge(['reason' => trim($this->reason)]);
        }

        if ($this->filled('admin_notes')) {
            $this->merge(['admin_notes' => trim($this->admin_notes)]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $action = $this->route()->getActionMethod();
            $targetUser = $this->route('user');

            // Prevent self-actions
            if ($targetUser && $targetUser->id === auth()->id()) {
                $validator->errors()->add('user', 'You cannot perform this action on your own account.');
                return;
            }

            // Validate admin hierarchy
            $this->validateAdminHierarchy($validator, $targetUser);

            // Validate action-specific business rules
            match ($action) {
                'suspend' => $this->validateSuspensionRules($validator, $targetUser),
                'ban' => $this->validateBanRules($validator, $targetUser),
                'unban' => $this->validateUnbanRules($validator, $targetUser),
                'destroy' => $this->validateDeletionRules($validator, $targetUser),
                default => null,
            };
        });
    }

    /**
     * Validate admin hierarchy (lower role cannot action higher role)
     */
    private function validateAdminHierarchy($validator, $targetUser): void
    {
        if (!$targetUser) return;

        $currentUser = auth()->user();

        // Define role hierarchy (higher number = more powerful)
        $roleHierarchy = [
            'user' => 1,
            'premium-user' => 2,
            'moderator' => 3,
            'admin' => 4,
            'super-admin' => 5,
        ];

        $currentUserRole = $currentUser->getRoleNames()->first();
        $targetUserRole = $targetUser->getRoleNames()->first();

        $currentLevel = $roleHierarchy[$currentUserRole] ?? 0;
        $targetLevel = $roleHierarchy[$targetUserRole] ?? 0;

        if ($currentLevel <= $targetLevel && $currentUser->id !== $targetUser->id) {
            $validator->errors()->add(
                'user',
                'You do not have sufficient permissions to perform this action on this user.'
            );
        }
    }

    /**
     * Validate suspension-specific rules
     */
    private function validateSuspensionRules($validator, $targetUser): void
    {
        if (!$targetUser) return;

        // Check if user is already suspended
        if ($targetUser->status === 'suspended') {
            $validator->errors()->add('duration_days', 'User is already suspended.');
        }

        // Check if user is banned (cannot suspend banned user)
        if ($targetUser->status === 'banned') {
            $validator->errors()->add('duration_days', 'Cannot suspend a banned user. Please unban first.');
        }

        // Validate reasonable suspension duration
        $duration = $this->input('duration_days');
        if ($duration > 90 && !auth()->user()->hasRole('super-admin')) {
            $validator->errors()->add(
                'duration_days',
                'Only super admins can suspend users for more than 90 days.'
            );
        }
    }

    /**
     * Validate ban-specific rules
     */
    private function validateBanRules($validator, $targetUser): void
    {
        if (!$targetUser) return;

        // Check if user is already banned
        if ($targetUser->status === 'banned') {
            $validator->errors()->add('reason', 'User is already banned.');
        }

        // Check for permanent ban permissions
        if ($this->input('is_permanent') && !auth()->user()->hasRole(['admin', 'super-admin'])) {
            $validator->errors()->add(
                'is_permanent',
                'Only admins can issue permanent bans.'
            );
        }

        // Check recent ban activity to prevent abuse
        $recentBans = \App\Models\User::where('banned_by', auth()->id())
            ->where('banned_at', '>=', now()->subDay())
            ->count();

        if ($recentBans >= 5 && !auth()->user()->hasRole('super-admin')) {
            $validator->errors()->add(
                'reason',
                'You have reached the daily ban limit. Please contact a super admin for assistance.'
            );
        }
    }

    /**
     * Validate unban-specific rules
     */
    private function validateUnbanRules($validator, $targetUser): void
    {
        if (!$targetUser) return;

        // Check if user is actually banned
        if ($targetUser->status !== 'banned') {
            $validator->errors()->add('reason', 'User is not currently banned.');
        }

        // Check if current user can unban (must be same level or higher than banner)
        if ($targetUser->banned_by) {
            $banner = \App\Models\User::find($targetUser->banned_by);
            if ($banner) {
                $this->validateAdminHierarchy($validator, $banner);
            }
        }
    }

    /**
     * Validate deletion-specific rules
     */
    private function validateDeletionRules($validator, $targetUser): void
    {
        if (!$targetUser) return;

        // Only super admins can delete users
        if (!auth()->user()->hasRole('super-admin')) {
            $validator->errors()->add(
                'confirmation',
                'Only super administrators can delete user accounts.'
            );
        }

        // Check if user has active subscriptions
        $activeSubscriptions = $targetUser->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->count();

        if ($activeSubscriptions > 0) {
            $validator->errors()->add(
                'reason',
                'Cannot delete user with active subscriptions. Please cancel subscriptions first.'
            );
        }

        // Warn about data loss
        if (!$this->input('backup_data')) {
            $validator->errors()->add(
                'backup_data',
                'User data backup is recommended before deletion.'
            );
        }
    }

    /**
     * Get action summary for logging
     */
    public function getActionSummary(): array
    {
        $action = $this->route()->getActionMethod();
        
        return [
            'action' => $action,
            'reason' => $this->input('reason'),
            'admin_notes' => $this->input('admin_notes'),
            'performed_by' => auth()->id(),
            'performed_at' => now(),
            'ip_address' => request()->ip(),
        ];
    }
} 
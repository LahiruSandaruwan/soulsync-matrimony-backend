<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log an audit event.
     */
    public static function log(
        string $action,
        string $description,
        string $category = 'system',
        string $severity = 'info',
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): AuditLog {
        $request = request();
        $user = Auth::user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_type' => self::getUserType($user),
            'action' => $action,
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity?->id,
            'description' => $description,
            'old_values' => $oldValues ? self::sanitizeValues($oldValues) : null,
            'new_values' => $newValues ? self::sanitizeValues($newValues) : null,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_method' => $request?->method(),
            'request_url' => $request?->fullUrl(),
            'severity' => $severity,
            'category' => $category,
        ]);
    }

    /**
     * Log authentication events.
     */
    public static function logAuth(string $action, ?User $user = null, ?array $metadata = null): AuditLog
    {
        $descriptions = [
            'login' => 'User logged in',
            'logout' => 'User logged out',
            'login_failed' => 'Failed login attempt',
            'password_changed' => 'Password was changed',
            'password_reset' => 'Password was reset',
            '2fa_enabled' => 'Two-factor authentication enabled',
            '2fa_disabled' => 'Two-factor authentication disabled',
            'account_locked' => 'Account was locked due to failed attempts',
        ];

        $severity = in_array($action, ['login_failed', 'account_locked']) ? 'warning' : 'info';

        return self::log(
            $action,
            $descriptions[$action] ?? "Auth action: {$action}",
            'auth',
            $severity,
            $user,
            null,
            null,
            $metadata
        );
    }

    /**
     * Log profile changes.
     */
    public static function logProfileChange(User $user, array $oldValues, array $newValues): AuditLog
    {
        return self::log(
            'profile_updated',
            'User profile was updated',
            'profile',
            'info',
            $user,
            $oldValues,
            $newValues
        );
    }

    /**
     * Log subscription events.
     */
    public static function logSubscription(
        string $action,
        User $user,
        ?array $metadata = null
    ): AuditLog {
        $descriptions = [
            'subscribed' => 'User subscribed to a plan',
            'upgraded' => 'User upgraded their subscription',
            'downgraded' => 'User downgraded their subscription',
            'cancelled' => 'User cancelled their subscription',
            'renewed' => 'Subscription was renewed',
            'expired' => 'Subscription expired',
            'payment_failed' => 'Payment failed for subscription',
        ];

        $severity = in_array($action, ['payment_failed', 'expired']) ? 'warning' : 'info';

        return self::log(
            $action,
            $descriptions[$action] ?? "Subscription action: {$action}",
            'subscription',
            $severity,
            $user,
            null,
            null,
            $metadata
        );
    }

    /**
     * Log admin actions.
     */
    public static function logAdminAction(
        string $action,
        string $description,
        ?Model $entity = null,
        ?array $metadata = null
    ): AuditLog {
        return self::log(
            $action,
            $description,
            'admin',
            'info',
            $entity,
            null,
            null,
            $metadata
        );
    }

    /**
     * Log moderation actions.
     */
    public static function logModeration(
        string $action,
        User $targetUser,
        string $reason,
        ?array $metadata = null
    ): AuditLog {
        $descriptions = [
            'warned' => "User was warned: {$reason}",
            'suspended' => "User was suspended: {$reason}",
            'banned' => "User was banned: {$reason}",
            'unbanned' => 'User ban was lifted',
            'profile_rejected' => "Profile was rejected: {$reason}",
            'profile_approved' => 'Profile was approved',
            'photo_removed' => "Photo was removed: {$reason}",
        ];

        $severity = in_array($action, ['banned', 'suspended']) ? 'critical' : 'warning';

        return self::log(
            $action,
            $descriptions[$action] ?? "Moderation action: {$action}",
            'moderation',
            $severity,
            $targetUser,
            null,
            null,
            array_merge($metadata ?? [], ['reason' => $reason])
        );
    }

    /**
     * Log security events.
     */
    public static function logSecurity(
        string $action,
        string $description,
        ?User $user = null,
        ?array $metadata = null
    ): AuditLog {
        $severity = in_array($action, [
            'suspicious_activity',
            'brute_force_detected',
            'unauthorized_access',
        ]) ? 'critical' : 'warning';

        return self::log(
            $action,
            $description,
            'security',
            $severity,
            $user,
            null,
            null,
            $metadata
        );
    }

    /**
     * Log data-related events (GDPR).
     */
    public static function logDataEvent(
        string $action,
        User $user,
        ?array $metadata = null
    ): AuditLog {
        $descriptions = [
            'data_exported' => 'User data was exported',
            'data_deleted' => 'User data was deleted',
            'account_deleted' => 'Account was deleted',
            'consent_updated' => 'Privacy consent was updated',
        ];

        return self::log(
            $action,
            $descriptions[$action] ?? "Data action: {$action}",
            'data',
            'critical',
            $user,
            null,
            null,
            $metadata
        );
    }

    /**
     * Determine the user type.
     */
    private static function getUserType(?User $user): string
    {
        if (!$user) {
            return 'guest';
        }

        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('super-admin')) {
                return 'super-admin';
            }
            if ($user->hasRole('admin')) {
                return 'admin';
            }
            if ($user->hasRole('moderator')) {
                return 'moderator';
            }
        }

        return 'user';
    }

    /**
     * Sanitize values to remove sensitive data.
     */
    private static function sanitizeValues(array $values): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'api_key',
            'secret',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($values[$field])) {
                $values[$field] = '[REDACTED]';
            }
        }

        return $values;
    }
}

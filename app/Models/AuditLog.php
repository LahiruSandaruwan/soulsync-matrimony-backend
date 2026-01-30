<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_email',
        'user_type',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'request_method',
        'request_url',
        'severity',
        'category',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for filtering by severity.
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for filtering by entity.
     */
    public function scopeForEntity($query, string $type, $id = null)
    {
        $query->where('entity_type', $type);

        if ($id) {
            $query->where('entity_id', $id);
        }

        return $query;
    }

    /**
     * Scope for filtering by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for filtering by date range.
     */
    public function scopeDateRange($query, $from, $to = null)
    {
        $query->where('created_at', '>=', $from);

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope for critical events.
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Get the changed fields.
     */
    public function getChangedFieldsAttribute(): array
    {
        if (!$this->old_values || !$this->new_values) {
            return [];
        }

        $changed = [];
        foreach ($this->new_values as $key => $value) {
            if (!isset($this->old_values[$key]) || $this->old_values[$key] !== $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_type',
        'status',
        'starts_at',
        'expires_at',
        'amount_usd',
        'amount_local',
        'local_currency',
        'payment_method',
        'auto_renewal',
        'is_trial',
        'downgrade_to',
        'downgrade_at',
        'pending_plan_change',
        'cancelled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'downgrade_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renewal' => 'boolean',
        'is_trial' => 'boolean',
        'amount_usd' => 'decimal:2',
        'amount_local' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && 
               $this->expires_at && 
               $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}

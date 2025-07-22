<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarningTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'severity',
        'title',
        'default_message',
        'default_restrictions',
        'escalation_after_count',
        'escalation_action',
        'is_active',
    ];

    protected $casts = [
        'default_restrictions' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get template by category and severity
     */
    public static function getTemplate(string $category, string $severity): ?self
    {
        return static::where('category', $category)
            ->where('severity', $severity)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active templates grouped by category
     */
    public static function getGroupedTemplates(): array
    {
        return static::where('is_active', true)
            ->get()
            ->groupBy('category')
            ->toArray();
    }

    /**
     * Create default warning templates
     */
    public static function createDefaults(): void
    {
        $templates = [
            // Inappropriate Content
            [
                'category' => 'inappropriate_content',
                'severity' => 'minor',
                'title' => 'Inappropriate Content - Minor',
                'default_message' => 'Your profile contains content that may not be appropriate for our community. Please review our community guidelines.',
                'default_restrictions' => [],
                'escalation_after_count' => 2,
                'escalation_action' => 'suspend',
            ],
            [
                'category' => 'inappropriate_content',
                'severity' => 'moderate',
                'title' => 'Inappropriate Content - Moderate',
                'default_message' => 'Your profile contains inappropriate content. This is a formal warning.',
                'default_restrictions' => ['profile_hidden'],
                'escalation_after_count' => 2,
                'escalation_action' => 'suspend',
            ],
            [
                'category' => 'inappropriate_content',
                'severity' => 'major',
                'title' => 'Inappropriate Content - Major',
                'default_message' => 'Your profile contains seriously inappropriate content that violates our terms.',
                'default_restrictions' => ['profile_hidden', 'photo_upload_disabled'],
                'escalation_after_count' => 1,
                'escalation_action' => 'ban',
            ],

            // Fake Profile
            [
                'category' => 'fake_profile',
                'severity' => 'moderate',
                'title' => 'Potential Fake Profile',
                'default_message' => 'Your profile appears to contain false information. Please verify your identity.',
                'default_restrictions' => ['matching_disabled'],
                'escalation_after_count' => 1,
                'escalation_action' => 'suspend',
            ],
            [
                'category' => 'fake_profile',
                'severity' => 'major',
                'title' => 'Fake Profile Confirmed',
                'default_message' => 'Your profile has been confirmed as fake. This is a serious violation.',
                'default_restrictions' => ['profile_hidden', 'matching_disabled', 'messaging_disabled'],
                'escalation_after_count' => 1,
                'escalation_action' => 'ban',
            ],

            // Harassment
            [
                'category' => 'harassment',
                'severity' => 'minor',
                'title' => 'Inappropriate Messaging',
                'default_message' => 'You have been reported for inappropriate messaging. Please be respectful.',
                'default_restrictions' => [],
                'escalation_after_count' => 2,
                'escalation_action' => 'suspend',
            ],
            [
                'category' => 'harassment',
                'severity' => 'moderate',
                'title' => 'Harassment Warning',
                'default_message' => 'You have been reported for harassment. This behavior is not tolerated.',
                'default_restrictions' => ['messaging_disabled'],
                'escalation_after_count' => 2,
                'escalation_action' => 'suspend',
            ],
            [
                'category' => 'harassment',
                'severity' => 'severe',
                'title' => 'Serious Harassment',
                'default_message' => 'You have been reported for serious harassment. This is your final warning.',
                'default_restrictions' => ['messaging_disabled', 'matching_disabled'],
                'escalation_after_count' => 1,
                'escalation_action' => 'ban',
            ],

            // Spam
            [
                'category' => 'spam',
                'severity' => 'minor',
                'title' => 'Spam Content',
                'default_message' => 'Your messages appear to be spam. Please avoid promotional content.',
                'default_restrictions' => [],
                'escalation_after_count' => 3,
                'escalation_action' => 'suspend',
            ],
            [
                'category' => 'spam',
                'severity' => 'moderate',
                'title' => 'Repeated Spam',
                'default_message' => 'You have been repeatedly sending spam content.',
                'default_restrictions' => ['messaging_disabled'],
                'escalation_after_count' => 2,
                'escalation_action' => 'ban',
            ],
        ];

        foreach ($templates as $template) {
            static::create($template);
        }
    }
} 
<?php

namespace App\Traits;

use App\Services\AuditService;

/**
 * Trait for automatically logging model changes.
 *
 * Add this trait to any model that should have its changes audited.
 */
trait Auditable
{
    /**
     * Boot the trait.
     */
    public static function bootAuditable(): void
    {
        // Log when model is created
        static::created(function ($model) {
            if ($model->shouldAudit('created')) {
                AuditService::log(
                    'created',
                    $model->getAuditDescription('created'),
                    $model->getAuditCategory(),
                    'info',
                    $model,
                    null,
                    $model->getAuditableAttributes()
                );
            }
        });

        // Log when model is updated
        static::updated(function ($model) {
            if ($model->shouldAudit('updated') && $model->wasChanged()) {
                $oldValues = [];
                $newValues = [];

                foreach ($model->getChanges() as $key => $value) {
                    if (!in_array($key, $model->getExcludedAuditFields())) {
                        $oldValues[$key] = $model->getOriginal($key);
                        $newValues[$key] = $value;
                    }
                }

                if (!empty($newValues)) {
                    AuditService::log(
                        'updated',
                        $model->getAuditDescription('updated'),
                        $model->getAuditCategory(),
                        'info',
                        $model,
                        $oldValues,
                        $newValues
                    );
                }
            }
        });

        // Log when model is deleted
        static::deleted(function ($model) {
            if ($model->shouldAudit('deleted')) {
                AuditService::log(
                    'deleted',
                    $model->getAuditDescription('deleted'),
                    $model->getAuditCategory(),
                    'warning',
                    $model,
                    $model->getAuditableAttributes(),
                    null
                );
            }
        });
    }

    /**
     * Determine if the action should be audited.
     */
    public function shouldAudit(string $action): bool
    {
        // Override in model to customize
        $auditEvents = $this->auditEvents ?? ['created', 'updated', 'deleted'];
        return in_array($action, $auditEvents);
    }

    /**
     * Get the audit category for this model.
     */
    public function getAuditCategory(): string
    {
        // Override in model to customize
        return $this->auditCategory ?? 'system';
    }

    /**
     * Get the audit description.
     */
    public function getAuditDescription(string $action): string
    {
        $modelName = class_basename($this);
        $descriptions = [
            'created' => "{$modelName} was created",
            'updated' => "{$modelName} was updated",
            'deleted' => "{$modelName} was deleted",
        ];

        return $this->auditDescriptions[$action] ?? $descriptions[$action] ?? "{$modelName} {$action}";
    }

    /**
     * Get the attributes to include in audit log.
     */
    public function getAuditableAttributes(): array
    {
        $attributes = $this->attributesToArray();

        // Remove excluded fields
        foreach ($this->getExcludedAuditFields() as $field) {
            unset($attributes[$field]);
        }

        return $attributes;
    }

    /**
     * Get fields to exclude from audit logging.
     */
    public function getExcludedAuditFields(): array
    {
        return array_merge(
            ['password', 'remember_token', 'updated_at', 'created_at'],
            $this->excludeFromAudit ?? []
        );
    }
}

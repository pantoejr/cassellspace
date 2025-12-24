<?php

namespace App\Traits;

use App\Models\AuditLog;
use App\Models\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Attach to any Eloquent model to automatically write audit_logs on create/update/delete.
 *
 * Usage: use \App\Traits\Auditable; in the model class.
 */
trait Auditable
{
    /**
     * Default attributes to ignore in audit payloads.
     * Models can override with a public array $auditIgnore.
     *
     * @var array<int, string>
     */
    protected array $auditIgnored = [
        'password',
        'remember_token',
        'updated_at',
        'created_at',
    ];

    /**
     * Boot the trait and register model event hooks.
     */
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('created');
        });

        static::updated(function ($model) {
            $model->writeAuditLog('updated');
        });

        static::deleted(function ($model) {
            $model->writeAuditLog('deleted');
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->writeAuditLog('restored');
            });
        }
    }

    /**
     * Persist an audit log entry for the given action.
     */
    protected function writeAuditLog(string $action): void
    {
        try {
            $before = null;
            $after = null;

            if ($action === 'created' || $action === 'restored') {
                $after = $this->filterAuditableAttributes($this->getAttributes());
            } elseif ($action === 'updated') {
                $dirty = $this->getDirty();
                if (empty($dirty)) {
                    return; // nothing effectively changed
                }
                $before = $this->filterAuditableAttributes(array_intersect_key($this->getOriginal(), $dirty));
                $after = $this->filterAuditableAttributes(array_intersect_key($this->getAttributes(), $dirty));
            } elseif ($action === 'deleted') {
                $before = $this->filterAuditableAttributes($this->getAttributes());
            }

            $payload = [
                'before' => $before,
                'after' => $after,
            ];

            AuditTrail::create([
                'entity_name' => class_basename($this),
                'entity_id' => $this->getKey(),
                'action' => $action,
                'user_id' => Auth::id(),
                'changes' => $payload,
                'ip_address' => Request::ip(),
            ]);
        } catch (\Throwable $e) {
            // Do not disrupt the main flow if auditing fails
            logger()->warning('Audit logging failed', [
                'model' => static::class,
                'id' => $this->getKey(),
                'action' => $action,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove ignored attributes from the audit payload.
     */
    protected function filterAuditableAttributes(array $attributes): array
    {
        $ignore = property_exists($this, 'auditIgnore') ? $this->auditIgnore : $this->auditIgnored;
        foreach ($ignore as $key) {
            if (array_key_exists($key, $attributes)) {
                unset($attributes[$key]);
            }
        }
        return $attributes;
    }
}

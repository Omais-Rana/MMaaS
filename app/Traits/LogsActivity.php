<?php

namespace App\Traits;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            self::logActivity('created', $model);
        });

        static::updated(function ($model) {
            self::logActivity('updated', $model);
        });

        static::deleted(function ($model) {
            self::logActivity('deleted', $model);
        });
    }

    protected static function logActivity(string $event, $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        // For User model, only log employee activities
        if ($model instanceof User && $model->role !== 'employee') {
            return;
        }

        $tenantId = $user->role === 'subadmin' ? $user->id : $user->tenant_id;

        $type = strtolower(class_basename($model)).'_'.$event;
        $description = self::getActivityDescription($event, $model);

        Activity::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'type' => $type,
            'description' => $description,
            'properties' => [
                'model_type' => get_class($model),
                'model_id' => $model->id,
            ],
        ]);
    }

    protected static function getActivityDescription(string $event, $model): string
    {
        $modelName = class_basename($model);
        $user = Auth::user();

        return match ($event) {
            'created' => match ($modelName) {
                'User' => "{$user->name} created a new employee: {$model->name}",
                'ScheduleImport' => "{$user->name} imported a new schedule: {$model->filename}",
                'Role' => "{$user->name} created a new role: {$model->name}",
                default => "{$user->name} created a new {$modelName}",
            },
            'updated' => match ($modelName) {
                'User' => "{$user->name} updated employee: {$model->name}",
                'ScheduleImport' => "{$user->name} updated schedule: {$model->filename}",
                'Role' => "{$user->name} updated role: {$model->name}",
                default => "{$user->name} updated {$modelName}",
            },
            'deleted' => match ($modelName) {
                'User' => "{$user->name} deleted employee: {$model->name}",
                'ScheduleImport' => "{$user->name} deleted schedule: {$model->filename}",
                'Role' => "{$user->name} deleted role: {$model->name}",
                default => "{$user->name} deleted {$modelName}",
            },
            default => "{$user->name} performed {$event} on {$modelName}",
        };
    }

    public static function logCustomActivity(string $type, string $description, ?array $properties = null): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $tenantId = $user->role === 'subadmin' ? $user->id : $user->tenant_id;

        Activity::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'type' => $type,
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}

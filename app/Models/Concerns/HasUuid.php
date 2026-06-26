<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a UUID string primary key (matches the original Supabase schema).
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function initializeHasUuid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}

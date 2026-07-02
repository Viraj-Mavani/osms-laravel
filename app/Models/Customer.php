<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A store contact. Every buyer is a customer; a customer becomes a "patient"
 * (a derived role, not a separate table) once they have at least one eye record.
 */
class Customer extends Model
{
    use HasUuid, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'age',
        'gender',
    ];

    protected $casts = [
        'age' => 'integer',
    ];

    public function eyeRecords(): HasMany
    {
        return $this->hasMany(EyeRecord::class)->latest();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    /** A customer is a "patient" once they have a prescription on file. */
    public function isPatient(): bool
    {
        return $this->eyeRecords()->exists();
    }

    /** Scope to customers who have at least one eye record (the "patients" view). */
    public function scopePatients(Builder $query): Builder
    {
        return $query->whereHas('eyeRecords');
    }
}

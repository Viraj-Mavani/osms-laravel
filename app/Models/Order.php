<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'patient_id', 'eye_record_id', 'status',
        'total_amount', 'advance_paid', 'balance_due',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'advance_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Keep balance_due in sync (replaces the Supabase computed column).
        static::saving(function (Order $order) {
            $order->balance_due = (float) $order->total_amount - (float) $order->advance_paid;
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function eyeRecord(): BelongsTo
    {
        return $this->belongsTo(EyeRecord::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'ready_for_pickup' => 'Ready for pickup',
            'delivered' => 'Delivered',
            default => ucfirst((string) $this->status),
        };
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'order_id', 'amount', 'method', 'note', 'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            'cash' => 'Cash',
            'card' => 'Card',
            'upi' => 'UPI',
            default => ucfirst((string) $this->method),
        };
    }
}

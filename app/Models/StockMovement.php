<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'inventory_id', 'delta', 'type', 'reason', 'order_id', 'recorded_by',
    ];

    protected $casts = [
        'delta' => 'integer',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'order' => 'Order',
            'cancel' => 'Order cancelled',
            'edit' => 'Order edited',
            'adjustment' => 'Manual adjustment',
            default => ucfirst((string) $this->type),
        };
    }
}

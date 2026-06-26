<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasUuid, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'razorpay_subscription_id', 'razorpay_customer_id',
        'status', 'tier', 'current_period_end',
    ];

    protected $casts = [
        'current_period_end' => 'date',
    ];

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true);
    }
}

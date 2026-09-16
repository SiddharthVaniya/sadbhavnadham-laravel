<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RazorpayPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'cause_id',
        'cause_package_id',
        'frequency',
        'amount',
        'custom_plan_key',
        'currency',
        'razorpay_plan_id',
        'plan_name',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(CausePackage::class, 'cause_package_id');
    }

    public function cause(): BelongsTo
    {
        return $this->belongsTo(Cause::class);
    }
}

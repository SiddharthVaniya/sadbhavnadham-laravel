<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentEvent extends Model
{
    use HasFactory;

    protected $table = 'payment_events';

    public $timestamps = false;

    protected $fillable = [
        'donation_order_id',
        'payment_provider',
        'event',
        'provider_payment_id',
        'amount',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /* ==========================
     |  Relationships
     ========================== */

    public function order()
    {
        return $this->belongsTo(DonationOrder::class, 'donation_order_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    //
   protected $fillable = [
        'payment_id',
        'razorpay_order_id',    
        'receipt_no',
        'donor_name',
        'amount',
        'email',
        'contact',
        'donate_for',
        'pan_number',
        'address',         
        'status',
         'payment_link_id',
        'payment_link_url',
        
    ];

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['rental_id', 'payment_code', 'amount', 'payment_method', 'payment_proof', 'status', 'paid_at'];

    public function rental() {
    return $this->belongsTo(Rental::class);
}
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    protected $fillable = ['user_id', 'rental_code', 'start_date', 'end_date', 'total_price', 'payment_status', 'rental_status', 'payment_proof'];

    public function user() {
    return $this->belongsTo(User::class);
}

    public function items() {
    return $this->hasMany(RentalItem::class);
}

    public function payments() {
    return $this->hasMany(Payment::class);
}
}

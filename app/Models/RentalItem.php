<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalItem extends Model
{
    protected $fillable = ['rental_id', 'equipment_id', 'qty', 'subtotal'];

    public function rental() {
    return $this->belongsTo(Rental::class);
}

    public function equipment() {
    return $this->belongsTo(Equipment::class);
}
}

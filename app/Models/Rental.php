<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'rental_code',
        'start_date',
        'end_date',
        'total_price',
        'payment_status',
        'rental_status',
        'payment_proof',
        'ktp_number',
        'pickup_notes',
        'return_notes',
        'pickup_date',
        'return_date',
        'late_fee',
        'is_damage',
        'is_lost',
        'damage_note',
        'lost_note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Nama relasi disesuaikan jadi rentalItems agar cocok dengan Controller
    public function rentalItems()
    {
        return $this->hasMany(RentalItem::class, 'rental_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipments';

    protected $fillable = [
        'category_id',
        'name',
        'price_per_day',
        'stock',
        'image',
        'description',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}

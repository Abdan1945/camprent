<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $fillable = ['category_id', 'name', 'price_per_day', 'stock', 'image', 'description'];

    public function category() {
    return $this->belongsTo(Category::class);
}
}

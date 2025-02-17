<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function photos()
    {
        return $this->hasMany(ItemPhoto::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}

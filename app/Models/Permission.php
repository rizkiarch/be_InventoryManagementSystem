<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as BasePemission;

class Permission extends BasePemission
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}

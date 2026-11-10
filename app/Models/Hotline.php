<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hotline extends Model
{
    protected $fillable = ['name', 'phone_number', 'description'];
}

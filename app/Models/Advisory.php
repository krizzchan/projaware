<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Advisory extends Model
{
    protected $fillable = ['title', 'severity', 'message', 'user_id'];

    protected function casts(): array
    {
        return ['message' => 'encrypted'];
    }
}

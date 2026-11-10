<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentReport extends Model
{
    protected $fillable = ['user_id', 'barangay_id', 'title', 'description', 'severity', 'status'];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}

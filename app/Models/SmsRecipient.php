<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsRecipient extends Model
{
    protected $fillable = ['broadcast_id', 'contact_id', 'phone_number', 'status', 'error', 'processed_at'];

    protected $hidden = ['phone_number'];

    protected function casts(): array
    {
        return ['phone_number' => 'encrypted', 'processed_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }
}

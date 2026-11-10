<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    protected $fillable = ['user_id', 'request_id', 'payload_hash', 'title', 'severity', 'message', 'target', 'recipient_count', 'is_test'];

    protected $hidden = ['payload_hash'];

    protected function casts(): array
    {
        return ['is_test' => 'boolean', 'message' => 'encrypted'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SmsRecipient::class);
    }
}

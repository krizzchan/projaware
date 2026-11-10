<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public static function record(string $action, string $subjectType, ?int $subjectId = null, array $metadata = []): void
    {
        self::create(['user_id' => auth()->id(), 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'metadata' => $metadata]);
    }
}

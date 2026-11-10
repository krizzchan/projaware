<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'middle_initial',
        'last_name',
        'office',
        'position',
        'phone_number',
        'category_id',
        'barangay_id',
        'is_active',
    ];

    /**
     * Relationship: A Contact belongs to one Category.
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relationship: A Contact belongs to one Barangay.
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}

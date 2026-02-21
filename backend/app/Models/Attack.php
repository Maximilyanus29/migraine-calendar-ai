<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attack extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_at',
        'end_at',
        'intensity',
        'medications',
        'relief',
        'pain_types',
        'localizations',
        'triggers',
        'symptoms',
        'auras',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'relief' => 'boolean',
            'pain_types' => 'array',
            'localizations' => 'array',
            'triggers' => 'array',
            'symptoms' => 'array',
            'auras' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_email_id',
        'company_name',
        'position',
        'status',
        'salary_range',
        'job_description',
        'applied_at',
        'ai_metadata',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'ai_metadata' => 'array', // Удобная работа с JSON столбцом
    ];

    public function sourceEmail(): BelongsTo
    {
        return $this->belongsTo(Email::class, 'source_email_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class);
    }
}

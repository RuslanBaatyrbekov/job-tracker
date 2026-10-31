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
        'job_category_id',
        'applied_at',
        'ai_metadata',
        'rejection_reason',
        'ai_advice',
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

    public static function getKanbanStatuses(): array
    {
        return [
            'applied' => 'Отправлено',
            'interview' => 'Интервью',
            'offer' => 'Оффер',
            'rejected' => 'Отказ',
        ];
    }

    public function jobCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'job_category_id');
    }
}

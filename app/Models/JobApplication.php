<?php

namespace App\Models;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Support\CompanyName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_email_id',
        'job_lead_id',
        'job_category_id',
        'company_name',
        'company_key',
        'company_domain',
        'position',
        'status',
        'source',
        'vacancy_url',
        'location',
        'work_mode',
        'language',
        'german_level',
        'cover_letter_sent',
        'salary_range',
        'job_description',
        'applied_at',
        'last_activity_at',
        'follow_up_reminded_at',
        'follow_up_sent_at',
        'ai_metadata',
    ];

    protected $casts = [
        'status' => ApplicationStatus::class,
        'source' => ApplicationSource::class,
        'cover_letter_sent' => 'boolean',
        'applied_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'follow_up_reminded_at' => 'datetime',
        'follow_up_sent_at' => 'datetime',
        'ai_metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $application) {
            if ($application->isDirty('company_name')) {
                $application->company_key = CompanyName::key($application->company_name);
            }
            $application->last_activity_at ??= $application->applied_at ?? now();
        });

        // Manual status changes in the admin panel are recorded too.
        static::saved(function (self $application) {
            if ($application->wasChanged('status') || $application->wasRecentlyCreated) {
                $application->statusChanges()->create([
                    'from_status' => $application->wasRecentlyCreated ? null : $application->getOriginal('status')?->value,
                    'to_status' => $application->status->value,
                    'email_id' => $application->pendingStatusEmailId,
                    'changed_at' => now(),
                ]);
                $application->pendingStatusEmailId = null;
            }
        });
    }

    /** Email that caused the next status change (set by ApplicationRecorder). */
    public ?int $pendingStatusEmailId = null;

    public function sourceEmail(): BelongsTo
    {
        return $this->belongsTo(Email::class, 'source_email_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(JobLead::class, 'job_lead_id');
    }

    public function jobCategory(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'job_category_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(ApplicationStatusChange::class)->orderBy('changed_at');
    }

    public function scopeForCategory(Builder $query, ?int $categoryId): Builder
    {
        return $query->when($categoryId, fn (Builder $q) => $q->where('job_category_id', $categoryId));
    }

    /** Applications still waiting for any reaction from the employer. */
    public function scopeAwaitingReply(Builder $query): Builder
    {
        return $query->where('status', ApplicationStatus::Applied);
    }
}

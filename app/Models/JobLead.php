<?php

namespace App\Models;

use App\Enums\ApplicationSource;
use App\Enums\LeadStatus;
use App\Support\CompanyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class JobLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_email_id',
        'job_category_id',
        'source',
        'title',
        'company_name',
        'company_key',
        'location',
        'url',
        'fingerprint',
        'description',
        'score',
        'score_reason',
        'red_flags',
        'work_mode',
        'german_level',
        'language',
        'scored_at',
        'tailoring',
        'status',
        'notified_at',
    ];

    protected $casts = [
        'source' => ApplicationSource::class,
        'status' => LeadStatus::class,
        'red_flags' => 'array',
        'score' => 'integer',
        'scored_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'new',
        'source' => 'other',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $lead) {
            $lead->company_key = CompanyName::key($lead->company_name);
            $lead->fingerprint ??= self::fingerprintFor($lead->url, $lead->title, $lead->company_name);
        });
    }

    /**
     * Same vacancy = same URL (without tracking params) or same title + company.
     */
    public static function fingerprintFor(?string $url, ?string $title, ?string $company): string
    {
        $url = self::cleanUrl($url);

        $basis = $url ?: Str::lower(trim((string) $title)).'|'.CompanyName::key($company);

        return hash('sha256', $basis);
    }

    /**
     * Strip tracking query params (utm_*, refId, trackingId, ...) so the same job from two alerts matches.
     */
    public static function cleanUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        $host = Str::lower($parts['host'] ?? '');
        $path = rtrim($parts['path'] ?? '', '/');

        parse_str($parts['query'] ?? '', $query);
        // Keep only params that identify the job itself.
        $query = array_intersect_key($query, array_flip(['jk', 'jobId', 'job_id', 'id', 'currentJobId']));
        ksort($query);

        return $host.$path.($query ? '?'.http_build_query($query) : '');
    }

    public function sourceEmail(): BelongsTo
    {
        return $this->belongsTo(Email::class, 'source_email_id');
    }

    public function jobCategory(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'job_category_id');
    }

    public function application(): HasOne
    {
        return $this->hasOne(JobApplication::class, 'job_lead_id');
    }
}

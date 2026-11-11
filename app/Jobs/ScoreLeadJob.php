<?php

namespace App\Jobs;

use App\Models\JobCategory;
use App\Models\JobLead;
use App\Services\Ai\CareerAssistant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScoreLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var int[] */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $leadId) {}

    public function handle(CareerAssistant $assistant): void
    {
        $lead = JobLead::find($this->leadId);
        if (! $lead) {
            return;
        }

        $result = $assistant->scoreLead($lead);

        $categoryId = ! empty($result['category_name'])
            ? JobCategory::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($result['category_name']))])->value('id')
            : null;

        $lead->update([
            'score' => max(0, min(100, (int) ($result['score'] ?? 0))),
            'score_reason' => $result['score_reason'] ?? $result['reason'] ?? null,
            'red_flags' => array_values(array_filter((array) ($result['red_flags'] ?? []))),
            'job_category_id' => $lead->job_category_id ?? $categoryId,
            'work_mode' => $this->nullable($result['work_mode'] ?? null),
            'german_level' => $this->nullable($result['german_level'] ?? null),
            'language' => $this->nullable($result['language'] ?? null),
            'scored_at' => now(),
        ]);
    }

    private function nullable(?string $value): ?string
    {
        return in_array($value, [null, '', 'null'], true) ? null : $value;
    }
}

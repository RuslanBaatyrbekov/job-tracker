<?php

namespace App\Services;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Funnel numbers: how many applications got any answer, reached an interview, an offer.
 * "Reached interview" uses the status history, so a later rejection does not hide it.
 */
class FunnelStats
{
    public function summary(?int $categoryId = null, ?CarbonInterface $since = null, ?string $source = null): array
    {
        $base = fn (): Builder => JobApplication::query()
            ->forCategory($categoryId)
            ->when($since, fn (Builder $q) => $q->where('applied_at', '>=', $since))
            ->when($source !== null, fn (Builder $q) => $source === 'unknown'
                ? $q->whereNull('source')
                : $q->where('source', $source));

        $total = $base()->count();
        $interviews = $base()->where(fn (Builder $q) => $q
            ->whereIn('status', [ApplicationStatus::Interview, ApplicationStatus::Offer])
            ->orWhereHas('statusChanges', fn (Builder $h) => $h->whereIn('to_status', ['interview', 'offer'])))
            ->count();
        $offers = $base()->where('status', ApplicationStatus::Offer)->count();
        $rejected = $base()->where('status', ApplicationStatus::Rejected)->count();
        $noResponse = $base()->where('status', ApplicationStatus::NoResponse)->count();
        $waiting = $base()->where('status', ApplicationStatus::Applied)->count();
        // Any human reaction: interview, offer or rejection.
        $responded = $base()->where(fn (Builder $q) => $q
            ->whereIn('status', [ApplicationStatus::Interview, ApplicationStatus::Offer, ApplicationStatus::Rejected])
            ->orWhereHas('statusChanges', fn (Builder $h) => $h->whereIn('to_status', ['interview', 'offer', 'rejected'])))
            ->count();

        return [
            'applications' => $total,
            'waiting' => $waiting,
            'responded' => $responded,
            'interviews' => $interviews,
            'offers' => $offers,
            'rejected' => $rejected,
            'no_response' => $noResponse,
            'response_rate' => $this->percent($responded, $total),
            'interview_rate' => $this->percent($interviews, $total),
        ];
    }

    /**
     * Same numbers per channel (LinkedIn, StepStone, referral, ...).
     *
     * @return array<string, array>
     */
    public function bySource(?int $categoryId = null, ?CarbonInterface $since = null): array
    {
        $sources = JobApplication::query()
            ->forCategory($categoryId)
            ->when($since, fn (Builder $q) => $q->where('applied_at', '>=', $since))
            ->select('source')
            ->distinct()
            ->pluck('source')
            ->map(fn ($s) => $s instanceof ApplicationSource ? $s->value : 'unknown')
            ->unique();

        $result = [];
        foreach ($sources as $source) {
            $label = $source === 'unknown' ? 'Не указан' : ApplicationSource::from($source)->getLabel();
            $result[$label] = $this->summary($categoryId, $since, $source);
        }

        uasort($result, fn ($a, $b) => $b['applications'] <=> $a['applications']);

        return $result;
    }

    public function appliedThisWeek(?int $categoryId = null): int
    {
        return JobApplication::query()
            ->forCategory($categoryId)
            ->where('applied_at', '>=', now()->startOfWeek())
            ->count();
    }

    private function percent(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }
}

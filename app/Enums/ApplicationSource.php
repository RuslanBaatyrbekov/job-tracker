<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ApplicationSource: string implements HasLabel
{
    case LinkedIn = 'linkedin';
    case StepStone = 'stepstone';
    case Indeed = 'indeed';
    case Xing = 'xing';
    case Arbeitsagentur = 'arbeitsagentur';
    case CompanySite = 'company_site';
    case Referral = 'referral';
    case Recruiter = 'recruiter';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
            self::StepStone => 'StepStone',
            self::Indeed => 'Indeed',
            self::Xing => 'XING',
            self::Arbeitsagentur => 'Arbeitsagentur',
            self::CompanySite => 'Сайт компании',
            self::Referral => 'Рекомендация',
            self::Recruiter => 'Рекрутер написал сам',
            self::Other => 'Другое',
        };
    }

    /**
     * Best-effort guess from the sender address of a job alert email.
     */
    public static function guessFromEmail(?string $address): self
    {
        $address = strtolower((string) $address);

        return match (true) {
            str_contains($address, 'linkedin') => self::LinkedIn,
            str_contains($address, 'stepstone') => self::StepStone,
            str_contains($address, 'indeed') => self::Indeed,
            str_contains($address, 'xing') => self::Xing,
            str_contains($address, 'arbeitsagentur') => self::Arbeitsagentur,
            default => self::Other,
        };
    }
}

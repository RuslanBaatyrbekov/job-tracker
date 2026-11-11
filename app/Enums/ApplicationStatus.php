<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApplicationStatus: string implements HasColor, HasLabel
{
    case Applied = 'applied';
    case Interview = 'interview';
    case Offer = 'offer';
    case Rejected = 'rejected';
    case NoResponse = 'no_response';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Applied => 'Отправлено',
            self::Interview => 'Интервью / Тестовое',
            self::Offer => 'Оффер',
            self::Rejected => 'Отказ',
            self::NoResponse => 'Нет ответа',
            self::Withdrawn => 'Отозван',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Applied => 'info',
            self::Interview => 'warning',
            self::Offer => 'success',
            self::Rejected => 'danger',
            self::NoResponse, self::Withdrawn => 'gray',
        };
    }

    /**
     * Statuses that close the application: nothing should move it forward automatically.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Offer, self::Rejected, self::Withdrawn], true);
    }

    /**
     * Pipeline position. Used to ignore "downgrades" coming from late emails
     * (e.g. an auto-confirmation arriving after the interview invite).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Applied, self::NoResponse => 1,
            self::Interview => 2,
            self::Offer => 3,
            self::Rejected, self::Withdrawn => 4,
        };
    }

    /**
     * Decide if an incoming status (from email analysis) should replace the current one.
     */
    public function canTransitionTo(self $next): bool
    {
        if ($this === $next || $this->isFinal()) {
            return false;
        }

        // A rejection is valid at any stage; otherwise only move forward.
        return $next === self::Rejected || $next->rank() > $this->rank();
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->getLabel()])->all();
    }
}

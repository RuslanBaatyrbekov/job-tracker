<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeadStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Shortlisted = 'shortlisted';
    case Applied = 'applied';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Новая',
            self::Shortlisted => 'В шортлисте',
            self::Applied => 'Откликнулся',
            self::Skipped => 'Пропущена',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Shortlisted => 'warning',
            self::Applied => 'success',
            self::Skipped => 'gray',
        };
    }
}

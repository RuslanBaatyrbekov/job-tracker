<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmailStatus: string implements HasColor, HasLabel
{
    case PendingSubjectScan = 'pending_subject_scan';
    case PendingBodyAnalysis = 'pending_body_analysis';
    case PendingAlertParse = 'pending_alert_parse';
    case Irrelevant = 'irrelevant';
    case Processed = 'processed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingSubjectScan => 'Ждёт фильтра',
            self::PendingBodyAnalysis => 'Ждёт анализа',
            self::PendingAlertParse => 'Job alert',
            self::Irrelevant => 'Нерелевантно',
            self::Processed => 'Обработано',
            self::Failed => 'Ошибка',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingSubjectScan, self::PendingBodyAnalysis, self::PendingAlertParse => 'warning',
            self::Processed => 'success',
            self::Failed => 'danger',
            self::Irrelevant => 'gray',
        };
    }
}

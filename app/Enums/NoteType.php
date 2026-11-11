<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NoteType: string implements HasColor, HasLabel
{
    case AiAdvice = 'ai_advice';
    case RejectionReason = 'rejection_reason';
    case InterviewPrep = 'interview_prep';
    case FollowUpDraft = 'follow_up_draft';
    case ResumeCorrection = 'resume_correction';
    case UserNote = 'user_note';

    public function getLabel(): string
    {
        return match ($this) {
            self::AiAdvice => 'Совет ИИ',
            self::RejectionReason => 'Причина отказа',
            self::InterviewPrep => 'Подготовка к интервью',
            self::FollowUpDraft => 'Черновик follow-up',
            self::ResumeCorrection => 'Правка резюме',
            self::UserNote => 'Личная заметка',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AiAdvice => 'success',
            self::RejectionReason => 'danger',
            self::InterviewPrep => 'warning',
            self::FollowUpDraft => 'info',
            default => 'gray',
        };
    }
}

<?php

namespace App\Models;

use App\Enums\EmailStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'mailbox_id',
        'imap_uid',
        'subject',
        'from_address',
        'from_name',
        'date',
        'status',
        'body_text',
        'body_html',
    ];

    protected $casts = [
        'date' => 'datetime',
        'status' => EmailStatus::class,
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function jobLeads(): HasMany
    {
        return $this->hasMany(JobLead::class, 'source_email_id');
    }

    public function jobApplication(): HasOne
    {
        return $this->hasOne(JobApplication::class, 'source_email_id');
    }
}

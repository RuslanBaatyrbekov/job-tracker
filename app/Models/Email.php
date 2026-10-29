<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function jobApplication(): HasOne
    {
        return $this->hasOne(JobApplication::class, 'source_email_id');
    }
}

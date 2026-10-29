<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mailbox extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'host',
        'port',
        'username',
        'password',
        'protocol',
        'encryption',
        'last_synced_at',
        'is_active',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'encrypted', // Автоматическое шифрование пароля в Laravel 11
    ];

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }
}

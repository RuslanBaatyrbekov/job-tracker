<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobCategory extends Model
{
    protected $fillable = ['name', 'color'];

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}

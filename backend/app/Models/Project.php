<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'main_file',
        'compile_status',
        'compile_duration_ms',
        'compiled_at',
    ];

    protected $casts = [
        'compiled_at' => 'datetime',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function problems(): HasMany
    {
        return $this->hasMany(Problem::class);
    }
}

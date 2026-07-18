<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Problem extends Model
{
    protected $fillable = [
        'project_id',
        'file',
        'line',
        'column',
        'severity',
        'message',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

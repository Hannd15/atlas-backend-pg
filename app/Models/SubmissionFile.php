<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionFile extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'submission_id',
        'file_id',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}

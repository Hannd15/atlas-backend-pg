<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverableFile extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'deliverable_id',
        'file_id',
    ];

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}

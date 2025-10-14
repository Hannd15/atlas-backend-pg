<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryProjectFile extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'repository_item_id',
        'file_id',
    ];

    public function repositoryProject(): BelongsTo
    {
        return $this->belongsTo(RepositoryProject::class, 'repository_item_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}

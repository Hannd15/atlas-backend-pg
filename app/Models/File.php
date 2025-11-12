<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'extension',
        'url',
        'disk',
        'path',
    ];

    protected static function booted(): void
    {
        static::deleting(function (File $file) {
            if ($file->path && $file->disk) {
                Storage::disk($file->disk)->delete($file->path);
            }
        });
    }

    public function deliverables(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Deliverable::class, 'deliverable_files');
    }

    public function submissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Submission::class, 'submission_files');
    }

    public function repositoryProjects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(RepositoryProject::class, 'repository_project_files', 'file_id', 'repository_item_id');
    }

    public function proposals(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Proposal::class, 'proposal_files');
    }
}

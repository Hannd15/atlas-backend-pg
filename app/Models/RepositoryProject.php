<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepositoryProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'url',
        'publish_date',
        'keywords_es',
        'keywords_en',
        'abstract_es',
        'abstract_en',
    ];

    protected function casts(): array
    {
        return [
            'publish_date' => 'date',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function files()
    {
        return $this->belongsToMany(File::class, 'repository_project_files', 'repository_item_id', 'file_id');
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'files',
            'project.groups.members',
            'project.staff',
            'project.proposal.thematicLine',
        ]);
    }
}

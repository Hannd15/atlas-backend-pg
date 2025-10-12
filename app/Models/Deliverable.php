<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deliverable extends Model
{
    use HasFactory;

    protected $fillable = [
        'phase_id',
        'name',
        'due_date',
    ];

    public function phase()
    {
        return $this->belongsTo(Phase::class);
    }

    public function files()
    {
        return $this->belongsToMany(File::class, 'deliverable_files');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}

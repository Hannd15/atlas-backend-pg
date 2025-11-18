<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'meeting_date',
        'start_time',
        'end_time',
        'timezone',
        'observations',
        'created_by',
        'google_calendar_event_id',
        'google_meet_url',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Meeting $meeting): void {
            $meeting->url = $meeting->buildUrl();
        });

        static::updating(function (Meeting $meeting): void {
            if ($meeting->isDirty('meeting_date')) {
                $meeting->url = $meeting->buildUrl();
            }
        });
    }

    private function buildUrl(): string
    {
        $date = $this->meeting_date instanceof Carbon
            ? $this->meeting_date
            : ($this->meeting_date ? Carbon::parse($this->meeting_date) : Carbon::now());

        return sprintf('https://meetings.test/project-%s/%s', $this->project_id, $date->format('Ymd'));
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendees()
    {
        return $this->belongsToMany(User::class, 'meeting_attendees')->withTimestamps();
    }
}

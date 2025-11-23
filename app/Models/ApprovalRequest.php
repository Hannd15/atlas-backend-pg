<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pendiente';

    public const STATUS_APPROVED = 'Aprobado';

    public const STATUS_REJECTED = 'Rechazado';

    public const DECISION_APPROVED = 'Aprobado';

    public const DECISION_REJECTED = 'Rechazado';

    protected $fillable = [
        'title',
        'description',
        'requested_by',
        'action_key',
        'action_payload',
        'status',
        'resolved_decision',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'action_payload' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ApprovalRequestRecipient::class);
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'approval_request_files')->withTimestamps();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}

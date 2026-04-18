<?php

namespace App\Models;

use App\Enums\ShooterClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShooterAccountClaim extends Model
{
    protected $fillable = [
        'shooter_id',
        'user_id',
        'match_id',
        'status',
        'evidence',
        'reviewer_id',
        'reviewed_at',
        'reviewer_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShooterClaimStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function shooter(): BelongsTo
    {
        return $this->belongsTo(Shooter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isPending(): bool
    {
        return $this->status === ShooterClaimStatus::Pending;
    }
}

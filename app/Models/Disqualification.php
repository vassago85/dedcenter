<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disqualification extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'shooter_id',
        'target_set_id',
        'reason',
        'issued_by',
    ];

    public function isMatchDq(): bool
    {
        return $this->target_set_id === null;
    }

    public function isStageDq(): bool
    {
        return $this->target_set_id !== null;
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function shooter(): BelongsTo
    {
        return $this->belongsTo(Shooter::class);
    }

    public function targetSet(): BelongsTo
    {
        return $this->belongsTo(TargetSet::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}

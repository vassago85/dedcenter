<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id', 'sent_by', 'subject', 'body', 'audience', 'squad_id', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function squad(): BelongsTo
    {
        return $this->belongsTo(Squad::class);
    }
}

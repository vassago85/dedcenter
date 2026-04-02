<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchRegistrationCustomValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_registration_id',
        'match_custom_field_id',
        'value',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(MatchRegistration::class, 'match_registration_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(MatchCustomField::class, 'match_custom_field_id');
    }
}

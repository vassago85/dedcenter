<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'user_id',
        'payment_reference',
        'payment_status',
        'proof_of_payment_path',
        'amount',
        'admin_notes',
        'sa_id_number',
        'caliber',
        'bullet_brand_type',
        'bullet_weight',
        'action_brand',
        'barrel_brand_length',
        'trigger_brand',
        'stock_chassis_brand',
        'muzzle_brake_silencer_brand',
        'scope_brand_type',
        'scope_mount_brand',
        'bipod_brand',
        'share_rifle_with',
        'contact_number',
        'is_free_entry',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_free_entry' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ──

    public static function generatePaymentReference(User $user): string
    {
        $prefix = Setting::get('bank_reference_prefix', 'DC');
        $surname = strtoupper(
            preg_replace('/[^A-Za-z]/', '', last(explode(' ', $user->name)))
        );
        $surname = substr($surname, 0, 12) ?: 'USER';

        do {
            $random = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $reference = "{$prefix}-{$surname}-{$random}";
        } while (static::where('payment_reference', $reference)->exists());

        return $reference;
    }

    public function isPending(): bool
    {
        return $this->payment_status === 'pending_payment';
    }

    public function isProofSubmitted(): bool
    {
        return $this->payment_status === 'proof_submitted';
    }

    public function isConfirmed(): bool
    {
        return $this->payment_status === 'confirmed';
    }

    public function isRejected(): bool
    {
        return $this->payment_status === 'rejected';
    }

    public function isFreeEntry(): bool
    {
        return (bool) $this->is_free_entry;
    }

    public function scopeSharesRifleWith($query, string $name)
    {
        return $query->where('share_rifle_with', $name);
    }
}

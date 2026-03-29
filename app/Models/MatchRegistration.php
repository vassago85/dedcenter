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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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
}

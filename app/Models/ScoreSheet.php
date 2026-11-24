<?php

namespace App\Models;

use App\Enums\AlrhaClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A printed ALRHA mark-sense score card.
 *
 * The whole point of this record is to decouple the camera scan from
 * the live stage graph. The `token` is the camera-readable identity,
 * and `layout` freezes the exact (target, shot) grid the sheet was
 * printed with so the match director can still import a card after
 * stages have been edited in the meantime.
 *
 * A sheet with `shooter_id = NULL` is an **anonymous spare**: pre-printed
 * so an unknown walk-in can be given a card on the day and bound to an
 * actual shooter in the scanner before confirm. Spares always carry
 * their `alrha_class` (because Hunters and Varmint distances differ);
 * pre-assigned cards leave `alrha_class` NULL and derive it from the
 * shooter.
 *
 * @property int $id
 * @property int $match_id
 * @property ?int $shooter_id
 * @property ?AlrhaClass $alrha_class
 * @property string $token
 * @property array $layout
 * @property int $format_version
 * @property ?int $printed_by
 * @property ?\Illuminate\Support\Carbon $printed_at
 * @property ?\Illuminate\Support\Carbon $claimed_at
 */
class ScoreSheet extends Model
{
    use HasFactory;

    public const FORMAT_VERSION = 1;

    /** QR payload prefix. `DCS` = DeadCenter Score card; `1` = format. */
    public const QR_PREFIX = 'DCS1:';

    protected $fillable = [
        'match_id',
        'shooter_id',
        'alrha_class',
        'token',
        'layout',
        'format_version',
        'printed_by',
        'printed_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'format_version' => 'integer',
            'printed_at' => 'datetime',
            'claimed_at' => 'datetime',
            'alrha_class' => AlrhaClass::class,
        ];
    }

    /** True for anonymous spares that still need a shooter assignment. */
    public function isSpare(): bool
    {
        return $this->shooter_id === null;
    }

    public function scopeUnclaimed(Builder $q): Builder
    {
        return $q->whereNull('shooter_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ShootingMatch::class, 'match_id');
    }

    public function shooter(): BelongsTo
    {
        return $this->belongsTo(Shooter::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function qrPayload(): string
    {
        return self::QR_PREFIX.$this->token;
    }

    /**
     * Generate a fresh, collision-free token.
     *
     * Short-base32 alphabet so a camera reading the QR doesn't choke on
     * zero-vs-O type confusables.
     */
    public static function generateToken(): string
    {
        do {
            $token = strtoupper(Str::random(12));
        } while (self::where('token', $token)->exists());

        return $token;
    }
}

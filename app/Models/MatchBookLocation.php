<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchBookLocation extends Model
{
    protected $fillable = [
        'match_book_id',
        'name',
        'maps_link',
        'gps_coordinates',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function matchBook(): BelongsTo
    {
        return $this->belongsTo(MatchBook::class);
    }

    /**
     * Get QR code URL for this location's maps link.
     */
    public function getQrCodeUrl(int $size = 100, string $color = '2563eb'): string
    {
        if (empty($this->maps_link)) {
            return '';
        }

        return 'https://api.qrserver.com/v1/create-qr-code/?size='.$size.'x'.$size.'&data='.urlencode($this->maps_link).'&color='.$color;
    }
}

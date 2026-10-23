<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_id
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $price_at_booking
 * @property CarbonInterface|null $start_at
 * @property CarbonInterface|null $end_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Service|null $service
 */
#[Fillable(['service_id', 'customer_name', 'customer_phone', 'start_at', 'end_at', 'price_at_booking'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            if ($booking->end_at === null && $booking->start_at !== null && $booking->service !== null) {
                $booking->end_at = $booking->start_at->copy()->addMinutes($booking->service->duration_minutes);
            }
        });
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'price_at_booking' => 'decimal:2',
        ];
    }
}

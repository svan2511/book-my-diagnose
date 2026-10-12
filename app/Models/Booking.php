<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'booking_number', 'customer_id', 'status',
        'subtotal', 'discount', 'total', 'currency',
        'patient_name', 'patient_age', 'patient_gender', 'patient_phone', 'patient_email',
        'address', 'area', 'city', 'state', 'pincode', 'landmark',
        'latitude', 'longitude', 'location_accuracy', 'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'scheduled_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->latest();
    }

    public function collection(): HasOne
    {
        return $this->hasOne(SampleCollection::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)->latest();
    }

    public function latestReport(): HasOne
    {
        return $this->hasOne(Report::class)->latestOfMany();
    }

    public function status(): BookingStatus
    {
        return BookingStatus::from($this->status);
    }
}

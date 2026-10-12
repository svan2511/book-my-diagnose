<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LabTest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'category_id', 'name', 'code', 'serial_no', 'description',
        'preparation_instructions', 'general_instructions', 'sample_type', 'sample_detail',
        'storage_temp', 'method', 'schedule', 'reporting',
        'source_sheet', 'code_prefix', 'price', 'mrp',
        'turnaround_hours', 'parameter_count', 'home_collection_available', 'status',
        'is_nabl', 'is_outsourced', 'is_discontinued',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'mrp' => 'decimal:2',
            'home_collection_available' => 'boolean',
            'is_nabl' => 'boolean',
            'is_outsourced' => 'boolean',
            'is_discontinued' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TestCategory::class, 'category_id');
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'test_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeHomeCollectible($query)
    {
        return $query->where('home_collection_available', true);
    }

    public function discountPercent(): ?int
    {
        if (! $this->mrp || $this->mrp <= $this->price) {
            return null;
        }

        return (int) round((($this->mrp - $this->price) / $this->mrp) * 100);
    }
}

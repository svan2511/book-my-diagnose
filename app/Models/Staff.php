<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Staff extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['uuid', 'name', 'phone', 'role', 'area', 'status'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    public function collections(): HasMany
    {
        return $this->hasMany(SampleCollection::class, 'staff_id');
    }

    public function activeCollections(): HasMany
    {
        return $this->collections()->whereIn('status', ['pending', 'assigned', 'on_the_way']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

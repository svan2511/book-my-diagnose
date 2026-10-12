<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TestCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['uuid', 'name', 'slug', 'description', 'status'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
        static::creating(fn (self $m) => $m->slug ??= Str::slug($m->name));
    }

    public function tests(): HasMany
    {
        return $this->hasMany(LabTest::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

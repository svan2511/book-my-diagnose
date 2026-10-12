<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ContactQuery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'phone', 'email', 'subject', 'message',
        'status', 'assigned_to', 'admin_notes',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }
}

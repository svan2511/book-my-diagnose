<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionStatusHistory extends Model
{
    protected $fillable = ['collection_id', 'from_status', 'to_status', 'changed_by', 'notes'];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(SampleCollection::class, 'collection_id');
    }
}

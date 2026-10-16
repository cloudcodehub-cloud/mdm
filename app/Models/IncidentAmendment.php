<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incident_id',
    'author_user_id',
    'reason',
    'field_key',
    'original_content',
    'new_content',
])]
class IncidentAmendment extends Model
{
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}

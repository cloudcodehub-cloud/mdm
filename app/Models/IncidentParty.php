<?php

namespace App\Models;

use App\Enums\IncidentPartyKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'incident_id',
    'party_kind',
    'client_id',
    'employee_id',
    'name',
    'organization',
    'involvement',
    'sort_order',
])]
class IncidentParty extends Model
{
    protected function casts(): array
    {
        return [
            'party_kind' => IncidentPartyKind::class,
            'sort_order' => 'integer',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

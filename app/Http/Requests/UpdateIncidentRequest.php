<?php

namespace App\Http\Requests;

use App\Models\Incident;

class UpdateIncidentRequest extends StoreIncidentRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can('update', $incident) ?? false);
    }
}

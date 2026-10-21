<?php

namespace App\Policies;

use App\Enums\RetainedDocumentKey;
use App\Models\Incident;
use App\Models\RetainedDocumentArtifact;
use App\Models\User;

class RetainedDocumentArtifactPolicy
{
    public function view(User $user, RetainedDocumentArtifact $artifact): bool
    {
        $source = $artifact->source;

        if (! $source instanceof Incident) {
            return false;
        }

        $incidentPolicy = app(IncidentPolicy::class);

        return match ($artifact->document_key) {
            RetainedDocumentKey::IncidentFinal => $incidentPolicy->manage($user, $source),
            RetainedDocumentKey::IncidentSubmitted => $incidentPolicy->view($user, $source),
            default => false,
        };
    }
}

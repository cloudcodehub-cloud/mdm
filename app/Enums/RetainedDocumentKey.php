<?php

namespace App\Enums;

enum RetainedDocumentKey: string
{
    case IncidentSubmitted = 'incident_submitted';
    case IncidentFinal = 'incident_final';

    public function label(): string
    {
        return match ($this) {
            self::IncidentSubmitted => 'Incident submitted PDF',
            self::IncidentFinal => 'Incident final PDF',
        };
    }

    public function adminCategory(): AdminDocumentCategory
    {
        return match ($this) {
            self::IncidentSubmitted => AdminDocumentCategory::IncidentSubmittedPdf,
            self::IncidentFinal => AdminDocumentCategory::IncidentFinalPdf,
        };
    }
}

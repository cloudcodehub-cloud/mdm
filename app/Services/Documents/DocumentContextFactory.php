<?php

namespace App\Services\Documents;

use App\Models\User;
use App\Services\SettingsService;
use App\Support\Documents\DocumentContext;
use App\Support\Documents\DocumentFilename;

class DocumentContextFactory
{
    public const CONFIDENTIALITY = 'Confidential — Contains agency operational and care information. Handle according to agency privacy and record-retention policies.';

    public function __construct(private SettingsService $settings) {}

    public function make(User $user): DocumentContext
    {
        $agency = $this->settings->agencyName();

        return new DocumentContext(
            agencyName: $agency,
            agencySlug: DocumentFilename::agencySlug($agency),
            timezone: $this->settings->timezone(),
            timezoneLabel: $this->settings->timezoneLabel($this->settings->timezone()),
            generatedByName: $user->name,
            generatedByRole: $user->role->label(),
            generatedAtLabel: $this->settings->formatDateTime($this->settings->localNow()).' ('.$this->settings->timezone().')',
            confidentiality: self::CONFIDENTIALITY,
            productMark: 'MDM',
            productName: 'Magic Data Management',
        );
    }
}

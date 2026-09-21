<?php

namespace App\Support\Documents;

final class DocumentContext
{
    public function __construct(
        public string $agencyName,
        public string $agencySlug,
        public string $timezone,
        public string $timezoneLabel,
        public string $generatedByName,
        public string $generatedByRole,
        public string $generatedAtLabel,
        public string $confidentiality,
        public string $productMark,
        public string $productName,
    ) {}
}

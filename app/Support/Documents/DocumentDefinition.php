<?php

namespace App\Support\Documents;

final class DocumentDefinition
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $title,
        public string $view,
        public string $filename,
        public string $subtitle,
        public string $reference,
        public array $data,
        public DocumentContext $context,
    ) {}
}

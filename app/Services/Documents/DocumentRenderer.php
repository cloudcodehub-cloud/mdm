<?php

namespace App\Services\Documents;

use App\Support\Documents\DocumentDefinition;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DocumentRenderer
{
    public function preview(DocumentDefinition $document, string $backUrl, string $downloadUrl): Response
    {
        return response()->view('documents.preview', [
            'document' => $document,
            'backUrl' => $backUrl,
            'downloadUrl' => $downloadUrl,
        ]);
    }

    public function download(DocumentDefinition $document): SymfonyResponse
    {
        return $this->pdf($document)->download($document->filename);
    }

    public function output(DocumentDefinition $document): string
    {
        return $this->pdf($document)->output();
    }

    private function pdf(DocumentDefinition $document): DomPdfDocument
    {
        return Pdf::loadView('documents.pdf', [
            'document' => $document,
        ])
            ->setPaper('letter')
            ->setOption('isPhpEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans');
    }
}

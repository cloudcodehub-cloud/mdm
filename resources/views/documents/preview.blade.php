<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    @include('documents.partials.styles')
</head>
<body>
    <div class="preview-toolbar">
        <a href="{{ $backUrl }}">Back to record</a>
        <a href="{{ $downloadUrl }}">Download PDF</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="preview-shell">
        @include('documents.partials.shell', ['document' => $document, 'forPdf' => false])
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    @include('documents.partials.styles')
</head>
<body>
    @include('documents.partials.shell', ['document' => $document, 'forPdf' => true])
</body>
</html>

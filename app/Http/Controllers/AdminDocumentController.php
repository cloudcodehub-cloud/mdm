<?php

namespace App\Http\Controllers;

use App\Models\EmployeeCredential;
use App\Models\EmployeeTraining;
use App\Services\AdminDocumentIndexService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDocumentController extends Controller
{
    public function __construct(private AdminDocumentIndexService $documents) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $payload = $this->documents->index($user, $request->only([
            'search',
            'category',
            'origin',
            'source_area',
            'from',
            'to',
        ]));

        return Inertia::render('documents/index', [
            'documents' => $payload['documents'],
            'filters' => $payload['filters'],
            'filterOptions' => $payload['filter_options'],
        ]);
    }

    public function downloadCredential(Request $request, EmployeeCredential $credential): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return $this->documents->streamCredentialDocument($user, $credential);
    }

    public function downloadTraining(Request $request, EmployeeTraining $training): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return $this->documents->streamTrainingDocument($user, $training);
    }
}

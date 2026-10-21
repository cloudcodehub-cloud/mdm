<?php

namespace App\Services\Documents;

use App\Enums\RetainedDocumentKey;
use App\Models\Incident;
use App\Models\RetainedDocumentArtifact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MDM 10-02 / 10-15 — retain exact official PDF binaries at source milestones.
 * Never silently overwrite or regenerate retained historical artifacts.
 */
class RetainedDocumentService
{
    public function __construct(
        private MdmDocumentFactory $documents,
        private DocumentRenderer $renderer,
    ) {}

    public function retainIncidentSubmitted(Incident $incident, User $actor): RetainedDocumentArtifact
    {
        $existing = $this->currentFor($incident, RetainedDocumentKey::IncidentSubmitted);

        if ($existing !== null) {
            return $existing;
        }

        $definition = $this->documents->incidentSubmitted($incident, $actor);

        return $this->storeNew(
            source: $incident,
            key: RetainedDocumentKey::IncidentSubmitted,
            milestone: 'submitted',
            version: 1,
            binary: $this->renderer->output($definition),
            filename: $definition->filename,
            title: $definition->title,
            subtitle: $definition->subtitle,
            sourceStatus: $incident->status->value,
            actor: $actor,
            meta: [
                'tracking_number' => $incident->tracking_number,
                'client_id' => $incident->client_id,
                'preserves_exact_bytes' => true,
            ],
        );
    }

    public function retainIncidentFinal(Incident $incident, User $actor): RetainedDocumentArtifact
    {
        $definition = $this->documents->incidentFinal($incident, $actor);
        $nextVersion = $this->nextVersion($incident, RetainedDocumentKey::IncidentFinal);

        return DB::transaction(function () use ($incident, $actor, $definition, $nextVersion): RetainedDocumentArtifact {
            $artifact = $this->storeNew(
                source: $incident,
                key: RetainedDocumentKey::IncidentFinal,
                milestone: 'closed',
                version: $nextVersion,
                binary: $this->renderer->output($definition),
                filename: $this->versionedFilename($definition->filename, $nextVersion),
                title: $definition->title.($nextVersion > 1 ? ' (amended v'.$nextVersion.')' : ''),
                subtitle: $definition->subtitle,
                sourceStatus: $incident->status->value,
                actor: $actor,
                meta: [
                    'tracking_number' => $incident->tracking_number,
                    'client_id' => $incident->client_id,
                    'closure_note' => $incident->closure_note,
                    'reopened_at' => $incident->reopened_at?->toIso8601String(),
                    'preserves_exact_bytes' => true,
                    'version_label' => $nextVersion > 1 ? 'amended' : 'original',
                ],
            );

            $this->markSuperseded(
                $incident,
                RetainedDocumentKey::IncidentFinal,
                $artifact,
            );

            return $artifact;
        });
    }

    public function currentFor(Incident $incident, RetainedDocumentKey $key): ?RetainedDocumentArtifact
    {
        return RetainedDocumentArtifact::query()
            ->where('source_type', $incident->getMorphClass())
            ->where('source_id', $incident->id)
            ->where('document_key', $key)
            ->where('is_current', true)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @return list<RetainedDocumentArtifact>
     */
    public function historyFor(Incident $incident, RetainedDocumentKey $key): array
    {
        return RetainedDocumentArtifact::query()
            ->where('source_type', $incident->getMorphClass())
            ->where('source_id', $incident->id)
            ->where('document_key', $key)
            ->orderByDesc('version')
            ->get()
            ->all();
    }

    public function download(RetainedDocumentArtifact $artifact, bool $inline = false): StreamedResponse
    {
        abort_unless($artifact->existsOnDisk(), 404);

        return Storage::disk($artifact->disk)->response(
            $artifact->path,
            $artifact->original_filename,
            [
                'Content-Type' => $artifact->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function storeNew(
        Incident $source,
        RetainedDocumentKey $key,
        string $milestone,
        int $version,
        string $binary,
        string $filename,
        string $title,
        string $subtitle,
        string $sourceStatus,
        User $actor,
        array $meta,
    ): RetainedDocumentArtifact {
        $disk = 'local';
        $directory = 'retained-documents/incidents/'.$source->id;
        $path = $directory.'/'.$key->value.'-v'.$version.'.pdf';

        Storage::disk($disk)->put($path, $binary);

        return RetainedDocumentArtifact::query()->create([
            'document_key' => $key,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'milestone' => $milestone,
            'version' => $version,
            'is_current' => true,
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
            'byte_size' => strlen($binary),
            'checksum_sha256' => hash('sha256', $binary),
            'title' => $title,
            'subtitle' => $subtitle,
            'source_status' => $sourceStatus,
            'generated_by_user_id' => $actor->id,
            'generated_at' => now(),
            'meta' => $meta,
        ]);
    }

    private function nextVersion(Incident $incident, RetainedDocumentKey $key): int
    {
        $max = (int) RetainedDocumentArtifact::query()
            ->where('source_type', $incident->getMorphClass())
            ->where('source_id', $incident->id)
            ->where('document_key', $key)
            ->max('version');

        return $max + 1;
    }

    private function markSuperseded(
        Incident $incident,
        RetainedDocumentKey $key,
        RetainedDocumentArtifact $current,
    ): void {
        RetainedDocumentArtifact::query()
            ->where('source_type', $incident->getMorphClass())
            ->where('source_id', $incident->id)
            ->where('document_key', $key)
            ->where('id', '!=', $current->id)
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'superseded_at' => now(),
                'superseded_by_artifact_id' => $current->id,
            ]);
    }

    private function versionedFilename(string $filename, int $version): string
    {
        if ($version <= 1) {
            return $filename;
        }

        if (str_ends_with(strtolower($filename), '.pdf')) {
            return substr($filename, 0, -4).'-v'.$version.'.pdf';
        }

        return $filename.'-v'.$version;
    }
}

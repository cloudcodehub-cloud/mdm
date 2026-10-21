<?php

namespace App\Models;

use App\Enums\RetainedDocumentKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property RetainedDocumentKey|string $document_key
 * @property string $source_type
 * @property int $source_id
 * @property string $milestone
 * @property int $version
 * @property bool $is_current
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $byte_size
 * @property string $checksum_sha256
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $source_status
 * @property int|null $generated_by_user_id
 * @property Carbon $generated_at
 * @property Carbon|null $superseded_at
 * @property int|null $superseded_by_artifact_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $source
 * @property-read User|null $generatedBy
 * @property-read RetainedDocumentArtifact|null $supersededBy
 */
#[Fillable([
    'document_key',
    'source_type',
    'source_id',
    'milestone',
    'version',
    'is_current',
    'disk',
    'path',
    'original_filename',
    'mime_type',
    'byte_size',
    'checksum_sha256',
    'title',
    'subtitle',
    'source_status',
    'generated_by_user_id',
    'generated_at',
    'superseded_at',
    'superseded_by_artifact_id',
    'meta',
])]
class RetainedDocumentArtifact extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_key' => RetainedDocumentKey::class,
            'version' => 'integer',
            'is_current' => 'boolean',
            'byte_size' => 'integer',
            'generated_at' => 'datetime',
            'superseded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * @return BelongsTo<RetainedDocumentArtifact, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_artifact_id');
    }

    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}

<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $author_id
 * @property string $title
 * @property string $body
 * @property AnnouncementAudience $audience
 * @property Carbon $published_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $author
 *
 * @method static Builder<static> currentlyPublished()
 */
#[Fillable([
    'organization_id',
    'author_id',
    'title',
    'body',
    'audience',
    'published_at',
    'expires_at',
    'is_active',
])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentlyPublished(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where('published_at', '<=', $now)
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            });
    }
}

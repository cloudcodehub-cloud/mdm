<?php

namespace App\Models;

use Database\Factories\CareServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property bool $note_required
 * @property bool $supervisor_review_expected
 * @property string|null $payer_code
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TaskCatalogBundle> $recommendedBundles
 * @property-read Collection<int, TaskCatalogItem> $recommendedItems
 * @property-read Collection<int, Client> $clients
 *
 * @method static Builder<static> active()
 */
#[Fillable([
    'slug',
    'name',
    'description',
    'is_active',
    'note_required',
    'supervisor_review_expected',
    'payer_code',
    'sort_order',
])]
class CareService extends Model
{
    /** @use HasFactory<CareServiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'note_required' => 'boolean',
            'supervisor_review_expected' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return BelongsToMany<TaskCatalogBundle, $this>
     */
    public function recommendedBundles(): BelongsToMany
    {
        return $this->belongsToMany(TaskCatalogBundle::class, 'care_service_task_catalog_bundles')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<TaskCatalogItem, $this>
     */
    public function recommendedItems(): BelongsToMany
    {
        return $this->belongsToMany(TaskCatalogItem::class, 'care_service_task_catalog_items')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_care_services')
            ->withTimestamps();
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'service';
        $suffix = 1;

        while (self::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}

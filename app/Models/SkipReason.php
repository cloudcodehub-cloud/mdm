<?php

namespace App\Models;

use Database\Factories\SkipReasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $requires_comment
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> active()
 */
#[Fillable([
    'name',
    'code',
    'requires_comment',
    'is_active',
    'sort_order',
])]
class SkipReason extends Model
{
    public const CLIENT_REFUSED = 'client_refused';

    /** @use HasFactory<SkipReasonFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_comment' => 'boolean',
            'is_active' => 'boolean',
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

    public function requiresExplanation(): bool
    {
        return $this->requires_comment || $this->code === self::CLIENT_REFUSED;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $key
 * @property string $seedable_type
 * @property int $seedable_id
 */
#[Fillable([
    'key',
    'seedable_type',
    'seedable_id',
])]
class DemoSeedRecord extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function seedable(): MorphTo
    {
        return $this->morphTo();
    }
}

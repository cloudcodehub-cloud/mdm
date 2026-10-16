<?php

namespace App\Models;

use App\Enums\EmployeeAdminHistoryCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property EmployeeAdminHistoryCategory $category
 * @property string $summary
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $reason
 * @property Carbon|null $effective_on
 * @property int|null $actor_user_id
 * @property array<string, mixed>|null $meta
 */
#[Fillable([
    'employee_id',
    'category',
    'summary',
    'old_value',
    'new_value',
    'reason',
    'effective_on',
    'actor_user_id',
    'subject_type',
    'subject_id',
    'meta',
])]
class EmployeeAdminHistoryEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => EmployeeAdminHistoryCategory::class,
            'effective_on' => 'date',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

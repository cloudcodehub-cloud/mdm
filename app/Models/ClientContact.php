<?php

namespace App\Models;

use App\Enums\ClientContactRole;
use Database\Factories\ClientContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $client_id
 * @property ClientContactRole $role
 * @property string $name
 * @property string|null $relationship
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $notes
 * @property bool $is_primary
 */
#[Fillable([
    'client_id',
    'role',
    'name',
    'relationship',
    'phone',
    'email',
    'notes',
    'is_primary',
])]
class ClientContact extends Model
{
    /** @use HasFactory<ClientContactFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ClientContactRole::class,
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

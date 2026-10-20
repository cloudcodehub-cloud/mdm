<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('support_ticket');

        return $ticket !== null && ($this->user()?->can('reassign', $ticket) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('it_tech_access', true)),
            ],
        ];
    }

    public function technician(): User
    {
        return User::query()->findOrFail((int) $this->validated('assigned_user_id'));
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        if (! $client instanceof Client) {
            return false;
        }

        $status = ClientStatus::tryFrom((string) $this->input('status'));

        if ($status === ClientStatus::Archived) {
            return $this->user()?->can('archive', $client) ?? false;
        }

        return $this->user()?->can('manageLifecycle', $client) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $status = ClientStatus::tryFrom((string) $this->input('status'));
        $requiresReason = in_array($status, [ClientStatus::Paused, ClientStatus::Discharged], true);

        return [
            'status' => ['required', Rule::enum(ClientStatus::class)],
            'reason' => [$requiresReason ? 'required' : 'nullable', 'string', 'max:1000'],
            'effective_on' => ['nullable', 'date'],
        ];
    }
}

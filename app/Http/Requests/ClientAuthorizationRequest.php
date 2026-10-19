<?php

namespace App\Http\Requests;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationUnit;
use App\Models\ClientAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $authorization = $this->route('authorization')
            ?? $this->route('client_authorization')
            ?? $this->route('clientAuthorization');

        if ($authorization instanceof ClientAuthorization) {
            return $this->user()?->can('update', $authorization) ?? false;
        }

        return $this->user()?->can('create', ClientAuthorization::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $client = $this->route('client');

        if ($client instanceof \App\Models\Client) {
            $this->merge(['client_id' => $client->id]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $authorization = $this->route('authorization')
            ?? $this->route('client_authorization')
            ?? $this->route('clientAuthorization');
        $authorizationId = $authorization instanceof ClientAuthorization ? $authorization->id : null;

        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'authorization_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('client_authorizations', 'authorization_number')->ignore($authorizationId),
            ],
            'payer' => ['required', 'string', 'max:255'],
            'service_type' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'authorized_units' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'unit' => ['required', Rule::enum(AuthorizationUnit::class)],
            'status' => ['required', Rule::enum(AuthorizationStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}

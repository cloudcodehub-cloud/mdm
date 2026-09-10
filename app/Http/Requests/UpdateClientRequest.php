<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ClientFormRules;
use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    use ClientFormRules;

    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            && ($this->user()?->can('update', $client) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareClientPayload();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        $clientId = $client instanceof Client ? $client->id : null;

        return $this->clientFieldRules($clientId);
    }
}

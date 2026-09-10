<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ClientFormRules;
use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    use ClientFormRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Client::class) ?? false;
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
        return $this->clientFieldRules();
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Conversation::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:5000'],
            'care_context_client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'care_context_visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'care_context_task_title' => ['nullable', 'string', 'max:255'],
            'care_context_label' => ['nullable', 'string', 'max:255'],
            'stay' => ['sometimes', 'boolean'],
        ];
    }

    public function recipient(): User
    {
        return User::query()->findOrFail((int) $this->validated('user_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function careContext(): array
    {
        $validated = $this->validated();

        if (! isset($validated['care_context_label']) || ! is_string($validated['care_context_label'])) {
            return [];
        }

        return array_filter([
            'client_id' => $validated['care_context_client_id'] ?? null,
            'visit_id' => $validated['care_context_visit_id'] ?? null,
            'task_title' => $validated['care_context_task_title'] ?? null,
            'label' => $validated['care_context_label'],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}

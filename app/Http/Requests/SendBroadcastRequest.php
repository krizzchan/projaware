<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['barangay_id', 'category_id'] as $field) {
            if ($this->input($field) === 'all') {
                $values[$field] = null;
            }
        }
        if (is_string($this->input('message'))) {
            $values['message'] = trim($this->input('message'));
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        $advisory = $this->routeIs('menu.advisories.dispatchQuick');

        return [
            'audience_mode' => [Rule::excludeIf($advisory), 'nullable', Rule::in(['all', 'single', 'multiple', 'barangay', 'category', 'filters'])],
            'selected_contact_ids' => [Rule::excludeIf($advisory || ! in_array($this->input('audience_mode'), ['single', 'multiple'], true)), 'required', 'array', 'min:1', $this->input('audience_mode') === 'single' ? 'max:1' : 'max:1000'],
            'selected_contact_ids.*' => ['integer', 'distinct', 'exists:contacts,id'],
            'request_id' => ['required', 'uuid'],
            'message' => ['required', 'string', 'max:670', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/'],
            'barangay_id' => ['required_if:audience_mode,barangay', 'nullable', 'integer', 'exists:barangays,id'],
            'category_id' => ['required_if:audience_mode,category', 'nullable', 'integer', 'exists:categories,id'],
            'title' => [$advisory ? 'required' : 'nullable', 'string', 'max:150'],
            'severity' => [$advisory ? 'required' : 'nullable', Rule::in(['info', 'warning', 'critical'])],
            'category' => ['nullable', 'string', 'max:100'],
            'target_scope' => [$advisory ? 'required' : 'nullable', Rule::in(['all', 'specific'])],
            'selected_barangays' => ['required_if:target_scope,specific', 'array', 'max:1000'],
            'selected_barangays.*' => ['integer', 'distinct', 'exists:barangays,id'],
            'channels' => [$advisory ? 'required' : 'nullable', 'array:sms,siren,social,radio'],
            'channels.sms' => ['exclude_without:channels', 'required_with:channels', 'accepted'],
            'channels.siren' => ['exclude_without:channels', 'sometimes', 'boolean', 'declined'],
            'channels.social' => ['exclude_without:channels', 'sometimes', 'boolean', 'declined'],
            'channels.radio' => ['exclude_without:channels', 'sometimes', 'boolean', 'declined'],
        ];
    }
}

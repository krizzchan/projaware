<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAdvisoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['title', 'message'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
            'message' => ['required', 'string', 'max:670', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/'],
        ];
    }
}

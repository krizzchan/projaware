<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate') ?? false;
    }

    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s()-]+/', '', trim($phone));
        if (preg_match('/^\+?63(9\d{9})$/', $phone, $matches)) {
            return '0'.$matches[1];
        }

        return $phone;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['first_name', 'middle_initial', 'last_name', 'office', 'position', 'phone_number'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }
        if (isset($values['phone_number'])) {
            $values['phone_number'] = self::normalizePhone($values['phone_number']);
        }
        $this->merge($values);
    }

    public static function contactRules(?int $contactId = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'],
            'office' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('contacts', 'phone_number')->ignore($contactId)],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'barangay_id' => ['present', 'nullable', 'integer', 'exists:barangays,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function rules(): array
    {
        return self::contactRules();
    }
}

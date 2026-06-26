<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $patientId = $this->route('patient')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required', 'string', 'max:30',
                // Unique per tenant + phone (matches the Supabase constraint).
                Rule::unique('patients')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->ignore($patientId),
            ],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'A patient with this phone number already exists in your store.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->name),
            'phone' => trim((string) $this->phone),
            'gender' => $this->gender ?: null,
        ]);
    }
}

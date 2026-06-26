<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEyeRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'pd' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (['od', 'os'] as $eye) {
            $rules["{$eye}_sph"] = ['nullable', 'numeric'];
            $rules["{$eye}_cyl"] = ['nullable', 'numeric'];
            $rules["{$eye}_axis"] = ['nullable', 'integer', 'min:0', 'max:180'];
            $rules["{$eye}_add"] = ['nullable', 'numeric'];
            $rules["{$eye}_va"] = ['nullable', 'string', 'max:20'];
            $rules["{$eye}_spl"] = ['nullable', 'numeric'];
            $rules["{$eye}_dv"] = ['nullable', 'numeric'];
            $rules["{$eye}_nv"] = ['nullable', 'numeric'];
        }

        return $rules;
    }
}

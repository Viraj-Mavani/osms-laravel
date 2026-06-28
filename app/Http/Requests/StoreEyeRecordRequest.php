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
            // Clinically realistic bounds that also stay within the decimal(5,2)/(6,2)
            // columns, so an out-of-range value is a 422, never a DB-overflow 500.
            $rules["{$eye}_sph"] = ['nullable', 'numeric', 'between:-30,30'];
            $rules["{$eye}_cyl"] = ['nullable', 'numeric', 'between:-15,15'];
            $rules["{$eye}_axis"] = ['nullable', 'integer', 'min:0', 'max:180'];
            $rules["{$eye}_add"] = ['nullable', 'numeric', 'between:0,6'];
            $rules["{$eye}_va"] = ['nullable', 'string', 'max:20'];
            $rules["{$eye}_spl"] = ['nullable', 'numeric', 'between:-50,50'];
            $rules["{$eye}_dv"] = ['nullable', 'numeric', 'between:-50,50'];
            $rules["{$eye}_nv"] = ['nullable', 'numeric', 'between:-50,50'];
        }

        return $rules;
    }
}

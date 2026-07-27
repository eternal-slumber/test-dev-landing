<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9+\s()\-]+$/',
            ],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'comment' => ['required', 'string', 'min:10', 'max:3000'],
        ];
    }
}
